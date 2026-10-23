<?php

namespace App\Http\Controllers;

use App\Models\MonthlyMovementSession;
use App\Models\MonthlyRecordSnapshot;
use App\Models\MonthlySessionActivity;
use App\Models\MonthlySessionMovement;
use App\Models\Purok;
use App\Models\R210EndorsementReason;
use App\Models\R401Snapshot;
use App\Models\Record;
use App\Services\FileStore;
use App\Services\MonthlySessionSnapshotExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliberationController extends Controller
{
    private const MOVEMENT_STATE_FIELDS = [
        'status',
        'purok',
        'grupo',
        'monthly_code',
        'date_of_entry',
        'reason_of_r2_10_endorsement',
        'log_section',
    ];

    public function startSession(Request $request): JsonResponse
    {
        try {
            $session = DB::transaction(fn (): MonthlyMovementSession => $this->activeMonthlySession($request, true));
        } catch (UniqueConstraintViolationException) {
            // Another request created the active session between our checks and the insert.
            throw ValidationException::withMessages([
                'session' => ['Another monthly session was started at the same time. Refresh the page to see the active session.'],
            ]);
        }

        return response()->json([
            'session' => $this->sessionPayload($session),
            'warning' => $session->wasRecentlyCreated
                ? $this->skippedPeriodWarning(Carbon::parse($session->period_month))
                : null,
        ]);
    }

    public function sessionStatus(Request $request): JsonResponse
    {
        $session = MonthlyMovementSession::query()->where('active_key', 'active')->first();
        $response = [
            'session' => $session ? $this->sessionPayload($session) : null,
            'start_availability' => $session ? null : $this->sessionStartAvailability(),
        ];
        if ($session && $request->boolean('include_movements')) {
            $response['movements'] = $session->movements()
                ->orderByDesc('changed_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (MonthlySessionMovement $movement): array => [
                    'id' => $movement->id,
                    'name' => $movement->full_name,
                    'registry' => $movement->registry,
                    'gender' => $movement->gender,
                    'kapisanan' => $movement->kapisanan,
                    'date_of_baptism' => $movement->date_of_baptism?->format('d/m/Y'),
                    'evangelist' => $movement->evangelist,
                    'action' => ucfirst($movement->action),
                    'destination_description' => $movement->destination_description,
                    'monthly_code' => $movement->monthly_code,
                    'description' => $movement->description,
                    'changed_by' => $movement->changed_by,
                    'changed_at' => $movement->changed_at?->timezone('Asia/Manila')->format('d/m/Y H:i:s'),
                    'undo_url' => route('forms.monthly.session.undo', $movement),
                ])
                ->values();
            $activityGroups = $session->activities()
                ->orderByDesc('changed_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn (MonthlySessionActivity $activity): string => $activity->session_movement_id
                    ? 'movement-'.$activity->session_movement_id
                    : 'activity-'.$activity->id);
            $response['activities'] = $activityGroups
                ->map(function ($activities): array {
                    /** @var MonthlySessionActivity $activity */
                    $activity = $activities->first();
                    $undoUrl = $activity->session_movement_id
                        ? route('forms.monthly.session.undo', $activity->session_movement_id)
                        : ($activity->record_id !== null
                            && in_array($activity->action, ['add', 'subtract', 'reactivate'], true)
                            && $activity->previous_values !== null
                            && $activity->next_values !== null
                                ? route('forms.monthly.session.activity.undo', $activity)
                                : null);

                    return [
                        'id' => $activity->session_movement_id ?? $activity->id,
                        'name' => $activity->full_name,
                        'registry' => $activity->registry,
                        'gender' => $activity->gender,
                        'kapisanan' => $activity->kapisanan,
                        'action' => ucfirst($activity->action),
                        'description' => $activity->description,
                        'changed_by' => $activity->changed_by,
                        'changed_at' => $activity->changed_at?->timezone('Asia/Manila')->format('d/m/Y H:i:s'),
                        'entries' => $activities->map(fn (MonthlySessionActivity $entry): array => [
                            'name' => $entry->full_name,
                            'monthly_code' => $entry->monthly_code,
                            'direction' => $entry->direction,
                            'location' => $entry->purok && $entry->grupo
                                ? $entry->purok.'-'.$entry->grupo
                                : ($entry->purok ? 'Purok '.$entry->purok : 'Location not set'),
                        ])->sortBy(fn (array $entry): int => $entry['direction'] === 'out' ? 0 : 1)
                            ->values(),
                        'undo_url' => $undoUrl,
                    ];
                })
                ->values();
            $response['session']['activity_count'] = $activityGroups->count();
        }

        return response()->json($response);
    }

    public function sessionMonitor()
    {
        $session = MonthlyMovementSession::query()
            ->where('active_key', 'active')
            ->with(['movements' => fn ($query) => $query->orderByDesc('changed_at')->orderByDesc('id')])
            ->first();

        return view('forms.monthly-session', compact('session'));
    }

    public function undoMovement(Request $request, MonthlySessionMovement $movement): JsonResponse
    {
        DB::transaction(function () use ($movement): void {
            $session = MonthlyMovementSession::query()
                ->whereKey($movement->session_id)
                ->where('active_key', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            $movement = MonthlySessionMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($movement->record_id === null) {
                throw ValidationException::withMessages(['movement' => ['This movement no longer has a record to restore.']]);
            }
            if (MonthlySessionMovement::query()
                ->where('session_id', $session->id)
                ->where('record_id', $movement->record_id)
                ->where('id', '>', $movement->id)
                ->exists()) {
                throw ValidationException::withMessages(['movement' => ['Undo later movements for this member first.']]);
            }

            $record = Record::query()->lockForUpdate()->findOrFail($movement->record_id);
            if ($this->movementState($record) != $movement->next_values) {
                throw ValidationException::withMessages([
                    'movement' => ['This member changed after the logged movement. Review the record before undoing it.'],
                ]);
            }
            if ($movement->action === 'status') {
                // Undoing MS -> UWP (or back) would itself be an MS/UWP swap.
                $this->ensureEndorsedRecordStaysOffMsUwpSwap(
                    $record,
                    (string) ($movement->next_values['status'] ?? ''),
                    (string) ($movement->previous_values['status'] ?? '')
                );
            }
            if ($movement->action === 'add') {
                $record->status = 'INACTIVE';
                $record->date_of_entry = null;
                $record->monthly_code = null;
                foreach (['reason_of_r2_10_endorsement', 'log_section'] as $field) {
                    $record->{$field} = $movement->previous_values[$field] ?? null;
                }
            } else {
                $record->forceFill($movement->previous_values);
            }
            R210EndorsementReason::revertMovement($record->id, $movement->id);
            $record->save();
            $movement->delete();
        });

        return response()->json(['message' => 'Movement undone and removed from the temporary session log.']);
    }

    public function undoActivity(Request $request, MonthlySessionActivity $activity): JsonResponse
    {
        DB::transaction(function () use ($activity): void {
            $session = MonthlyMovementSession::query()
                ->whereKey($activity->session_id)
                ->where('active_key', 'active')
                ->lockForUpdate()
                ->firstOrFail();
            $activity = MonthlySessionActivity::query()
                ->whereKey($activity->id)
                ->where('session_id', $session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($activity->session_movement_id !== null
                || ! in_array($activity->action, ['add', 'subtract', 'reactivate'], true)
                || $activity->record_id === null
                || $activity->previous_values === null
                || $activity->next_values === null) {
                throw ValidationException::withMessages([
                    'activity' => ['This activity cannot be undone independently.'],
                ]);
            }
            if ($session->activities()
                ->where('record_id', $activity->record_id)
                ->where('id', '>', $activity->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'activity' => ['Undo later activity for this member first.'],
                ]);
            }

            $record = Record::query()->lockForUpdate()->findOrFail($activity->record_id);
            if ($this->movementState($record) != $activity->next_values) {
                throw ValidationException::withMessages([
                    'activity' => ['This member changed after the logged activity. Review the record before undoing it.'],
                ]);
            }

            if ($activity->action === 'add') {
                $record->status = 'INACTIVE';
                $record->date_of_entry = null;
                $record->monthly_code = null;
                foreach (['reason_of_r2_10_endorsement', 'log_section'] as $field) {
                    $record->{$field} = $activity->previous_values[$field] ?? null;
                }
            } else {
                $record->forceFill($activity->previous_values);
            }
            $record->save();
            $activity->delete();
        });

        return response()->json(['message' => 'Activity undone and removed from the monthly session log.']);
    }

    public function finishSession(
        Request $request,
        MonthlySessionSnapshotExporter $snapshotExporter,
        FileStore $fileStore
    ): JsonResponse {
        $snapshotPath = null;
        $previewPath = null;
        try {
            $result = DB::transaction(function () use ($request, $snapshotExporter, $fileStore, &$snapshotPath, &$previewPath): array {
            $session = MonthlyMovementSession::query()
                ->where('active_key', 'active')
                ->lockForUpdate()
                ->first();
            if (! $session) {
                // A double-submitted finish lands here after the first request completed.
                throw new HttpResponseException(response()->json([
                    'message' => 'This monthly session has already been finished. Refresh the page to see the latest status.',
                ], 409));
            }
            $movements = MonthlySessionMovement::query()
                ->where('session_id', $session->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $count = $movements->count();
            $actor = $this->changedBy($request);

            $codedRecords = Record::query()
                ->whereNotNull('monthly_code')
                ->whereRaw("TRIM(monthly_code) <> ''")
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $snapshot = $snapshotExporter->generate($session, true);
            foreach ($codedRecords as $record) {
                [$codeStatus, $code] = $this->monthlyCodeParts(
                    (string) $record->monthly_code,
                    mb_strtoupper(trim((string) $record->status))
                );
                $nextValues = $this->movementState($record);
                $previousValues = $nextValues;
                if ($record->status === 'INACTIVE' && in_array($codeStatus, ['MS', 'UWP'], true)) {
                    $previousValues['status'] = $codeStatus;
                }
                DB::table('permanent_history')->insert([
                    'session_id' => $session->id,
                    'period_month' => $session->period_month?->toDateString(),
                    'record_id' => $record->id,
                    'action' => match ($code) {
                        'B' => 'add',
                        'C+', 'C-' => 'transfer',
                        'D', 'DD', 'G', 'H' => 'subtract',
                        default => 'status',
                    },
                    'full_name' => trim((string) $record->full_name) ?: 'Unnamed record',
                    'registry' => trim((string) $record->registry) ?: null,
                    'gender' => trim((string) $record->gender) ?: null,
                    'kapisanan' => trim((string) $record->kapisanan) ?: null,
                    'status' => $codeStatus ?: null,
                    'monthly_code' => trim((string) $record->monthly_code),
                    'destination_description' => $code === 'D' || $code === 'DD' || $code === 'G' || $code === 'H'
                        ? "{$codeStatus} -> INACTIVE ({$code})"
                        : null,
                    'description' => null,
                    'changed_by' => $actor,
                    'changed_at' => now(),
                    'date_of_baptism' => $record->date_of_baptism?->format('Y-m-d'),
                    'evangelist' => trim((string) $record->evangelist) ?: null,
                    'previous_values' => json_encode($previousValues, JSON_THROW_ON_ERROR),
                    'next_values' => json_encode($nextValues, JSON_THROW_ON_ERROR),
                ]);
            }

            foreach ($movements as $movement) {
                DB::table('permanent_history')->insert([
                    'session_id' => $session->id,
                    'period_month' => $session->period_month?->toDateString(),
                    'record_id' => $movement->record_id,
                    'action' => $movement->action,
                    'full_name' => $movement->full_name,
                    'registry' => $movement->registry,
                    'gender' => $movement->gender,
                    'kapisanan' => $movement->kapisanan,
                    'status' => $movement->status,
                    'monthly_code' => $movement->monthly_code,
                    'destination_description' => $movement->destination_description,
                    'description' => $movement->description,
                    'changed_by' => $movement->changed_by ?: $actor,
                    'changed_at' => $movement->changed_at,
                    'date_of_baptism' => $movement->date_of_baptism?->format('Y-m-d'),
                    'evangelist' => $movement->evangelist,
                    'previous_values' => json_encode($movement->previous_values, JSON_THROW_ON_ERROR),
                    'next_values' => json_encode($movement->next_values, JSON_THROW_ON_ERROR),
                ]);
            }

            $this->snapshotSession($session, $movements);
            $month = $session->period_month;
            $fileName = 'Monthly_'.$month->format('F_Y').'_RESUMEN_MS_UWP.docx';
            $directory = 'monthly-session-snapshots/'.$month->format('Y-m').'/';
            $candidate = $directory.'session-'.$session->id.'.docx';
            // Never overwrite an earlier snapshot (disk or database copy) that shares this name.
            for ($attempt = 0; $fileStore->exists('local', $candidate)
                || $fileStore->exists('local', str_replace('.docx', '.html', $candidate)); $attempt++) {
                if ($attempt >= 5) {
                    throw new \RuntimeException('Unable to choose a unique file name for the monthly session snapshot.');
                }
                $candidate = $directory.'session-'.$session->id.'-'.Str::lower(Str::random(6)).'.docx';
            }
            // The snapshot viewer derives the preview path from file_path by swapping the extension.
            $snapshotPath = $candidate;
            $fileStore->put(
                'local',
                $snapshotPath,
                $snapshot['contents'],
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );
            $previewPath = str_replace('.docx', '.html', $snapshotPath);
            $fileStore->put('local', $previewPath, $snapshot['preview'], 'text/html');
            R401Snapshot::query()->create([
                'session_id' => $session->id,
                'period_month' => $month->toDateString(),
                'report_type' => 'Monthly session',
                'kalagayan' => '',
                'purok_selection' => 'All Puroks',
                'format' => 'docx',
                'file_path' => $snapshotPath,
                'file_name' => $fileName,
                'generated_by' => $actor,
            ]);
            MonthlySessionActivity::query()
                ->where('session_id', $session->id)
                ->whereNotNull('session_movement_id')
                ->update([
                    'session_movement_id' => null,
                    // Explicit self-assignment keeps changed_at even where the column still auto-updates.
                    'changed_at' => DB::raw('changed_at'),
                ]);
            MonthlySessionMovement::query()->where('session_id', $session->id)->delete();
            $clearedMonthlyCodes = $codedRecords->count();
            if ($clearedMonthlyCodes > 0) {
                Record::query()
                    ->whereIn('id', $codedRecords->modelKeys())
                    ->update(['monthly_code' => null]);
            }
            DB::table('system_maintenance_audits')->insert([
                'action' => 'finish_monthly_clear_codes',
                'affected_count' => $clearedMonthlyCodes,
                'changed_by' => $actor,
                'changed_at' => now(),
            ]);
            $session->forceFill([
                'status' => 'completed',
                'active_key' => null,
                'completed_by' => $actor,
                'completed_at' => now(),
            ])->save();

            return [
                'movements' => $count,
                'coded_records' => $codedRecords->count(),
                'cleared_monthly_codes' => $clearedMonthlyCodes,
                'snapshot_file_name' => $fileName,
                'period_label' => $month->format('F Y'),
            ];
            });
        } catch (\Throwable $exception) {
            if ($snapshotPath !== null) {
                $fileStore->delete('local', $snapshotPath);
            }
            if ($previewPath !== null) {
                $fileStore->delete('local', $previewPath);
            }
            throw $exception;
        }
        $count = $result['movements'];
        $codedRecordCount = $result['coded_records'];
        $clearedMonthlyCodes = $result['cleared_monthly_codes'];

        return response()->json([
            'message' => 'Monthly '.$result['period_label'].' finalized.',
            'movements' => $count,
            'coded_records' => $codedRecordCount,
            'cleared_monthly_codes' => $clearedMonthlyCodes,
            'snapshot_file_name' => $result['snapshot_file_name'],
            'period_label' => $result['period_label'],
        ]);
    }

    public function index()
    {
        return view('forms.deliberation', [
            'monthlySession' => MonthlyMovementSession::query()->where('active_key', 'active')->first(),
            'puroks' => Purok::query()
                ->orderBy('purok_number')
                ->orderBy('grupo_start')
                ->orderBy('id')
                ->get(['id', 'purok_number', 'purok_name', 'grupo_start', 'grupo_end']),
        ]);
    }

    public function modifyIndex()
    {
        return view('forms.monthly-modify', [
            'monthlySession' => MonthlyMovementSession::query()->where('active_key', 'active')->first(),
            'puroks' => Purok::query()
                ->orderBy('purok_number')
                ->orderBy('grupo_start')
                ->orderBy('id')
                ->get(['id', 'purok_number', 'purok_name', 'grupo_start', 'grupo_end', 'full_purok']),
        ]);
    }

    public function modifySearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $query = mb_strtolower(trim($validated['q']));
        $records = Record::query()
            ->where(fn (Builder $builder) => $builder
                ->whereRaw('LOWER(full_name) LIKE ?', ['%'.$query.'%'])
                ->orWhereRaw('LOWER(registry) LIKE ?', ['%'.$query.'%']))
            ->orderBy('full_name')
            ->limit(10)
            ->get(['id', 'full_name', 'registry', 'status', 'purok', 'grupo', 'monthly_code', 'r2_10_endorsement']);

        return response()->json([
            'records' => $records->map(function (Record $record): array {
                $purokNumber = (int) preg_replace('/\D+/', '', (string) $record->purok);
                $purok = Purok::query()
                    ->where('purok_number', $purokNumber)
                    ->where(function ($query) use ($record): void {
                        $query->where('full_purok', true)
                            ->orWhere(function ($query) use ($record): void {
                                $query->where('grupo_start', '<=', (int) $record->grupo)
                                    ->where('grupo_end', '>=', (int) $record->grupo);
                            });
                    })
                    ->orderBy('id')
                    ->first();

                return [
                    'id' => $record->id,
                    'name' => $record->full_name,
                    'registry' => $record->registry,
                    'status' => $record->status,
                    'r2_10_endorsed' => $this->isR210Endorsed($record),
                    'purok' => (string) $record->purok,
                    'purok_name' => $purok?->purok_name ?: 'Purok '.$record->purok,
                    'grupo' => (string) $record->grupo,
                    'purok_id' => $purok?->id,
                    'monthly_code' => trim((string) $record->monthly_code),
                ];
            })->values(),
        ]);
    }

    public function modify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'record_id' => ['required', 'integer', 'exists:records,id'],
            'action' => ['required', Rule::in(['status', 'transfer'])],
            'status' => ['required_if:action,status', Rule::in(['MS', 'UWP', 'MK'])],
            'purok_id' => ['required_if:action,transfer', 'integer', 'exists:puroks,id'],
            'grupo' => ['required_if:action,transfer', 'integer', 'min:1', 'max:999'],
            'description' => ['nullable', 'string', 'max:500'],
            'endorsement_reason' => ['required_if:status,MK', 'nullable', 'string', 'max:2000'],
        ]);
        if ($validated['action'] === 'status' && (array_key_exists('purok_id', $validated) || array_key_exists('grupo', $validated))) {
            throw ValidationException::withMessages([
                'action' => ['Choose either a status change or a Purok transfer, not both.'],
            ]);
        }
        if ($validated['action'] === 'transfer' && array_key_exists('status', $validated)) {
            throw ValidationException::withMessages([
                'action' => ['Choose either a Purok transfer or a status change, not both.'],
            ]);
        }
        $destination = null;
        if ($validated['action'] === 'transfer') {
            $destination = Purok::query()->findOrFail($validated['purok_id']);
            $grupoMin = (int) $destination->grupo_start;
            $grupoMax = (int) $destination->grupo_end;
            if ((int) $validated['grupo'] < $grupoMin || (int) $validated['grupo'] > $grupoMax) {
                throw ValidationException::withMessages([
                    'grupo' => ["Grupo must be between {$grupoMin} and {$grupoMax} for {$destination->purok_name}."],
                ]);
            }
        }

        $record = DB::transaction(function () use ($validated, $destination, $request): Record {
            $record = Record::query()->lockForUpdate()->findOrFail($validated['record_id']);
            $this->ensureNoMonthlyCode($record);
            $session = $this->requireActiveMonthlySession($request);
            $previousValues = $this->movementState($record);
            $oldStatus = mb_strtoupper(trim((string) $record->status));
            if ($oldStatus === 'INACTIVE') {
                throw ValidationException::withMessages([
                    'record_id' => ['Inactive records can be searched but cannot be changed in Monthly Modify.'],
                ]);
            }
            if (! in_array($oldStatus, ['MS', 'UWP', 'MK'], true)) {
                throw ValidationException::withMessages([
                    'record_id' => ['Only MS, UWP, or MK records can be changed in Monthly Modify.'],
                ]);
            }

            if ($validated['action'] === 'status') {
                $newStatus = $validated['status'];
                if ($oldStatus === 'MK' && $newStatus !== 'MK') {
                    throw ValidationException::withMessages([
                        'status' => ['MK records cannot be changed to MS or UWP in Monthly Modify.'],
                    ]);
                }
                if ($oldStatus === $newStatus) {
                    throw ValidationException::withMessages([
                        'status' => ['Choose a status different from the member’s current status.'],
                    ]);
                }
                $this->ensureEndorsedRecordStaysOffMsUwpSwap($record, $oldStatus, $newStatus);

                $historyCode = $newStatus === 'MK' ? 'E' : 'G';
                $monthlyCode = $newStatus === 'MK' ? null : $newStatus.': B';
                $endorsementReason = '';
                if ($newStatus === 'MK') {
                    $endorsementReason = $this->normalizeDescription($validated['endorsement_reason'] ?? null);
                    if ($endorsementReason === '') {
                        throw ValidationException::withMessages([
                            'endorsement_reason' => ['Enter a reason for changing this member to MK.'],
                        ]);
                    }
                }
                $record->status = $newStatus;
                $record->monthly_code = $monthlyCode;
                $this->appendLogEntry(
                    $record,
                    "Monthly modify: status {$oldStatus} to {$newStatus} ({$oldStatus}: {$historyCode})"
                        .($monthlyCode === null ? '' : ", {$monthlyCode}"),
                    $validated['description'] ?? null
                );
                $currentReason = null;
                if ($newStatus === 'MK') {
                    // The column holds only the current reason; earlier ones stay in the reason history.
                    $currentReason = now()->timezone('Asia/Manila')->format('d-m-Y').': ('.$endorsementReason.')';
                    $record->reason_of_r2_10_endorsement = $currentReason;
                }
                $movement = $this->storeSessionMovement(
                    $session,
                    $record,
                    'status',
                    $previousValues,
                    "{$oldStatus} -> {$newStatus}",
                    $validated['description'] ?? $endorsementReason,
                    $request,
                    $oldStatus.': '.$historyCode
                );
                if ($currentReason !== null) {
                    // Linked to the movement so undoing it removes this reason again.
                    R210EndorsementReason::recordCurrent(
                        $record,
                        'MK',
                        $currentReason,
                        R210EndorsementReason::SOURCE_MONTHLY_MODIFY_MK,
                        $this->changedBy($request),
                        now(),
                        $session->id,
                        $movement->id
                    );
                }
                $record->save();
                $this->storeSessionActivity(
                    $session,
                    $movement,
                    $record,
                    'status',
                    'out',
                    $oldStatus,
                    $oldStatus.': '.$historyCode,
                    (string) ($previousValues['purok'] ?? ''),
                    (string) ($previousValues['grupo'] ?? ''),
                    $validated['description'] ?? $endorsementReason,
                    $request
                );
                if ($monthlyCode !== null) {
                    $this->storeSessionActivity(
                        $session,
                        $movement,
                        $record,
                        'status',
                        'in',
                        $newStatus,
                        $monthlyCode,
                        (string) $record->purok,
                        (string) $record->grupo,
                        $validated['description'] ?? null,
                        $request
                    );
                }

                return $record;
            }

            $oldPurok = trim((string) $record->purok) ?: 'Unassigned';
            $oldGrupo = trim((string) $record->grupo) ?: 'Unassigned';
            $oldPurokNumber = (int) preg_replace('/\D+/', '', $oldPurok);
            $source = Purok::query()
                ->where('purok_number', $oldPurokNumber)
                ->where(function ($query) use ($oldGrupo): void {
                    $query->where('full_purok', true)
                        ->orWhere(function ($query) use ($oldGrupo): void {
                            $query->where('grupo_start', '<=', (int) $oldGrupo)
                                ->where('grupo_end', '>=', (int) $oldGrupo);
                        });
                })
                ->orderBy('id')
                ->first();
            if (! $source) {
                throw ValidationException::withMessages([
                    'purok_id' => ['The member’s current Purok / Grupo does not match a configured Purok. Correct the record in Inquire / Modify before transferring.'],
                ]);
            }
            if (! $destination || $source->id === $destination->id) {
                throw ValidationException::withMessages([
                    'purok_id' => ['Monthly Modify is for transfers to a different configured Purok. For a Grupo-only correction, use Inquire / Modify.'],
                ]);
            }
            $oldPurokName = $source?->purok_name ?: 'Purok '.$oldPurok;
            $record->purok = (string) $destination->purok_number;
            $record->grupo = (string) $validated['grupo'];
            $record->monthly_code = $oldStatus.': C+';
            $this->appendLogEntry(
                $record,
                "Monthly modify: {$oldPurokName} Grupo {$oldGrupo} to {$destination->purok_name} Grupo {$validated['grupo']} ({$oldStatus}: C- / {$oldStatus}: C+)",
                $validated['description'] ?? null
            );
            $record->save();
            $movement = $this->storeSessionMovement(
                $session,
                $record,
                'transfer',
                $previousValues,
                "{$oldPurok}-{$oldGrupo} -> {$destination->purok_number}-{$validated['grupo']}",
                $validated['description'] ?? null,
                $request,
                $oldStatus.': C- / '.$oldStatus.': C+'
            );
            $this->storeSessionActivity(
                $session,
                $movement,
                $record,
                'transfer',
                'out',
                $oldStatus,
                $oldStatus.': C-',
                $oldPurok,
                $oldGrupo,
                $validated['description'] ?? null,
                $request
            );
            $this->storeSessionActivity(
                $session,
                $movement,
                $record,
                'transfer',
                'in',
                $oldStatus,
                $oldStatus.': C+',
                (string) $destination->purok_number,
                (string) $validated['grupo'],
                $validated['description'] ?? null,
                $request
            );

            return $record;
        });

        return response()->json([
            'message' => $validated['action'] === 'status'
                ? ($record->monthly_code
                    ? "Status updated. Current monthly code: {$record->monthly_code}; movement is in the active session."
                    : 'Status updated to MK. Movement is in the active session.')
                : "Purok and Grupo updated. Current monthly code: {$record->monthly_code}; movement is in the active session.",
            'record' => [
                'id' => $record->id,
                'name' => $record->full_name,
                'purok' => $record->purok,
                'grupo' => $record->grupo,
                'status' => $record->status,
                'monthly_code' => $record->monthly_code,
            ],
        ]);
    }

    public function duplicates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'registry' => ['nullable', 'string', 'max:13'],
            'is_rfid' => ['required', 'boolean'],
        ]);
        $rawRegistry = strtoupper(trim((string) ($validated['registry'] ?? '')));
        $registry = (bool) $validated['is_rfid']
            ? 'RFID'
            : (preg_match('/^[A-Z0-9]{13}$/', $rawRegistry) ? $rawRegistry : null);
        $matches = $this->findDuplicates(
            trim($validated['full_name']),
            $registry
        );

        return response()->json([
            'duplicates' => $matches->map(fn (Record $record): array => $this->duplicatePayload($record))->values(),
        ]);
    }

    public function addSuggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $search = mb_strtolower(trim($validated['q']));
        $query = Record::query()
            ->where(function (Builder $query) use ($search): void {
                $query->whereRaw('LOWER(full_name) LIKE ?', ['%'.$search.'%']);
                if (preg_match('/^[a-z]+$/', $search) && strlen($search) >= 3) {
                    $query->orWhereRaw('LOWER(full_name) LIKE ?', [substr($search, 0, 1).'%']);
                }
            })
            ->orderByRaw('CASE WHEN LOWER(full_name) LIKE ? THEN 0 ELSE 1 END', ['%'.$search.'%'])
            ->orderBy('full_name');
        $candidates = $query->limit(500)->get([
            'id',
            'full_name',
            'registry',
            'gender',
            'purok',
            'grupo',
            'kapisanan',
            'date_of_birth',
            'date_of_baptism',
            'evangelist',
            'is_married',
            'status',
            'monthly_code',
            'r2_10_endorsement',
        ]);
        $records = $candidates
            ->map(function (Record $record) use ($search): array {
                $name = mb_strtolower((string) $record->full_name);
                $exact = str_contains($name, $search);
                $distanceLimit = min(2, max(1, (int) floor(strlen($search) * 0.2)));
                $distance = $exact ? 0 : min(array_map(function (string $word) use ($search, $distanceLimit): int {
                    $wordLengths = range(
                        max(1, min(strlen($word), strlen($search) - $distanceLimit)),
                        min(strlen($word), strlen($search) + $distanceLimit)
                    );
                    $distances = array_map(
                        fn (int $length): int => levenshtein($search, substr($word, 0, $length)),
                        $wordLengths
                    );
                    $distances[] = levenshtein($search, $word);

                    return min($distances);
                }, preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name]));

                return [
                    'record' => $record,
                    'distance' => $distance,
                    'matches' => $exact || (
                        preg_match('/^[a-z]+$/', $search)
                        && strlen($search) >= 3
                        && $distance <= $distanceLimit
                    ),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['matches'])
            ->sort(function (array $left, array $right): int {
                $distance = $left['distance'] <=> $right['distance'];
                if ($distance !== 0) {
                    return $distance;
                }
                $inactive = (int) (mb_strtoupper(trim((string) $right['record']->status)) === 'INACTIVE')
                    <=> (int) (mb_strtoupper(trim((string) $left['record']->status)) === 'INACTIVE');

                return $inactive ?: strcasecmp((string) $left['record']->full_name, (string) $right['record']->full_name);
            })
            ->take(10)
            ->pluck('record')
            ->values();
        $puroks = Purok::query()
            ->whereIn('purok_number', $records->pluck('purok')->filter())
            ->orderBy('grupo_start')
            ->get(['id', 'purok_number', 'purok_name', 'grupo_start', 'grupo_end', 'full_purok'])
            ->groupBy(fn (Purok $purok): string => (string) $purok->purok_number);

        return response()->json([
            'records' => $records->map(function (Record $record) use ($puroks): array {
                $grupo = (int) $record->grupo;
                $purok = $puroks->get(trim((string) $record->purok), collect())
                    ->first(fn (Purok $candidate): bool => $candidate->full_purok
                        || ($grupo >= (int) $candidate->grupo_start && $grupo <= (int) $candidate->grupo_end));

                return [
                    'id' => $record->id,
                    'full_name' => $record->full_name,
                    'registry' => $record->registry,
                    'r2_10_endorsed' => $this->isR210Endorsed($record),
                    'gender' => match (mb_strtoupper(trim((string) $record->gender))) {
                        'L', 'MALE' => 'Male',
                        'B', 'FEMALE' => 'Female',
                        default => $record->gender,
                    },
                    'purok_id' => $purok?->id,
                    'purok' => trim((string) $record->purok),
                    'grupo' => trim((string) $record->grupo),
                    'kapisanan' => match (mb_strtolower(trim((string) $record->kapisanan))) {
                        'b', 'buklod' => 'Buklod',
                        'k', 'kadiwa' => 'Kadiwa',
                        's', 'suliranin' => 'Suliranin',
                        'binhi' => 'Binhi',
                        default => $record->kapisanan,
                    },
                    'date_of_birth' => $record->date_of_birth?->format('Y-m-d'),
                    'date_of_baptism' => $record->date_of_baptism?->format('Y-m-d'),
                    'evangelist' => $record->evangelist,
                    'is_married' => (bool) $record->is_married,
                    'status' => mb_strtoupper(trim((string) $record->status)),
                    'monthly_code' => trim((string) $record->monthly_code),
                ];
            })->values(),
        ]);
    }

    public function checkRegistry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registry' => ['required', 'string', 'size:13', 'regex:/^[A-Za-z0-9]{13}$/'],
            'is_rfid' => ['required', 'boolean'],
            'exclude_record_id' => ['nullable', 'integer', 'exists:records,id'],
        ]);

        if ($validated['is_rfid']) {
            return response()->json(['duplicates' => []]);
        }

        $registry = strtoupper(trim($validated['registry']));
        $duplicates = Record::query()
            ->whereRaw('UPPER(TRIM(registry)) = ?', [$registry])
            ->whereRaw("UPPER(TRIM(registry)) <> 'RFID'")
            ->when(
                isset($validated['exclude_record_id']),
                fn (Builder $query) => $query->where('id', '<>', $validated['exclude_record_id'])
            )
            ->orderBy('full_name')
            ->limit(10)
            ->get(['id', 'full_name', 'registry', 'status', 'purok', 'grupo']);

        return response()->json([
            'duplicates' => $duplicates->map(fn (Record $record): array => [
                'id' => $record->id,
                'full_name' => $record->full_name,
                'registry' => $record->registry,
                'status' => mb_strtoupper(trim((string) $record->status)),
                'purok' => trim((string) $record->purok),
                'grupo' => trim((string) $record->grupo),
            ])->values(),
        ]);
    }

    public function searchSubtractRecords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $search = mb_strtolower(trim($validated['q']));

        $records = Record::query()
            ->where(function (Builder $query) use ($search): void {
                $query->whereRaw('LOWER(full_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(registry) LIKE ?', ['%'.$search.'%']);
            })
            ->orderBy('full_name')
            ->limit(10)
            ->get([
                'id',
                'full_name',
                'registry',
                'gender',
                'purok',
                'grupo',
                'kapisanan',
                'date_of_birth',
                'date_of_baptism',
                'evangelist',
                'status',
                'monthly_code',
                'r2_10_endorsement',
            ]);
        $puroksByNumber = Purok::query()
            ->whereIn('purok_number', $records->pluck('purok')->filter())
            ->orderBy('grupo_start')
            ->get(['purok_number', 'purok_name', 'grupo_start', 'grupo_end', 'full_purok'])
            ->groupBy(fn (Purok $purok): string => (string) $purok->purok_number);

        return response()->json([
            'records' => $records->map(function (Record $record) use ($puroksByNumber): array {
                $purokSettings = $puroksByNumber->get(trim((string) $record->purok), collect());
                $grupo = (int) $record->grupo;
                $purokSetting = $purokSettings->first(fn (Purok $purok): bool => $purok->full_purok
                    || ($grupo >= (int) $purok->grupo_start && $grupo <= (int) $purok->grupo_end));

                return [
                    'id' => $record->id,
                    'full_name' => $record->full_name,
                    'registry' => $record->registry,
                    'r2_10_endorsed' => $this->isR210Endorsed($record),
                    'gender' => match (mb_strtoupper(trim((string) $record->gender))) {
                        'L', 'MALE' => 'Male',
                        'B', 'FEMALE' => 'Female',
                        default => $record->gender,
                    },
                    'purok' => trim((string) $record->purok),
                    'purok_name' => $purokSetting?->purok_name,
                    'grupo' => $record->grupo,
                    'kapisanan' => match (mb_strtolower(trim((string) $record->kapisanan))) {
                        'b', 'buklod' => 'Buklod',
                        'k', 'kadiwa' => 'Kadiwa',
                        default => $record->kapisanan,
                    },
                    'date_of_birth' => $record->date_of_birth?->format('Y-m-d'),
                    'date_of_baptism' => $record->date_of_baptism?->format('Y-m-d'),
                    'evangelist' => $record->evangelist,
                    'status' => mb_strtoupper(trim((string) $record->status)),
                    'monthly_code' => trim((string) $record->monthly_code),
                ];
            })->values(),
        ]);
    }

    public function subtract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'record_id' => ['required', 'integer', 'exists:records,id'],
            'code' => ['required', Rule::in(['D', 'DD', 'G', 'H'])],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($validated, $request): JsonResponse {
            $record = Record::query()->lockForUpdate()->findOrFail($validated['record_id']);
            $status = mb_strtoupper(trim((string) $record->status));

            if ($status === 'INACTIVE') {
                return response()->json(['message' => 'Already Inactive.'], 422);
            }
            $this->ensureNoMonthlyCode($record);
            if ($status === 'MK') {
                return response()->json(['message' => 'MK is not removable.'], 422);
            }
            if (! in_array($status, ['MS', 'UWP'], true)) {
                return response()->json(['message' => 'Only MS or UWP records can be marked inactive.'], 422);
            }
            if ($this->isR210Endorsed($record)) {
                return response()->json(['message' => self::ENDORSED_SUBTRACT_MESSAGE], 422);
            }

            $session = $this->requireActiveMonthlySession($request);
            $previousValues = $this->movementState($record);
            $description = $this->normalizeDescription($validated['description'] ?? null);
            // The subtract code and description are kept in log_section; the R2-10 reason is left alone.
            $record->monthly_code = $status.': '.$validated['code'];
            $record->status = 'INACTIVE';
            $this->appendLogEntry($record, $record->monthly_code, null, 'd-m-Y');
            if ($description !== '') {
                $this->appendLogEntry($record, $description, null, 'd-m-Y');
            }
            $record->save();
            $this->storeSessionActivity(
                $session,
                null,
                $record,
                'subtract',
                'out',
                $status,
                $record->monthly_code,
                (string) ($previousValues['purok'] ?? ''),
                (string) ($previousValues['grupo'] ?? ''),
                $description,
                $request,
                $previousValues,
                $this->movementState($record)
            );

            return response()->json([
                'message' => 'Record marked INACTIVE for this monthly session; its monthly code will be included in R4-01 and permanent history.',
                'record' => [
                    'id' => $record->id,
                    'full_name' => $record->full_name,
                    'status' => $record->status,
                    'monthly_code' => $record->monthly_code,
                ],
            ]);
        });
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'registry' => ['nullable', 'string', 'max:13'],
            'is_rfid' => ['required_without:reuse_record_id', 'nullable', 'boolean'],
            'gender' => ['required_without:reuse_record_id', 'nullable', Rule::in(['Male', 'Female'])],
            'purok_id' => ['required_without:reuse_record_id', 'nullable', 'integer', 'exists:puroks,id'],
            'grupo' => ['nullable', 'integer', 'min:1', 'max:999'],
            'kapisanan' => ['required_without:reuse_record_id', 'nullable', Rule::in(['Buklod', 'Kadiwa', 'Suliranin', 'Binhi'])],
            'date_of_baptism' => ['nullable', 'date', 'before_or_equal:today'],
            'evangelist' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(['MS', 'UWP', 'MK'])],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'is_married' => ['sometimes', 'required', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'reuse_record_id' => ['nullable', 'integer', 'exists:records,id'],
        ]);

        $name = mb_strtoupper(trim($validated['full_name']));
        $registry = isset($validated['reuse_record_id'])
            ? mb_strtoupper(trim((string) ($validated['registry'] ?? '')))
            : mb_strtoupper($this->normalizeRegistry($validated['registry'] ?? null, (bool) ($validated['is_rfid'] ?? false)));
        $grupo = $validated['grupo'] ?? null;
        $purok = isset($validated['purok_id']) ? Purok::query()->findOrFail($validated['purok_id']) : null;
        if ($purok && $grupo !== null && ((int) $grupo < $purok->grupo_start || (int) $grupo > $purok->grupo_end)) {
            throw ValidationException::withMessages([
                'grupo' => ["Grupo must be between {$purok->grupo_start} and {$purok->grupo_end} for {$purok->purok_name}."],
            ]);
        }

        $kapisanan = $this->deriveKapisanan(
            $validated['date_of_birth'] ?? null,
            (int) ($validated['is_married'] ?? 0)
        ) ?? ($validated['kapisanan'] ?? '');
        $isMarried = (int) ($validated['is_married'] ?? 0) === 1
            || in_array(mb_strtolower(trim($kapisanan)), ['b', 'buklod'], true);

        $description = $this->normalizeDescription($validated['description'] ?? null);
        $result = DB::transaction(function () use ($validated, $name, $registry, $purok, $kapisanan, $grupo, $description, $isMarried, $request): array {
            $duplicates = $this->findDuplicates($name, $registry, lockForUpdate: true);
            $reuseId = $validated['reuse_record_id'] ?? null;

            if ($reuseId !== null) {
                $record = Record::query()->lockForUpdate()->findOrFail($reuseId);
                if (! $duplicates->contains('id', $record->id)
                    || mb_strtoupper(trim((string) $record->status)) !== 'INACTIVE'
                    || trim((string) $record->monthly_code) !== '') {
                    throw ValidationException::withMessages([
                        'reuse_record_id' => ['Only a matching inactive record without a pending monthly code can be reactivated from Monthly Add.'],
                    ]);
                }

                $session = $this->requireActiveMonthlySession($request);
                $previousValues = $this->movementState($record);
                $record->full_name = mb_strtoupper(trim((string) $record->full_name));
                $record->registry = mb_strtoupper(trim((string) $record->registry));
                $record->status = $validated['status'];
                $record->monthly_code = $validated['status'].': B';
                $this->appendLogEntry($record, $record->monthly_code, null, 'd-m-Y');
                if ($description !== '') {
                    $this->appendLogEntry($record, $description, null, 'd-m-Y');
                }
                $record->save();
                $this->storeSessionActivity(
                    $session,
                    null,
                    $record,
                    'reactivate',
                    'in',
                    $record->status,
                    $record->monthly_code,
                    (string) $record->purok,
                    (string) $record->grupo,
                    $description,
                    $request,
                    $previousValues,
                    $this->movementState($record)
                );

                return [
                    'record' => $record,
                    'reused' => true,
                ];
            }

            if ($duplicates->isNotEmpty()) {
                $recordWithMonthlyCode = $duplicates->first(
                    fn (Record $duplicate): bool => trim((string) $duplicate->monthly_code) !== ''
                );
                if ($recordWithMonthlyCode) {
                    throw ValidationException::withMessages([
                        'full_name' => ['This person already has a monthly movement code. Clear it in Inquire / Modify before proceeding.'],
                    ]);
                }
                throw ValidationException::withMessages([
                    'full_name' => ['This person already has a record. Select an inactive match to reactivate it; active records cannot be added again.'],
                ]);
            }

            if (! $purok) {
                throw ValidationException::withMessages([
                    'purok_id' => ['Select a Purok before adding a new record.'],
                ]);
            }

            $record = Record::query()->create([
                'full_name' => $name,
                'registry' => $registry,
                'gender' => $validated['gender'] === 'Male' ? 'L' : 'B',
                'purok' => (string) $purok->purok_number,
                'grupo' => $grupo === null ? null : (string) $grupo,
                'kapisanan' => $this->storeKapisanan($kapisanan),
                'date_of_baptism' => $validated['date_of_baptism'] ?? null,
                'evangelist' => isset($validated['evangelist']) ? trim($validated['evangelist']) : null,
                'date_of_entry' => now()->timezone('Asia/Manila')->toDateString(),
                'status' => $validated['status'],
                'monthly_code' => $validated['status'].': B',
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'is_married' => $isMarried,
            ]);
            $session = $this->requireActiveMonthlySession($request);
            $this->appendLogEntry($record, $record->monthly_code, null, 'd-m-Y');
            if ($description !== '') {
                $this->appendLogEntry($record, $description, null, 'd-m-Y');
            }
            $record->save();
            $this->storeSessionActivity(
                $session,
                null,
                $record,
                'add',
                'in',
                $record->status,
                $record->monthly_code,
                (string) $record->purok,
                (string) $record->grupo,
                $description,
                $request,
                [
                    'reason_of_r2_10_endorsement' => null,
                    'log_section' => null,
                ],
                $this->movementState($record)
            );

            return [
                'record' => $record,
                'reused' => false,
            ];
        });

        return response()->json([
            'message' => $result['reused']
                ? "Existing inactive record reactivated to {$result['record']->status} with a monthly code for the active session."
                : 'Deliberation record added with a monthly code for the active session.',
            'reused' => $result['reused'],
            'record' => [
                'id' => $result['record']->id,
                'name' => $result['record']->full_name,
                'registry' => $result['record']->registry,
                'status' => $result['record']->status,
            ],
        ], $result['reused'] ? 200 : 201);
    }

    private function findDuplicates(string $name, ?string $registry, bool $lockForUpdate = false)
    {
        $query = Record::query()
            ->where(function ($query) use ($name, $registry): void {
                $query->whereRaw('LOWER(TRIM(full_name)) = ?', [mb_strtolower($name)]);
                if ($registry !== null && $registry !== 'RFID') {
                    $query->orWhereRaw('UPPER(TRIM(registry)) = ?', [$registry]);
                }
            })
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get(['id', 'full_name', 'registry', 'status']);
    }

    private function duplicatePayload(Record $record): array
    {
        return [
            'id' => $record->id,
            'name' => $record->full_name,
            'registry' => $record->registry,
            'status' => mb_strtoupper(trim((string) $record->status)),
            'monthly_code' => trim((string) $record->monthly_code),
            'inactive' => mb_strtoupper(trim((string) $record->status)) === 'INACTIVE',
        ];
    }

    private function normalizeRegistry(?string $registry, bool $isRfid): string
    {
        if ($isRfid) {
            return 'RFID';
        }

        $registry = strtoupper(trim((string) $registry));
        if (! preg_match('/^[A-Z0-9]{13}$/', $registry)) {
            throw ValidationException::withMessages([
                'registry' => ['Registry must contain exactly 13 letters or numbers, or select RFID.'],
            ]);
        }

        return $registry;
    }

    private function appendLogEntry(
        Record $record,
        string $action,
        ?string $description = null,
        string $dateFormat = 'd/m/Y'
    ): void {
        $existing = (string) $record->log_section;
        $description = $this->normalizeDescription($description);
        $entry = now()->timezone('Asia/Manila')->format($dateFormat).': '.$action
            .($description === '' ? '' : ': '.$description).',';
        $separator = $existing === ''
            ? ''
            : (preg_match('/,\s*$/u', $existing) === 1
                ? (preg_match('/,\s+$/u', $existing) === 1 ? '' : ' ')
                : ', ');
        $record->log_section = $existing.$separator.$entry;
    }

    private function normalizeDescription(?string $description): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', (string) $description) ?? '');
    }

    public const ENDORSED_SUBTRACT_MESSAGE = "You can't subtract this record and make it INACTIVE since it's already R2-10 endorsed.";

    public static function endorsedSwapMessage(string $fromStatus, string $toStatus): string
    {
        return "You can't change {$fromStatus} to {$toStatus} since it's already R2-10 endorsed. It can only move to MK.";
    }

    private function isR210Endorsed(Record $record): bool
    {
        return mb_strtoupper(trim((string) $record->r2_10_endorsement)) === 'ENDORSED';
    }

    /**
     * R2-10 endorsed members may leave MS or UWP only for MK in Monthly Session.
     */
    private function ensureEndorsedRecordStaysOffMsUwpSwap(Record $record, string $fromStatus, string $toStatus): void
    {
        $fromStatus = mb_strtoupper(trim($fromStatus));
        $toStatus = mb_strtoupper(trim($toStatus));
        if ($this->isR210Endorsed($record)
            && $fromStatus !== $toStatus
            && in_array($fromStatus, ['MS', 'UWP'], true)
            && in_array($toStatus, ['MS', 'UWP'], true)) {
            throw ValidationException::withMessages([
                'status' => [self::endorsedSwapMessage($fromStatus, $toStatus)],
            ]);
        }
    }

    private function ensureNoMonthlyCode(Record $record): void
    {
        if (trim((string) $record->monthly_code) !== '') {
            throw ValidationException::withMessages([
                'record_id' => ['This record already has a monthly movement code. Clear the previous code in Inquire / Modify before proceeding.'],
            ]);
        }
    }

    private function deriveKapisanan(?string $birthDate, int $isMarried): ?string
    {
        if ($birthDate === null) {
            return null;
        }
        if ($isMarried === 1) {
            return 'Buklod';
        }

        return Carbon::parse($birthDate)->age < 18 ? 'Binhi' : 'Kadiwa';
    }

    private function activeMonthlySession(Request $request, bool $create): ?MonthlyMovementSession
    {
        $session = MonthlyMovementSession::query()
            ->where('active_key', 'active')
            ->lockForUpdate()
            ->first();
        if ($session || ! $create) {
            return $session;
        }

        $periodMonth = $this->targetPeriodMonth();
        $existing = MonthlyMovementSession::query()
            ->whereDate('period_month', $periodMonth)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'session' => [$this->periodTakenMessage($existing)],
            ]);
        }

        return MonthlyMovementSession::query()->create([
            'active_key' => 'active',
            'status' => 'active',
            'period_month' => $periodMonth->toDateString(),
            'started_by' => $this->changedBy($request),
            'started_at' => now(),
        ]);
    }

    /**
     * The period a session started right now belongs to: from the 15th (Manila
     * time) onward the session is for the following month.
     */
    private function targetPeriodMonth(): Carbon
    {
        $now = Carbon::now('Asia/Manila');
        $periodMonth = $now->copy()->startOfMonth();
        if ($now->day >= 15) {
            $periodMonth->addMonthNoOverflow();
        }

        return $periodMonth;
    }

    private function sessionStartAvailability(): array
    {
        $targetMonth = $this->targetPeriodMonth();
        $existing = MonthlyMovementSession::query()
            ->whereDate('period_month', $targetMonth)
            ->orderBy('id')
            ->first();
        if ($existing) {
            return [
                'allowed' => false,
                'target_month' => null,
                'existing_period' => $existing->period_month?->format('F Y'),
                'warning' => null,
                'message' => $this->periodTakenMessage($existing),
            ];
        }

        $warning = $this->skippedPeriodWarning($targetMonth);

        return [
            'allowed' => true,
            'target_month' => $targetMonth->format('F Y'),
            'existing_period' => null,
            'warning' => $warning,
            'message' => "Monthly Session available right now for {$targetMonth->format('F Y')}!"
                .($warning === null ? '' : ' '.$warning),
        ];
    }

    private function periodTakenMessage(MonthlyMovementSession $existing): string
    {
        $period = $existing->period_month?->format('F Y') ?? 'this period';
        $state = $existing->active_key === 'active' ? 'is already active' : 'already exists';

        return "A Monthly Session for {$period} {$state}. Only one session is allowed per period month.";
    }

    /**
     * Warn when starting $targetMonth would leave earlier months without a
     * session (for example the last session was October and the target is
     * December). Nothing is created automatically.
     */
    private function skippedPeriodWarning(Carbon $targetMonth): ?string
    {
        $latest = MonthlyMovementSession::query()
            ->whereDate('period_month', '<', $targetMonth->toDateString())
            ->max('period_month');
        if ($latest === null) {
            return null;
        }

        $skipped = [];
        $cursor = Carbon::parse($latest)->startOfMonth()->addMonthNoOverflow();
        while ($cursor->lt($targetMonth->copy()->startOfMonth()) && count($skipped) < 24) {
            $skipped[] = $cursor->format('F Y');
            $cursor->addMonthNoOverflow();
        }
        if ($skipped === []) {
            return null;
        }

        return 'Warning: no Monthly Session exists for '.implode(', ', $skipped)
            .'. Starting now creates the '.$targetMonth->format('F Y').' session and skips '
            .(count($skipped) === 1 ? 'that month' : 'those months').'.';
    }

    private function requireActiveMonthlySession(Request $request): MonthlyMovementSession
    {
        $session = $this->activeMonthlySession($request, false);
        if (! $session) {
            throw ValidationException::withMessages([
                'session' => ['Start a monthly session before adding, subtracting, or modifying records.'],
            ]);
        }

        return $session;
    }

    private function changedBy(Request $request): ?string
    {
        $gmail = trim((string) $request->session()->get('login_user.gmail', ''));

        return $gmail !== '' ? $gmail : null;
    }

    private function movementState(Record $record): array
    {
        return collect(self::MOVEMENT_STATE_FIELDS)
            ->mapWithKeys(fn (string $field): array => [
                $field => $record->{$field} instanceof \DateTimeInterface
                    ? $record->{$field}->format('Y-m-d')
                    : $record->{$field},
            ])
            ->all();
    }

    private function monthlyCodeParts(string $monthlyCode, string $fallbackStatus = ''): array
    {
        $parts = explode(':', trim($monthlyCode), 2);
        if (count($parts) < 2) {
            return [mb_strtoupper(trim($fallbackStatus)), mb_strtoupper(trim($monthlyCode, " \t\n\r\0\x0B\"'"))];
        }

        $status = mb_strtoupper(trim($parts[0]));
        if (! in_array($status, ['MS', 'UWP', 'MK'], true)) {
            $status = mb_strtoupper(trim($fallbackStatus));
        }
        $code = mb_strtoupper(trim(explode('/', $parts[1], 2)[0], " \t\n\r\0\x0B\"'"));

        return [$status, $code];
    }

    private function storeSessionMovement(
        MonthlyMovementSession $session,
        Record $record,
        string $action,
        array $previousValues,
        string $destinationDescription,
        ?string $description,
        Request $request,
        ?string $monthlyCode
    ): MonthlySessionMovement {
        return MonthlySessionMovement::query()->create([
            'session_id' => $session->id,
            'record_id' => $record->id,
            'action' => $action,
            'full_name' => trim((string) $record->full_name) ?: 'Unnamed record',
            'registry' => trim((string) $record->registry) ?: null,
            'gender' => trim((string) $record->gender) ?: null,
            'kapisanan' => trim((string) $record->kapisanan) ?: null,
            'date_of_baptism' => $record->date_of_baptism?->format('Y-m-d'),
            'evangelist' => trim((string) $record->evangelist) ?: null,
            'status' => $previousValues['status'] ?? null,
            'monthly_code' => $monthlyCode,
            'previous_purok' => $previousValues['purok'] ?? null,
            'previous_grupo' => $previousValues['grupo'] ?? null,
            'destination_description' => $destinationDescription,
            'previous_values' => $previousValues,
            'next_values' => $this->movementState($record),
            'description' => $this->normalizeDescription($description) ?: null,
            'changed_by' => $this->changedBy($request),
            'changed_at' => now(),
        ]);
    }

    private function storeSessionActivity(
        MonthlyMovementSession $session,
        ?MonthlySessionMovement $movement,
        Record $record,
        string $action,
        string $direction,
        string $status,
        string $monthlyCode,
        string $purok,
        string $grupo,
        ?string $description,
        Request $request,
        ?array $previousValues = null,
        ?array $nextValues = null
    ): void {
        MonthlySessionActivity::query()->create([
            'session_id' => $session->id,
            'session_movement_id' => $movement?->id,
            'record_id' => $record->id,
            'action' => $action,
            'direction' => $direction,
            'full_name' => trim((string) $record->full_name) ?: 'Unnamed record',
            'registry' => trim((string) $record->registry) ?: null,
            'gender' => trim((string) $record->gender) ?: null,
            'kapisanan' => trim((string) $record->kapisanan) ?: null,
            'date_of_baptism' => $record->date_of_baptism?->format('Y-m-d'),
            'evangelist' => trim((string) $record->evangelist) ?: null,
            'status' => $status,
            'monthly_code' => $monthlyCode,
            'purok' => trim($purok) ?: null,
            'grupo' => trim($grupo) ?: null,
            'description' => $this->normalizeDescription($description) ?: null,
            'changed_by' => $this->changedBy($request),
            'changed_at' => now(),
            'previous_values' => $previousValues,
            'next_values' => $nextValues,
        ]);
    }

    private function sessionPayload(MonthlyMovementSession $session): array
    {
        return [
            'id' => $session->id,
            'started_by' => $session->started_by,
            'started_at' => $session->started_at?->toIso8601String(),
            'period_month' => $session->period_month?->format('F Y'),
            'movement_count' => $session->movements()->count(),
            'activity_count' => $session->activities()
                ->whereNull('session_movement_id')
                ->count()
                + $session->activities()->whereNotNull('session_movement_id')
                    ->distinct('session_movement_id')
                    ->count('session_movement_id'),
        ];
    }

    private function snapshotSession(MonthlyMovementSession $session, $movements): void
    {
        $month = $session->period_month->startOfMonth()->toDateString();
        $puroks = Purok::query()
            ->orderBy('purok_number')
            ->orderBy('grupo_start')
            ->get(['id', 'purok_number', 'grupo_start', 'grupo_end', 'full_purok']);
        $records = Record::query()->get(['purok', 'grupo', 'status', 'monthly_code']);
        $purokNumbers = $puroks->pluck('purok_number')
            ->merge($records->pluck('purok')->filter()->map(fn ($number): int => (int) preg_replace('/\D+/', '', (string) $number)))
            ->merge(collect($movements)->flatMap(fn (MonthlySessionMovement $movement): array => [
                $movement->previous_values['purok'] ?? null,
                $movement->next_values['purok'] ?? null,
            ])->filter()->map(fn ($number): int => (int) preg_replace('/\D+/', '', (string) $number)))
            ->filter(fn ($number): bool => (int) $number > 0)
            ->unique()
            ->sort()
            ->values();
        $movementCountsBySegment = [];

        $segmentForLocation = static function (mixed $purok, mixed $grupo) use ($puroks): ?Purok {
            $number = (int) preg_replace('/\D+/', '', (string) $purok);
            $groupNumber = (int) preg_replace('/\D+/', '', (string) $grupo);

            return $puroks->first(function (Purok $candidate) use ($number, $groupNumber): bool {
                if ((int) $candidate->purok_number !== $number) {
                    return false;
                }
                $start = $candidate->full_purok ? 1 : (int) $candidate->grupo_start;
                $end = $candidate->full_purok ? PHP_INT_MAX : (int) $candidate->grupo_end;

                return $groupNumber >= $start && $groupNumber <= $end;
            });
        };
        $increment = function (mixed $purok, mixed $grupo, mixed $status, string $code) use (&$movementCountsBySegment, $segmentForLocation): void {
            $number = (int) preg_replace('/\D+/', '', (string) $purok);
            $status = mb_strtoupper(trim((string) $status));
            if ($number < 1 || ! in_array($status, ['MS', 'UWP'], true)) {
                return;
            }
            $segment = $segmentForLocation($purok, $grupo);
            $segmentId = $segment?->id !== null ? (string) $segment->id : 'fallback';
            $movementCountsBySegment[$segmentId][$status][$code] =
                ($movementCountsBySegment[$segmentId][$status][$code] ?? 0) + 1;
        };

        foreach ($movements as $movement) {
            $previous = $movement->previous_values ?? [];
            $next = $movement->next_values ?? [];
            [$movementStatus, $code] = $this->monthlyCodeParts(
                (string) $movement->monthly_code,
                mb_strtoupper(trim((string) $movement->status))
            );
            if ($movement->action === 'add') {
                $increment($next['purok'] ?? null, $next['grupo'] ?? null, $movementStatus ?: ($next['status'] ?? null), 'B');
            } elseif ($movement->action === 'subtract') {
                $increment($previous['purok'] ?? null, $previous['grupo'] ?? null, $movementStatus ?: ($previous['status'] ?? null), $code);
            } elseif ($movement->action === 'transfer') {
                $increment($previous['purok'] ?? null, $previous['grupo'] ?? null, $movementStatus ?: ($previous['status'] ?? null), 'C-');
                $increment($next['purok'] ?? null, $next['grupo'] ?? null, $movementStatus ?: ($previous['status'] ?? null), 'C+');
            } elseif ($movement->action === 'status') {
                $oldStatus = mb_strtoupper(trim((string) ($previous['status'] ?? '')));
                $newStatus = mb_strtoupper(trim((string) ($next['status'] ?? '')));
                $increment($previous['purok'] ?? null, $previous['grupo'] ?? null, $oldStatus, $newStatus === 'MK' ? 'E' : 'G');
                if ($newStatus !== 'MK') {
                    $increment($next['purok'] ?? null, $next['grupo'] ?? null, $newStatus, 'B');
                }
            }
        }

        $movementRecordIds = collect($movements)->pluck('record_id')->filter()->unique()->all();
        $codedRecords = Record::query()
            ->whereNotNull('monthly_code')
            ->whereRaw("TRIM(monthly_code) <> ''")
            ->when($movementRecordIds !== [], fn ($query) => $query->whereNotIn('id', $movementRecordIds))
            ->get(['purok', 'grupo', 'status', 'monthly_code']);
        foreach ($codedRecords as $record) {
            [$codeStatus, $code] = $this->monthlyCodeParts(
                (string) $record->monthly_code,
                mb_strtoupper(trim((string) $record->status))
            );
            $increment($record->purok, $record->grupo, $codeStatus ?: (string) $record->status, $code);
        }

        foreach ($purokNumbers as $purokNumber) {
            $numberSegments = $puroks->where('purok_number', $purokNumber)->values();
            if ($numberSegments->isEmpty()) {
                $numberSegments = collect([null]);
            }
            $existingSnapshots = MonthlyRecordSnapshot::query()
                ->whereDate('month', $month)
                ->where('purok_number', $purokNumber)
                ->get()
                ->keyBy(fn (MonthlyRecordSnapshot $snapshot): string => $snapshot->purok_id === null
                    ? 'fallback'
                    : (string) $snapshot->purok_id);
            if ($existingSnapshots->contains(fn (MonthlyRecordSnapshot $snapshot): bool => ! $snapshot->session_generated)) {
                continue;
            }

            foreach ($numberSegments as $segment) {
                $segmentId = $segment?->id !== null ? (string) $segment->id : 'fallback';
                $start = $segment === null ? 1 : ($segment->full_purok ? 1 : (int) $segment->grupo_start);
                $end = $segment === null ? PHP_INT_MAX : ($segment->full_purok ? PHP_INT_MAX : (int) $segment->grupo_end);
                $members = $records->filter(fn (Record $record): bool =>
                    (int) preg_replace('/\D+/', '', (string) $record->purok) === (int) $purokNumber
                    && (int) preg_replace('/\D+/', '', (string) $record->grupo) >= $start
                    && (int) preg_replace('/\D+/', '', (string) $record->grupo) <= $end
                );
                $counts = $members->countBy(fn (Record $record): string => mb_strtoupper(trim((string) $record->status)));
                $snapshot = $existingSnapshots->get($segmentId);
                $movementCounts = $snapshot?->movement_counts ?? [];
                foreach ($movementCountsBySegment[$segmentId] ?? [] as $status => $codes) {
                    foreach ($codes as $code => $quantity) {
                        $movementCounts[$status][$code] = ($movementCounts[$status][$code] ?? 0) + $quantity;
                    }
                }
                DB::table('monthly_record_snapshots')->updateOrInsert(
                    ['month' => $month, 'purok_id' => $segment?->id],
                    [
                        'purok_number' => (int) $purokNumber,
                        'ms_count' => $counts->get('MS', 0),
                        'uwp_count' => $counts->get('UWP', 0),
                        'mk_count' => $counts->get('MK', 0),
                        'movement_counts' => json_encode($movementCounts, JSON_THROW_ON_ERROR),
                        'created_at' => $snapshot?->created_at ?? now(),
                        'updated_at' => now(),
                        'session_generated' => true,
                    ]
                );
            }
        }
    }

    private function storeKapisanan(string $kapisanan): string
    {
        return match ($kapisanan) {
            'Buklod' => 'B',
            'Kadiwa' => 'K',
            'Binhi' => 'binhi',
            default => $kapisanan,
        };
    }
}
