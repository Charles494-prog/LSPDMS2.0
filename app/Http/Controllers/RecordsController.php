<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Purok;
use App\Models\Record;
use App\Models\GlobalVariable;
use App\Models\MonthlyMovementSession;
use App\Models\MonthlySessionActivity;
use App\Models\MonthlySessionMovement;
use App\Services\R210Eligibility;
use App\Support\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordsController extends Controller
{
    /** @var \Illuminate\Support\Collection<int, Purok>|null */
    private ?\Illuminate\Support\Collection $purokSettings = null;

    public function search(Request $request, R210Eligibility $eligibility): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $query = trim($validated['q']);

        if ($query === '') {
            return response()->json(['message' => 'Enter a name or registry number.'], 422);
        }

        $nameParts = preg_split('/\s+/', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);

        $records = Record::query()
            ->where(function ($records) use ($query, $nameParts) {
                $records->where(function ($nameQuery) use ($nameParts) {
                    foreach ($nameParts as $part) {
                        $nameQuery->whereRaw('LOWER(full_name) LIKE ?', ['%'.$part.'%']);
                    }
                })->orWhereRaw('LOWER(registry) LIKE ?', ['%'.mb_strtolower($query).'%']);
            })
            ->orderBy('full_name')
            ->orderBy('registry')
            ->limit(50)
            ->get();

        $requirements = GlobalVariable::query()->first();
        $canViewEligibility = $this->canViewDelistmentEligibility($request);

        return response()->json([
            'data' => $records->map(fn (Record $record): array => $this->present($record, $eligibility, $requirements, $canViewEligibility)),
        ]);
    }

    public function smartHelper(): JsonResponse
    {
        $records = Record::query()
            ->select(['id', 'full_name', 'registry', 'status', 'kapisanan', 'purok', 'grupo'])
            ->orderBy('full_name')
            ->orderBy('registry')
            ->get();

        $findings = [];
        $registryGroups = [];
        $nameGroups = [];
        $namedRecords = [];

        foreach ($records as $record) {
            $registry = (string) ($record->registry ?? '');
            if (trim($registry) !== '' && mb_strtoupper(trim($registry)) !== 'RFID') {
                $registryGroups['registry:'.$registry][] = $record;
            }

            $name = $this->nameParts($record->full_name ?? '');
            if ($name['letters'] !== '') {
                $nameGroups['name:'.$name['letters'].':'.$name['suffix']][] = $record;
                $namedRecords[] = ['record' => $record, ...$name, 'length' => mb_strlen($name['letters'])];
            }
        }

        foreach ($registryGroups as $registryRecords) {
            if (count($registryRecords) < 2) {
                continue;
            }

            $findings[] = [
                'type' => 'duplicate_registry',
                'field' => 'registry',
                'match_value' => $registryRecords[0]->registry,
                'records' => $this->presentHelperRecords($registryRecords),
            ];
        }

        foreach ($nameGroups as $nameRecords) {
            if (count($nameRecords) < 2) {
                continue;
            }

            $findings[] = [
                'type' => 'duplicate_name',
                'field' => 'full_name',
                'match_value' => $nameRecords[0]->full_name,
                'similarity' => 100,
                'records' => $this->presentHelperRecords($nameRecords),
            ];
        }

        // The edit distance is at least the length difference, so only names whose
        // lengths alone can still reach 90% are compared. Records are grouped by name
        // length once, and each record is compared only with the later records in the
        // length groups it can match, in the same order as a full pairwise scan.
        $indexesByLength = [];
        foreach ($namedRecords as $index => $namedRecord) {
            $indexesByLength[$namedRecord['length']][] = $index;
        }
        $candidateLengths = [];
        foreach (array_keys($indexesByLength) as $length) {
            foreach (array_keys($indexesByLength) as $otherLength) {
                $longest = max($length, $otherLength);
                if ((int) round((1 - abs($length - $otherLength) / $longest) * 100) >= 90) {
                    $candidateLengths[$length][] = $otherLength;
                }
            }
        }

        $namedCount = count($namedRecords);
        for ($left = 0; $left < $namedCount; $left++) {
            $candidates = [];
            foreach ($candidateLengths[$namedRecords[$left]['length']] as $length) {
                foreach ($indexesByLength[$length] as $right) {
                    if ($right > $left) {
                        $candidates[] = $right;
                    }
                }
            }
            sort($candidates);

            foreach ($candidates as $right) {
                $leftName = $namedRecords[$left]['letters'];
                $rightName = $namedRecords[$right]['letters'];
                $leftSuffix = $namedRecords[$left]['suffix'];
                $rightSuffix = $namedRecords[$right]['suffix'];

                if ($leftSuffix !== '' && $rightSuffix !== '' && $leftSuffix !== $rightSuffix) {
                    continue;
                }

                if ($leftName === $rightName) {
                    continue;
                }

                $similarity = $this->nameSimilarity($leftName, $rightName);
                if ($similarity < 90) {
                    continue;
                }

                $findings[] = [
                    'type' => 'similar_name',
                    'field' => 'full_name',
                    'similarity' => $similarity,
                    'records' => $this->presentHelperRecords([
                        $namedRecords[$left]['record'],
                        $namedRecords[$right]['record'],
                    ]),
                ];
            }
        }

        return response()->json([
            'data' => $findings,
            'records_scanned' => $records->count(),
        ]);
    }

    public function update(Request $request, Record $record, R210Eligibility $eligibility): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'registry_number' => [
                'sometimes',
                'required',
                'string',
                'regex:/\A(?:[A-Z0-9]{13}|RFID)\z/i',
            ],
            'gender' => ['sometimes', 'required', 'string', Rule::in(['Male', 'Female'])],
            'kapisanan' => ['sometimes', 'nullable', 'string', 'max:50'],
            'purok' => ['prohibited'],
            'grupo' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            // "Today" is the Manila date; the app timezone is UTC, which lags it until 08:00.
            'date_of_baptism' => ['sometimes', 'nullable', 'date', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'is_married' => ['sometimes', 'required', 'boolean'],
            'evangelist' => ['sometimes', 'nullable', 'string', 'max:150'],
        ], [
            'registry_number.regex' => 'Enter exactly 13 letters or numbers, or RFID.',
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = mb_strtoupper(trim($validated['name']));
        }
        if (array_key_exists('registry_number', $validated)) {
            $validated['registry_number'] = mb_strtoupper(trim($validated['registry_number']));
        }

        $attributes = [];
            if (array_key_exists('registry_number', $validated)) {
                $validated['registry_number'] = mb_strtoupper(trim($validated['registry_number']));
            }

            if (array_key_exists('grupo', $validated) && $validated['grupo'] !== null && $validated['grupo'] !== '') {
                $purokNumber = $this->normalizePurokNumber($record->purok);
                $matchingPuroks = Purok::query()
                    ->where('purok_number', $purokNumber)
                    ->orderBy('grupo_start')
                    ->orderBy('id')
                    ->get();
                $currentPurok = $this->purokForGrupo($matchingPuroks, $record->grupo);
                if ($matchingPuroks->isNotEmpty() && $currentPurok === null) {
                    $ranges = $matchingPuroks
                        ->filter(fn (Purok $purok): bool => ! $purok->full_purok)
                        ->map(fn (Purok $purok): string => "{$purok->grupo_start}-{$purok->grupo_end}")
                        ->implode(', ');
                    throw ValidationException::withMessages([
                        'grupo' => ["The current Grupo is not in a configured Purok range ({$ranges}); update it through Monthly Modify."],
                    ]);
                }
                $grupoMin = $currentPurok ? ($currentPurok->full_purok ? 1 : (int) $currentPurok->grupo_start) : null;
                $grupoMax = $currentPurok ? ($currentPurok->full_purok ? 24 : (int) $currentPurok->grupo_end) : null;
                if ($currentPurok && ((int) $validated['grupo'] < $grupoMin || (int) $validated['grupo'] > $grupoMax)) {
                    throw ValidationException::withMessages([
                        'grupo' => ["Grupo must stay between {$grupoMin} and {$grupoMax} for this Purok. Use Monthly Modify to transfer to another Purok."],
                    ]);
                }
            }

            $fieldMap = [
                'name' => 'full_name',
                'registry_number' => 'registry',
                'gender' => 'gender',
                'kapisanan' => 'kapisanan',
                'grupo' => 'grupo',
                'date_of_baptism' => 'date_of_baptism',
                'date_of_birth' => 'date_of_birth',
                'evangelist' => 'evangelist',
            ];

            foreach ($fieldMap as $input => $column) {
                if (array_key_exists($input, $validated)) {
                    $attributes[$column] = match ($input) {
                        'kapisanan' => $this->storeKapisanan($validated[$input]),
                        'gender' => $this->storeGender($validated[$input]),
                        default => $validated[$input],
                    };
                }
            }

            DB::transaction(function () use ($record, $attributes, $validated): void {
                $lockedRecord = Record::query()->lockForUpdate()->findOrFail($record->getKey());
                if (array_key_exists('registry_number', $validated)) {
                    // Checked under a lock inside the transaction, so two edits cannot both take the same number.
                    $this->ensureRegistryIsFree($validated['registry_number'], (int) $record->getKey());
                }
                $maritalFieldsChanged = array_key_exists('is_married', $validated);
                $isMarried = $maritalFieldsChanged
                    ? (bool) $validated['is_married']
                    : (bool) $lockedRecord->is_married;
                if ($maritalFieldsChanged) {
                    $attributes['is_married'] = $isMarried;
                }

                $birthday = array_key_exists('date_of_birth', $attributes)
                    ? $attributes['date_of_birth']
                    : $lockedRecord->date_of_birth?->format('Y-m-d');
                if ($isMarried || $birthday) {
                    // Age is counted against Manila's date, not the UTC app clock.
                    $attributes['kapisanan'] = $isMarried
                        ? 'B'
                        : (Carbon::parse($birthday)->addYears(18)->isAfter(Carbon::parse(Carbon::now('Asia/Manila')->toDateString())) ? 'binhi' : 'K');
                } elseif (array_key_exists('kapisanan', $validated)) {
                    $attributes['kapisanan'] = $this->storeKapisanan($validated['kapisanan']);
                }

                $kapisanan = $attributes['kapisanan'] ?? $lockedRecord->kapisanan;
                if (in_array(mb_strtolower(trim((string) $kapisanan)), ['b', 'buklod'], true)) {
                    $attributes['is_married'] = true;
                }

                $lockedRecord->fill($attributes)->save();
            });
        $requirements = GlobalVariable::query()->first();
        $canViewEligibility = $this->canViewDelistmentEligibility($request);

        return response()->json([
            'data' => $this->present($record->refresh(), $eligibility, $requirements, $canViewEligibility),
        ]);
    }

    public function destroy(Record $record): JsonResponse
    {
        return DB::transaction(function () use ($record): JsonResponse {
            $lockedRecord = Record::query()->lockForUpdate()->find($record->getKey());
            if (! $lockedRecord) {
                return response()->json(['message' => 'This record was already deleted.'], 404);
            }
            if (mb_strtolower(trim((string) $lockedRecord->status)) !== 'inactive') {
                return response()->json([
                    'message' => 'You can only delete this record after changing its status to Inactive in Form Generator > Monthly > Deliberation.',
                ], 422);
            }

            // A pending monthly code or active-session log entry means finishing the session still needs this row
            // for permanent_history and the snapshot, and undo still needs it to restore the member.
            $activeSessionIds = MonthlyMovementSession::query()->where('active_key', 'active')->pluck('id');
            $inActiveSession = $activeSessionIds->isNotEmpty() && (
                MonthlySessionActivity::query()
                    ->whereIn('session_id', $activeSessionIds)
                    ->where('record_id', $lockedRecord->id)
                    ->exists()
                || MonthlySessionMovement::query()
                    ->whereIn('session_id', $activeSessionIds)
                    ->where('record_id', $lockedRecord->id)
                    ->exists()
            );
            if (trim((string) $lockedRecord->monthly_code) !== '' || $inActiveSession) {
                return response()->json([
                    'message' => 'This record has a movement in the active Monthly Session. Finish the session first, then delete it.',
                ], 422);
            }

            $lockedRecord->delete();

            return response()->json(['message' => 'Inactive record deleted.']);
        });
    }

    private function present(
        Record $record,
        R210Eligibility $eligibility,
        ?GlobalVariable $requirements,
        bool $canViewEligibility
    ): array
    {
        $purokSetting = $this->purokSettingForRecord($record);
        $grupoMin = $purokSetting
            ? ($purokSetting->full_purok ? 1 : (int) $purokSetting->grupo_start)
            : 1;
        $grupoMax = $purokSetting
            ? ($purokSetting->full_purok ? 24 : (int) $purokSetting->grupo_end)
            : 24;

        return [
            'id' => $record->id,
            'name' => $record->full_name ?? '',
            'registry_number' => $record->registry ?? '',
            'gender' => $this->displayGender($record->gender),
            'kapisanan' => $this->displayKapisanan($record->kapisanan),
            'purok' => $record->purok ?? '',
            'grupo' => $record->grupo ?? '',
            'date_of_baptism' => $record->date_of_baptism?->format('Y-m-d') ?? '',
            'date_of_birth' => $record->date_of_birth?->format('Y-m-d') ?? '',
            'is_married' => $record->is_married,
            'evangelist' => $record->evangelist ?? '',
            'date_of_entry' => $record->date_of_entry?->format('Y-m-d') ?? '',
            'r210_eligible' => $canViewEligibility
                ? ($eligibility->forRecord($record, $requirements) ? 'ELIGIBLE' : 'NOT ELIGIBLE')
                : null,
            'status' => $record->status ?? '',
            'monthly_code' => $record->monthly_code ?? '',
            'purok_name' => $purokSetting?->purok_name ?: ($record->purok ? 'Purok '.$record->purok : ''),
            'grupo_min' => $grupoMin,
            'grupo_max' => $grupoMax,
        ];
    }

    private function canViewDelistmentEligibility(Request $request): bool
    {
        $user = $request->session()->get('login_user', []);
        return Role::isHeadSecretary($user['tungkulin'] ?? '')
            || (bool) ($user['Delistment_allow'] ?? false);
    }

    private function normalizePurokNumber(mixed $value): ?int
    {
        $value = trim((string) $value);
        if (preg_match('/^(?:Purok\s*)?(\d+)$/i', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function purokForGrupo(\Illuminate\Support\Collection $puroks, mixed $grupo): ?Purok
    {
        if ($grupo === null || $grupo === '') {
            return $puroks->first(fn (Purok $purok): bool => ! $purok->full_purok)
                ?? $puroks->first();
        }

        return $puroks->first(fn (Purok $purok): bool => ! $purok->full_purok
                && (int) $grupo >= $purok->grupo_start
                && (int) $grupo <= $purok->grupo_end)
            ?? $puroks->first(fn (Purok $purok): bool => $purok->full_purok);
    }

    private function purokSettingForRecord(Record $record): ?Purok
    {
        $number = $this->normalizePurokNumber($record->purok);
        // Loaded once per request instead of once per record in a result list.
        $this->purokSettings ??= Purok::query()->orderBy('grupo_start')->orderBy('id')->get();
        $puroks = $number === null
            ? collect()
            : $this->purokSettings->where('purok_number', $number)->values();

        return $this->purokForGrupo($puroks, $record->grupo);
    }

    /**
     * 'RFID' is shared by many members and never conflicts; any other registry
     * number may belong to one record only. Runs inside the update transaction.
     */
    private function ensureRegistryIsFree(string $registry, int $recordId): void
    {
        if ($registry === 'RFID') {
            return;
        }

        $duplicates = Record::query()
            ->whereKeyNot($recordId)
            ->whereRaw('UPPER(TRIM(registry)) = ?', [$registry])
            ->orderBy('full_name')
            ->lockForUpdate()
            ->get(['id', 'full_name', 'registry']);
        if ($duplicates->isNotEmpty()) {
            $matches = $duplicates->map(fn (Record $duplicate): string => trim((string) $duplicate->full_name)
                .' (record #'.$duplicate->id.', registry '.trim((string) $duplicate->registry).')')->implode(', ');
            throw ValidationException::withMessages([
                'registry_number' => ["This registry number is already assigned to: {$matches}."],
            ]);
        }
    }


    private function presentHelperRecords(array $records): array
    {
        return array_map(fn (Record $record): array => [
            'id' => $record->id,
            'name' => $record->full_name ?? '',
            'registry_number' => $record->registry ?? '',
            'status' => $record->status ?? '',
            'kapisanan' => $this->displayKapisanan($record->kapisanan),
            'purok' => $record->purok ?? '',
            'grupo' => $record->grupo ?? '',
        ], $records);
    }

    /**
     * Normalize the name for comparison while retaining Jr/Sr as distinguishing suffixes.
     *
     * @return array{letters: string, suffix: string}
     */
    private function nameParts(string $name): array
    {
        preg_match('/\s+(jr|sr)\.?\s*$/iu', trim($name), $matches);
        $suffix = mb_strtolower($matches[1] ?? '');
        $name = preg_replace('/\s+(?:jr|sr)\.?\s*$/iu', '', trim($name)) ?? $name;

        return [
            'letters' => preg_replace('/[^\p{L}]/u', '', mb_strtolower($name)) ?? '',
            'suffix' => $suffix,
        ];
    }

    private function nameSimilarity(string $left, string $right): int
    {
        // PHP's built-in levenshtein() counts bytes, which equals counting letters
        // when both names are plain ASCII (nearly every name); others use the loop below.
        if (! preg_match('/[^\x00-\x7F]/', $left.$right)) {
            $longest = max(strlen($left), strlen($right));

            return $longest === 0 ? 100 : (int) round((1 - levenshtein($left, $right) / $longest) * 100);
        }

        $leftLetters = mb_str_split($left);
        $rightLetters = mb_str_split($right);
        $leftLength = count($leftLetters);
        $rightLength = count($rightLetters);
        $longest = max($leftLength, $rightLength);

        if ($longest === 0) {
            return 100;
        }

        $previous = range(0, $rightLength);
        for ($row = 1; $row <= $leftLength; $row++) {
            $current = [$row];
            for ($column = 1; $column <= $rightLength; $column++) {
                $cost = $leftLetters[$row - 1] === $rightLetters[$column - 1] ? 0 : 1;
                $current[$column] = min(
                    $current[$column - 1] + 1,
                    $previous[$column] + 1,
                    $previous[$column - 1] + $cost
                );
            }
            $previous = $current;
        }

        return (int) round((1 - $previous[$rightLength] / $longest) * 100);
    }

    private function displayKapisanan(?string $kapisanan): string
    {
        return match (mb_strtolower(trim($kapisanan ?? ''))) {
            'b' => 'Buklod',
            'k' => 'Kadiwa',
            'binhi' => 'binhi',
            default => $kapisanan ?? '',
        };
    }

    private function storeKapisanan(?string $kapisanan): ?string
    {
        return match (mb_strtolower(trim($kapisanan ?? ''))) {
            'b', 'buklod' => 'B',
            'k', 'kadiwa' => 'K',
            'binhi' => 'binhi',
            default => $kapisanan,
        };
    }

    private function displayGender(?string $gender): string
    {
        return match (mb_strtoupper(trim($gender ?? ''))) {
            'L', 'MALE' => 'Male',
            'B', 'FEMALE' => 'Female',
            default => $gender ?? '',
        };
    }

    private function storeGender(string $gender): string
    {
        return match (mb_strtolower(trim($gender))) {
            'male' => 'L',
            'female' => 'B',
        };
    }
}
