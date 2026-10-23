<?php

namespace App\Http\Controllers;

use App\Models\GlobalVariable;
use App\Models\MonthlyMovementSession;
use App\Models\Purok;
use App\Models\R401Snapshot;
use App\Models\Record;
use App\Services\MonthlyResumenReport;
use App\Services\PurokSelection;
use App\Services\R401DocxExporter;
use App\Services\R401ReportBuilder;
use App\Services\ResumenDocxExporter;
use App\Support\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FormGeneratorController extends Controller
{
    public function __construct(private readonly R401ReportBuilder $r401ReportBuilder) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'panel' => ['nullable', 'in:weekly,monthly,notes'],
            'monthly_section' => ['nullable', 'in:deliberation,monthly-forms'],
        ]);

        return view('form-generator', [
            'activePanel' => $validated['panel'] ?? 'weekly',
            'activeMonthlyPanel' => $validated['monthly_section'] ?? 'deliberation',
            'monthlySession' => MonthlyMovementSession::query()
                ->where('active_key', 'active')
                ->withCount('activities')
                ->first(),
        ]);
    }

    public function r401(Request $request)
    {
        $data = $this->r401ReportData($request);

        return view('forms.monthly-r4-01', $data);
    }

    public function monthlyGenerators(Request $request, MonthlyResumenReport $resumenReport)
    {
        $resumenData = $this->resumenReportData($resumenReport);
        $r401Data = $this->r401ReportData($request);

        return view('forms.monthly-generators', array_merge($resumenData, $r401Data, [
            'embedded' => true,
            'monthlySession' => MonthlyMovementSession::query()
                ->where('active_key', 'active')
                ->withCount('activities')
                ->first(),
        ]));
    }

    public function resumen(Request $request, MonthlyResumenReport $resumenReport)
    {
        $data = $this->resumenReportData($resumenReport);

        return view('forms.monthly-resumen', $data);
    }

    public function downloadResumen(
        MonthlyResumenReport $resumenReport,
        ResumenDocxExporter $exporter
    ): BinaryFileResponse {
        $data = $this->resumenReportData($resumenReport);
        $generatedPath = $exporter->generate(
            $data['report'],
            $data['locale'],
            $data['lcode'],
            $data['signatories']
        );
        $fileName = 'Resumen '.$data['report']['month']->format('M Y').'.docx';

        return response()->download($generatedPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function downloadR401(Request $request, R401DocxExporter $exporter): BinaryFileResponse
    {
        $request->validate([
            'purok' => ['required', 'string', 'max:'.PurokSelection::MAX_LENGTH],
            'kalagayan' => ['required', 'string', 'in:MS,UWP'],
        ]);
        $data = $this->r401ReportData($request);
        abort_if($data['reportRows'] === [], 404, 'No records match the selected Purok and Kalagayan.');
        $globalVariables = $data['globalVariables'];
        $kalihim = $data['kalihimName'];
        // r401ReportData() already filled each page's Kalihim (segment secretary or the global fallback).
        $reportPages = $data['reportPages'];
        $path = $exporter->generate([
            'district' => $globalVariables?->district,
            'dcode' => $globalVariables?->dcode,
            'locale' => $data['locale'],
            'lcode' => $data['lcode'],
            'month' => $data['month']->format('F'),
            'year' => $data['month']->format('Y'),
            'kalagayan' => $data['selectedStatus'],
            'signatures' => [
                $kalihim,
                $globalVariables?->ksp_pd,
                '',
                $globalVariables?->pastor_destinado,
            ],
        ], $reportPages);

        $fileName = 'R4-01 ('.$data['selectedStatus'].') '.$data['month']->format('M Y').'.docx';

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function r401Snapshots(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'digits:4'],
        ]);
        $years = R401Snapshot::query()
            ->where('report_type', 'Monthly session')
            ->pluck('period_month')
            ->map(fn ($month): string => Carbon::parse($month)->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();
        $snapshots = R401Snapshot::query()
            ->where('report_type', 'Monthly session')
            ->when(isset($filters['year']), fn ($query) => $query->whereYear('period_month', $filters['year']))
            ->when(isset($filters['month']), fn ($query) => $query->whereYear('period_month', substr($filters['month'], 0, 4))
                ->whereMonth('period_month', substr($filters['month'], 5, 2)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $fileStore = app(\App\Services\FileStore::class);
        foreach ($snapshots as $snapshot) {
            // Disk file or database copy; the list shows a "file missing" badge otherwise.
            $snapshot->file_available = is_string($snapshot->file_path) && $snapshot->file_path !== ''
                && $fileStore->exists('local', $snapshot->file_path);
        }

        return view('forms.monthly-r4-01-snapshots', [
            'snapshots' => $snapshots,
            'filters' => $filters,
            'years' => $years,
            'canDeleteSnapshots' => Role::isHeadSecretary((string) $request->session()->get('login_user.tungkulin', '')),
        ]);
    }

    public function viewR401Snapshot(R401Snapshot $snapshot)
    {
        abort_unless($snapshot->report_type === 'Monthly session', 404);
        abort_unless($snapshot->format === 'docx', 404);
        $previewPath = preg_replace('/\.docx$/i', '.html', (string) $snapshot->file_path);

        // Reads the disk file, or the stored_files copy after a restore on another PC.
        $response = is_string($previewPath) && $previewPath !== ''
            ? app(\App\Services\FileStore::class)->response(
                'local',
                $previewPath,
                (string) preg_replace('/\.docx$/i', '.html', (string) $snapshot->file_name),
                'text/html; charset=UTF-8',
                'inline',
            )
            : null;

        return $response ?? response(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Snapshot file missing</title></head>'
            .'<body style="font-family:sans-serif;padding:1.5rem;color:#333">'
            .'<p>The saved file for this snapshot is not on this server. It may have been removed, or this database was restored without its files.</p>'
            .'</body></html>',
            404,
            ['Content-Type' => 'text/html; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function r401SnapshotSummary(R401Snapshot $snapshot): JsonResponse
    {
        abort_unless($snapshot->report_type === 'Monthly session', 404);

        $history = $snapshot->session_id === null
            ? collect()
            : DB::table('permanent_history')
                ->where('session_id', $snapshot->session_id)
                ->orderBy('id')
                ->get();
        $incomingCodes = [];
        foreach ($history as $entry) {
            [$status, $code] = $this->snapshotMonthlyCodeParts((string) $entry->monthly_code, (string) $entry->status);
            if ($entry->action === 'add' && $code === 'B' && $entry->record_id !== null) {
                $incomingCodes[(int) $entry->record_id][$status] = $code;
            }
        }

        $pairedIncomingCodes = [];
        foreach ($history as $entry) {
            if ($entry->action !== 'status' || $entry->record_id === null) {
                continue;
            }
            $next = json_decode((string) ($entry->next_values ?? ''), true) ?: [];
            $nextStatus = mb_strtoupper(trim((string) ($next['status'] ?? '')));
            $recordId = (int) $entry->record_id;
            if ($nextStatus !== '' && isset($incomingCodes[$recordId][$nextStatus])) {
                $pairedIncomingCodes[$recordId][$nextStatus] = true;
            }
        }

        $entries = [];
        $seenEvents = [];
        foreach ($history as $entry) {
            [$status, $code] = $this->snapshotMonthlyCodeParts((string) $entry->monthly_code, (string) $entry->status);
            $previous = json_decode((string) ($entry->previous_values ?? ''), true) ?: [];
            $next = json_decode((string) ($entry->next_values ?? ''), true) ?: [];
            $name = trim((string) $entry->full_name) ?: 'Unnamed record';
            $summary = null;
            $signature = null;

            if ($entry->action === 'transfer') {
                $from = $this->snapshotLocation($previous);
                $to = $this->snapshotLocation($next);
                $summary = "transfer {$from} (Code: C-) -> {$to} (Code: C+)";
                $signature = implode('|', [$entry->record_id, 'transfer', $from, $to]);
            } elseif ($entry->action === 'status') {
                $previousStatus = mb_strtoupper(trim((string) ($previous['status'] ?? $entry->status)));
                $nextStatus = mb_strtoupper(trim((string) ($next['status'] ?? '')));
                if ($previousStatus !== '' && $nextStatus !== '' && $previousStatus !== $nextStatus) {
                    $incomingCode = $incomingCodes[(int) $entry->record_id][$nextStatus] ?? null;
                    $oldCode = $code ?: 'A';
                    $summary = "transfer: {$previousStatus} (Code: {$oldCode}) -> {$nextStatus}"
                        .($incomingCode ? " (Code: {$incomingCode})" : '');
                    $signature = implode('|', [$entry->record_id, 'status', $previousStatus, $nextStatus, $oldCode]);
                }
            } elseif ($entry->action === 'add') {
                if ($code !== 'B' || isset($pairedIncomingCodes[(int) $entry->record_id][$status])) {
                    continue;
                }
                $summary = "in: {$status} (Code: B)";
                $signature = implode('|', [$entry->record_id, 'add', $status, $this->snapshotLocation($next)]);
            } elseif ($entry->action === 'subtract') {
                $summary = "out: {$status} (Code: {$code})";
                $signature = implode('|', [$entry->record_id, 'subtract', $status, $code, $this->snapshotLocation($previous)]);
            }

            if ($summary === null || ($signature !== null && isset($seenEvents[$signature]))) {
                continue;
            }
            if ($signature !== null) {
                $seenEvents[$signature] = true;
            }
            $description = trim((string) $entry->description);
            $entries[] = [
                'name' => $name,
                'summary' => $summary.($description !== '' ? ' — '.$description : ''),
            ];
        }

        return response()->json([
            'period' => $snapshot->period_month->format('F Y'),
            'entries' => $entries,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function snapshotMonthlyCodeParts(string $monthlyCode, string $fallbackStatus): array
    {
        $parts = explode(':', trim($monthlyCode), 2);
        $status = mb_strtoupper(trim($parts[0] ?? $fallbackStatus));
        if (! in_array($status, ['MS', 'UWP', 'MK'], true)) {
            $status = mb_strtoupper(trim($fallbackStatus));
        }
        preg_match('/(C\+|C-|DD|B|D|E|G|H|A)(?![A-Z])/i', $parts[1] ?? $monthlyCode, $match);

        return [$status, mb_strtoupper($match[1] ?? '')];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function snapshotLocation(array $values): string
    {
        $purok = trim((string) ($values['purok'] ?? ''));
        $grupo = trim((string) ($values['grupo'] ?? ''));

        return $purok === '' && $grupo === '' ? 'Unassigned' : ($purok ?: 'Unassigned').'-'.($grupo ?: 'Unassigned');
    }

    public function deleteR401Snapshot(Request $request, R401Snapshot $snapshot)
    {
        $request->validate([
            'acknowledge_permanent_deletion' => ['required', 'accepted'],
            'confirmation_phrase' => ['required', 'string', Rule::in(['SUPERADMIN'])],
        ], [
            'acknowledge_permanent_deletion.accepted' => 'Acknowledge that this deletion is permanent before proceeding.',
            'confirmation_phrase.in' => 'Type SUPERADMIN exactly to permanently delete this monthly snapshot.',
        ]);
        abort_unless($snapshot->report_type === 'Monthly session', 404);

        $paths = array_filter([
            (string) $snapshot->file_path,
            (string) preg_replace('/\.docx$/i', '.html', (string) $snapshot->file_path),
        ]);
        $snapshot->delete();
        // Removes the disk files and their stored_files copies.
        $fileStore = app(\App\Services\FileStore::class);
        foreach (array_unique($paths) as $path) {
            $fileStore->delete('local', $path);
        }

        return redirect()
            ->route('forms.monthly.r4-01.snapshots')
            ->with('status', 'Monthly snapshot deleted.');
    }

    public function downloadR401Snapshot(R401Snapshot $snapshot)
    {
        abort_unless($snapshot->report_type === 'Monthly session', 404);
        abort_unless($snapshot->format === 'docx', 404);

        $response = filled($snapshot->file_path)
            ? app(\App\Services\FileStore::class)->response(
                'local',
                (string) $snapshot->file_path,
                (string) $snapshot->file_name,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'attachment',
            )
            : null;

        return $response ?? redirect()
            ->route('forms.monthly.r4-01.snapshots')
            ->with('error', 'The saved file for "'.$snapshot->file_name.'" is not on this server, so it cannot be downloaded.');
    }

    private function resumenReportData(MonthlyResumenReport $resumenReport): array
    {
        $defaultMonth = MonthlyMovementSession::query()
            ->whereNotNull('period_month')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->value('period_month')
            ?? now()->startOfMonth()->toDateString();
        $selectedMonth = Carbon::parse($defaultMonth)->format('Y-m');
        $report = $resumenReport->generate(Carbon::createFromFormat('Y-m', $selectedMonth));
        $globalVariables = GlobalVariable::query()->first();

        return [
            'report' => $report,
            'selectedMonth' => $selectedMonth,
            'locale' => trim((string) ($globalVariables?->locale ?? '')),
            'lcode' => trim((string) ($globalVariables?->lcode ?? '')),
            'signatories' => [
                'head_lsp' => trim((string) ($globalVariables?->head_lsp ?? '')),
                'ksp_pd' => trim((string) ($globalVariables?->ksp_pd ?? '')),
                'pastor_destinado' => trim((string) ($globalVariables?->pastor_destinado ?? '')),
            ],
        ];
    }

    private function r401ReportData(Request $request): array
    {
        $validated = $request->validate([
            'purok' => ['nullable', 'string', 'max:'.PurokSelection::MAX_LENGTH],
            'kalagayan' => ['nullable', 'string', 'in:MS,UWP'],
        ]);
        // Tokens: "all", "N" (every segment of purok number N) or "N:ID" (one segment, e.g. Purok 2a), comma separated.
        $selection = isset($validated['purok'])
            ? PurokSelection::fromRequestValue($validated['purok'])
            : null;
        $session = MonthlyMovementSession::query()
            ->whereNotNull('period_month')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->first();
        $month = Carbon::parse($session?->period_month ?? now())->startOfMonth();
        $puroks = Purok::selectionOptions();
        $selectedPurok = $validated['purok'] ?? null;
        $selectedStatus = $validated['kalagayan'] ?? null;
        $report = $selection !== null && $selectedStatus !== null
            ? $this->r401ReportBuilder->build($selection, $selectedStatus, $session)
            : ['rows' => [], 'pages' => [], 'outside_range' => []];
        $globalVariables = GlobalVariable::query()->first();
        $usePurokSecretary = (bool) ($globalVariables?->r401_use_purok_secretary ?? false);
        $globalKalihimName = $this->r401ReportBuilder->globalKalihimName($globalVariables, $selectedStatus);
        $reportPages = $this->r401ReportBuilder->withKalihimNames($report['pages'], $globalVariables, $selectedStatus);

        return [
            'month' => $month,
            'reportRows' => $report['rows'],
            'reportPages' => $reportPages,
            'reportWarnings' => $this->r401ReportBuilder->outsideRangeWarnings($report['outside_range']),
            'puroks' => $puroks,
            'selectedPurok' => $selectedPurok,
            'selectedStatus' => $selectedStatus,
            'globalVariables' => $globalVariables,
            'locale' => $globalVariables?->locale ?? '',
            'lcode' => $globalVariables?->lcode ?? '',
            'dcode' => $globalVariables?->dcode ?? '',
            'district' => $globalVariables?->district ?? '',
            'kalihimName' => $globalKalihimName,
            'usePurokSecretary' => $usePurokSecretary,
        ];
    }

    public function notes(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $term = trim((string) ($validated['q'] ?? ''));

        $records = Record::query()
            ->whereNotNull('log_section')
            ->where('log_section', '!=', '')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('full_name', 'like', '%'.$term.'%')
                        ->orWhere('registry', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('full_name')
            ->limit(100)
            ->get(['id', 'full_name', 'registry', 'log_section']);

        return response()->json($records->map(fn (Record $record): array => $this->recordPayload($record)));
    }

    public function appendNote(Request $request, Record $record): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $note = trim($validated['note']);
        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Enter a note before saving.']);
        }

        $record = DB::transaction(function () use ($record, $note): Record {
            $lockedRecord = Record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $existing = trim((string) $lockedRecord->log_section);
            $newNote = now()->timezone('Asia/Manila')->format('d/m/Y').': '.$note;
            $lockedRecord->log_section = $existing === '' ? $newNote : $existing.', '.$newNote;
            $lockedRecord->save();

            return $lockedRecord;
        });

        return response()->json($this->recordPayload($record->refresh()));
    }

    public function deleteNote(Record $record, int $noteIndex): JsonResponse
    {
        $record = DB::transaction(function () use ($record, $noteIndex): Record {
            $lockedRecord = Record::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $notes = $this->parseNotes((string) $lockedRecord->log_section);

            abort_unless(array_key_exists($noteIndex, $notes), 404);
            array_splice($notes, $noteIndex, 1);
            $lockedRecord->log_section = $notes === [] ? null : implode(', ', $notes);
            $lockedRecord->save();

            return $lockedRecord;
        });

        return response()->json($this->recordPayload($record->refresh()));
    }

    private function recordPayload(Record $record): array
    {
        return [
            'id' => $record->id,
            'name' => $record->full_name ?? 'Unnamed record',
            'registry' => $record->registry ?? '',
            'notes' => $this->parseNotes((string) $record->log_section),
        ];
    }

    /**
     * New notes are comma-separated entries beginning with DD/MM/YYYY.
     * Older imported text is preserved as-is until an individual dated entry is added.
     *
     * @return list<string>
     */
    private function parseNotes(string $logSection): array
    {
        $logSection = trim($logSection);
        if ($logSection === '') {
            return [];
        }

        $notes = preg_split('/,\s*(?=\d{2}\/\d{2}\/\d{4}\s*:)/u', $logSection) ?: [];

        return array_values(array_filter(array_map('trim', $notes), fn (string $note): bool => $note !== ''));
    }
}
