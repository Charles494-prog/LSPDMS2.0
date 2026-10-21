<?php

namespace App\Http\Controllers;

use App\Models\DelistmentHistory;
use App\Models\GlobalVariable;
use App\Models\Purok;
use App\Models\Record;
use App\Models\User;
use App\Services\DelistmentDocxExporter;
use App\Services\R210Eligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DelistmentController extends Controller
{
    private const STATUSES = ['MS', 'UWP', 'MK'];
    private const EXPORT_CATEGORIES = [
        'eligible',
        'not-eligible',
        'not-eligible-no-endorsement',
        'not-eligible-endorsed',
        'eligible-no-endorsement',
        'eligible-endorsed',
        'mk',
        'progress-ms-reported',
        'progress-ms-pending',
        'progress-uwp-reported',
        'progress-uwp-pending',
        'progress-mk-reported',
        'progress-mk-pending',
    ];

    public function index(R210Eligibility $eligibility)
    {
        $globalVariables = GlobalVariable::query()->first();
        $records = Record::query()
            ->select([
                'id',
                'full_name',
                'registry',
                'status',
                'r2_10_endorsement',
                'date_of_baptism',
                'date_of_entry',
            ])
            ->orderBy('full_name')
            ->get()
            ->filter(fn (Record $record): bool => in_array(
                mb_strtoupper(trim((string) $record->status)),
                self::STATUSES,
                true
            ))
            ->values();

        $members = $records->map(function (Record $record) use ($eligibility, $globalVariables): array {
            $status = mb_strtoupper(trim((string) $record->status));
            $eligible = $eligibility->forRecord($record, $globalVariables);
            $endorsed = mb_strtoupper(trim((string) $record->r2_10_endorsement)) === 'ENDORSED';

            return [
                'id' => $record->id,
                'name' => trim((string) $record->full_name) ?: 'Unnamed record',
                'registry' => trim((string) $record->registry),
                'kalagayan' => $status,
                'eligible' => $eligible,
                'endorsed' => $endorsed,
                'reported' => $eligible && $endorsed,
                'entryDate' => $record->date_of_entry?->format('Y-m-d'),
            ];
        })->all();

        $requireBaptismDate = (bool) $globalVariables?->dateofbaptism_requirement_boolean;
        $missingRecords = $records
            ->filter(fn (Record $record): bool => $requireBaptismDate && ! $record->date_of_baptism)
            ->map(fn (Record $record): array => [
                'id' => $record->id,
                'name' => trim((string) $record->full_name) ?: 'Unnamed record',
                'registry' => trim((string) $record->registry) ?: '—',
                'baptism' => $record->date_of_baptism?->format('Y-m-d') ?? '',
            ])
            ->values()
            ->all();

        return view('de-listment', [
            'members' => $members,
            'missingRecords' => $missingRecords,
            'requireBaptismDate' => $requireBaptismDate,
            'puroks' => Purok::query()->orderBy('purok_number')->get(),
            'delistedRecords' => DelistmentHistory::query()
                ->with('record:id,full_name,registry')
                ->orderByDesc('delisted_at')
                ->orderByDesc('id')
                ->get(),
            'entryYearsRequirement' => $globalVariables?->entry_years_requirement ?? 1,
        ]);
    }

    public function addMkMember(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'registry' => ['required', 'string', 'max:100'],
            'is_rfid' => ['sometimes', 'boolean'],
            'gender' => ['required', 'in:Male,Female'],
            'purok_id' => ['required', 'integer', 'exists:puroks,id'],
            'grupo' => ['nullable', 'integer', 'min:1'],
            'kapisanan' => ['required', 'in:Buklod,Kadiwa,Binhi,Suliranin'],
            'date_of_baptism' => ['nullable', 'date', 'before_or_equal:today'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'evangelist' => ['nullable', 'string', 'max:150'],
            'date_of_entry' => ['nullable', 'date', 'before_or_equal:today'],
            'is_married' => ['sometimes', 'boolean'],
        ]);

        $registry = mb_strtoupper(trim($validated['registry']));
        $name = mb_strtoupper(trim($validated['full_name']));
        $isRfid = (bool) ($validated['is_rfid'] ?? false);
        if (! $isRfid && preg_match('/^[A-Z0-9]{13}$/', $registry) !== 1) {
            throw ValidationException::withMessages([
                'registry' => 'Enter exactly 13 letters or numbers, or select the RFID option.',
            ]);
        }

        return DB::transaction(function () use ($validated, $registry, $name): JsonResponse {
            $duplicate = Record::query()
                ->where(function ($query) use ($registry, $name): void {
                    $query->whereRaw('UPPER(TRIM(registry)) = ?', [$registry])
                        ->orWhereRaw('UPPER(TRIM(full_name)) = ?', [$name]);
                })
                ->lockForUpdate()
                ->first();
            if ($duplicate && mb_strtoupper(trim((string) $duplicate->registry)) === $registry) {
                throw ValidationException::withMessages([
                    'registry' => 'A record with this registry or RFID already exists.',
                ]);
            }
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'full_name' => 'A record with this name already exists. Check for an existing record before adding another.',
                ]);
            }

            $purok = Purok::query()->findOrFail($validated['purok_id']);
            $grupo = $validated['grupo'] ?? null;
            if ($grupo !== null && ($grupo < $purok->grupo_start || $grupo > $purok->grupo_end)) {
                throw ValidationException::withMessages([
                    'grupo' => "Enter a group number from {$purok->grupo_start} to {$purok->grupo_end} for this Purok.",
                ]);
            }

            $isMarried = (bool) ($validated['is_married'] ?? false);
            if (isset($validated['date_of_birth'])) {
                $kapisanan = $isMarried
                    ? 'B'
                    : (Carbon::parse($validated['date_of_birth'])->age < 18 ? 'binhi' : 'K');
            } else {
                $kapisanan = match ($isMarried ? 'Buklod' : $validated['kapisanan']) {
                    'Buklod' => 'B',
                    'Kadiwa' => 'K',
                    'Binhi' => 'binhi',
                    default => 'Suliranin',
                };
                $isMarried = $isMarried || $validated['kapisanan'] === 'Buklod';
            }

            $record = Record::query()->create([
                'full_name' => $name,
                'registry' => $registry,
                'gender' => $validated['gender'] === 'Male' ? 'L' : 'B',
                'purok' => (string) $purok->purok_number,
                'grupo' => $grupo === null ? null : (string) $grupo,
                'kapisanan' => $kapisanan,
                'date_of_baptism' => $validated['date_of_baptism'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'evangelist' => isset($validated['evangelist']) ? trim($validated['evangelist']) : null,
                'date_of_entry' => $validated['date_of_entry'] ?? now()->timezone('Asia/Manila')->toDateString(),
                'is_married' => $isMarried,
                'status' => 'MK',
                'monthly_code' => null,
            ]);

            return response()->json([
                'message' => 'MK member added.',
                'data' => [
                    'id' => $record->id,
                    'name' => $record->full_name,
                    'registry' => $record->registry,
                    'kalagayan' => 'MK',
                    'eligible' => true,
                    'endorsed' => false,
                    'reported' => false,
                    'entryDate' => $record->date_of_entry?->format('Y-m-d'),
                    'baptism' => $record->date_of_baptism?->format('Y-m-d') ?? '',
                ],
            ], 201);
        });
    }

    public function exportCategory(
        string $category,
        R210Eligibility $eligibility,
        DelistmentDocxExporter $exporter
    ) {
        abort_unless(in_array($category, self::EXPORT_CATEGORIES, true), 404);
        $globalVariables = GlobalVariable::query()->first();
        $members = Record::query()
            ->select(['id', 'full_name', 'registry', 'status', 'r2_10_endorsement'])
            ->whereIn(DB::raw('UPPER(TRIM(status))'), self::STATUSES)
            ->orderBy('full_name')
            ->get()
            ->map(function (Record $record) use ($eligibility, $globalVariables): array {
                $eligible = $eligibility->forRecord($record, $globalVariables);
                $endorsed = mb_strtoupper(trim((string) $record->r2_10_endorsement)) === 'ENDORSED';
                $status = mb_strtoupper(trim((string) $record->status));

                return [
                    'name' => trim((string) $record->full_name) ?: 'Unnamed record',
                    'registry' => trim((string) $record->registry),
                    'kalagayan' => $status,
                    'eligible' => $eligible,
                    'endorsed' => $endorsed,
                    'reported' => $eligible && $endorsed,
                ];
            })
            ->filter(fn (array $member): bool => $this->matchesExportCategory($member, $category))
            ->values()
            ->all();

        abort_if($members === [], 404, 'No records match this chart category.');
        $path = $exporter->generate($this->exportCategoryLabel($category), $members);
        $fileName = 'R2-10 - '.$this->exportCategoryLabel($category).'.docx';

        return response()->download(
            $path,
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }

    public function exportHistory(DelistmentDocxExporter $exporter)
    {
        $history = DelistmentHistory::query()
            ->with('record:id,full_name,registry')
            ->orderByDesc('delisted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DelistmentHistory $entry): array => [
                'name' => $entry->record?->full_name ?: 'Deleted record',
                'registry' => $entry->record?->registry ?: '',
                'delisted_at' => $entry->delisted_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?: '—',
                'reason' => $entry->reason,
                'delisted_by' => $entry->delisted_by,
            ])
            ->all();

        if ($history === []) {
            $history[] = [
                'name' => 'No delistment history recorded.',
                'registry' => '',
                'delisted_at' => '—',
                'reason' => '—',
                'delisted_by' => '—',
            ];
        }

        $path = $exporter->generate('De-Listment History', $history, history: true);

        return response()->download(
            $path,
            'De-Listment History.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }

    public function endorse(Request $request, Record $record, R210Eligibility $eligibility): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'one_year_confirmed' => ['sometimes', 'boolean'],
            'assurance' => ['accepted'],
        ]);

        return DB::transaction(function () use ($record, $validated, $request, $eligibility): JsonResponse {
            $record = Record::query()->lockForUpdate()->findOrFail($record->id);
            $status = mb_strtoupper(trim((string) $record->status));
            $eligible = $eligibility->forRecord($record, GlobalVariable::query()->first());

            if (! in_array($status, self::STATUSES, true) || ! $eligible) {
                return response()->json([
                    'message' => 'Only eligible MS, UWP, or MK records can be endorsed.',
                ], 422);
            }

            if (mb_strtoupper(trim((string) $record->r2_10_endorsement)) === 'ENDORSED') {
                return response()->json([
                    'message' => 'This record is already marked as endorsed.',
                ], 409);
            }

            $oneYearConfirmed = (bool) ($validated['one_year_confirmed'] ?? false);
            $note = preg_replace('/[\r\n]+/', ' ', trim((string) ($validated['note'] ?? ''))) ?? '';
            if ($status === 'MK' && ($note === '' || $oneYearConfirmed)) {
                return response()->json([
                    'message' => 'MK endorsements require a specific brief description.',
                ], 422);
            }
            if (in_array($status, ['MS', 'UWP'], true) && $note === '' && ! $oneYearConfirmed) {
                return response()->json([
                    'message' => 'Enter a brief description or confirm that this member has been MS/UWP for at least one year.',
                ], 422);
            }

            $user = $request->session()->get('login_user', []);
            $author = trim((string) ($user['gmail'] ?? ''));
            if ($author === '') {
                return response()->json([
                    'message' => 'The signed-in account email is unavailable. Please sign in again.',
                ], 422);
            }
            $reason = $oneYearConfirmed ? '1 year MS or UWP' : 'due to '.$note;
            $auditEntry = $author.' ('.Carbon::now()->format('Y-m-d H:i:s').'): '.$reason.'.';
            $existingReason = trim((string) $record->reason_of_r2_10_endorsement);
            $record->r2_10_endorsement = 'ENDORSED';
            $record->reason_of_r2_10_endorsement = $existingReason === ''
                ? $auditEntry
                : $existingReason.PHP_EOL.$auditEntry;
            $record->save();

            return response()->json([
                'message' => 'R2-10 endorsement saved.',
                'data' => [
                    'id' => $record->id,
                    'r2_10_endorsement' => $record->r2_10_endorsement,
                ],
            ]);
        });
    }

    public function delist(Request $request, Record $record): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $record, $validated): JsonResponse {
            $record = Record::query()->lockForUpdate()->findOrFail($record->id);
            if (mb_strtoupper(trim((string) $record->status)) !== 'MK') {
                return response()->json(['message' => 'Only MK records can be delisted from this page.'], 422);
            }
            if (mb_strtoupper(trim((string) $record->r2_10_endorsement)) !== 'ENDORSED') {
                return response()->json(['message' => 'The MK record must be endorsed before it can be delisted.'], 422);
            }

            $sessionUser = $request->session()->get('login_user', []);
            $user = User::query()->find($sessionUser['id'] ?? null);
            if (! $user) {
                return response()->json(['message' => 'The signed-in account is unavailable. Please sign in again.'], 401);
            }
            $author = trim((string) $user->gmail);
            if ($author === '') {
                return response()->json(['message' => 'The signed-in account email is unavailable. Please sign in again.'], 422);
            }

            $now = Carbon::now('Asia/Manila');
            $reason = trim(preg_replace('/[\r\n]+/', ' ', $validated['reason']) ?? '');
            DelistmentHistory::query()->create([
                'record_id' => $record->id,
                'delisted_by_user_id' => $user->id,
                'delisted_by' => $author,
                'reason' => $reason,
                'delisted_at' => $now,
            ]);

            $record->status = 'INACTIVE';
            $this->appendDelistmentLog($record, $now, $reason);
            $record->save();

            return response()->json([
                'message' => 'MK record delisted and marked INACTIVE.',
                'data' => [
                    'id' => $record->id,
                    'status' => $record->status,
                    'delisted_at' => $now->toISOString(),
                ],
            ]);
        });
    }

    public function updateDates(Request $request, R210Eligibility $eligibility): JsonResponse
    {
        $validated = $request->validate([
            'changes' => ['required', 'array', 'min:1', 'max:500'],
            'changes.*.id' => ['required', 'integer', 'distinct', 'exists:records,id'],
            'changes.*.baptism' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $saved = DB::transaction(function () use ($validated, $eligibility): array {
            $updated = [];
            $globalVariables = GlobalVariable::query()->first();
            foreach ($validated['changes'] as $change) {
                $record = Record::query()->lockForUpdate()->findOrFail($change['id']);
                $status = mb_strtoupper(trim((string) $record->status));
                if (! in_array($status, self::STATUSES, true)) {
                    throw ValidationException::withMessages([
                        'changes' => 'Dates can only be updated for MS, UWP, or MK records.',
                    ]);
                }

                if (array_key_exists('baptism', $change)) {
                    $record->date_of_baptism = $change['baptism'] ?: null;
                }
                $record->save();
                $eligible = $eligibility->forRecord($record, $globalVariables);
                $updated[] = [
                    'id' => $record->id,
                    'baptism' => $record->date_of_baptism?->format('Y-m-d') ?? '',
                    'eligible' => $eligible,
                    'reported' => $eligible
                        && mb_strtoupper(trim((string) $record->r2_10_endorsement)) === 'ENDORSED',
                ];
            }

            return $updated;
        });

        return response()->json([
            'message' => 'Record dates saved.',
            'data' => $saved,
        ]);
    }

    private function appendDelistmentLog(Record $record, Carbon $date, string $reason): void
    {
        $existing = (string) $record->log_section;
        $entry = $date->format('d/m/Y').': Delisted on '.$date->format('F d, Y').': '.$reason.',';
        $separator = $existing === ''
            ? ''
            : (preg_match('/,\s*$/u', $existing) === 1
                ? (preg_match('/,\s+$/u', $existing) === 1 ? '' : ' ')
                : ', ');
        $record->log_section = $existing.$separator.$entry;
    }

    private function matchesExportCategory(array $member, string $category): bool
    {
        return match ($category) {
            'eligible' => $member['eligible'],
            'not-eligible' => ! $member['eligible'],
            'not-eligible-no-endorsement' => ! $member['eligible'] && ! $member['endorsed'] && $member['kalagayan'] !== 'MK',
            'not-eligible-endorsed' => ! $member['eligible'] && $member['endorsed'] && $member['kalagayan'] !== 'MK',
            'eligible-no-endorsement' => $member['eligible'] && ! $member['endorsed'] && $member['kalagayan'] !== 'MK',
            'eligible-endorsed' => $member['eligible'] && $member['endorsed'] && $member['kalagayan'] !== 'MK',
            'mk' => $member['kalagayan'] === 'MK',
            'progress-ms-reported' => $member['kalagayan'] === 'MS' && $member['reported'],
            'progress-ms-pending' => $member['kalagayan'] === 'MS' && ! $member['reported'],
            'progress-uwp-reported' => $member['kalagayan'] === 'UWP' && $member['reported'],
            'progress-uwp-pending' => $member['kalagayan'] === 'UWP' && ! $member['reported'],
            'progress-mk-reported' => $member['kalagayan'] === 'MK' && $member['reported'],
            'progress-mk-pending' => $member['kalagayan'] === 'MK' && ! $member['reported'],
            default => false,
        };
    }

    private function exportCategoryLabel(string $category): string
    {
        return match ($category) {
            'eligible' => 'Eligible',
            'not-eligible' => 'Not eligible',
            'not-eligible-no-endorsement' => 'Not eligible - No endorsement',
            'not-eligible-endorsed' => 'Not eligible - Endorsed (review required)',
            'eligible-no-endorsement' => 'Eligible - No endorsement',
            'eligible-endorsed' => 'Eligible - Endorsed',
            'mk' => 'MK',
            'progress-ms-reported' => 'MS - Created and reported',
            'progress-ms-pending' => 'MS - Not yet done',
            'progress-uwp-reported' => 'UWP - Created and reported',
            'progress-uwp-pending' => 'UWP - Not yet done',
            'progress-mk-reported' => 'MK - Created and reported',
            'progress-mk-pending' => 'MK - Not yet done',
        };
    }
}
