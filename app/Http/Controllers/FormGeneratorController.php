<?php

namespace App\Http\Controllers;

use App\Models\GlobalVariable;
use App\Models\MonthlyMovementSession;
use App\Models\MonthlySessionMovement;
use App\Models\Purok;
use App\Models\R401Snapshot;
use App\Models\Record;
use App\Services\MonthlyResumenReport;
use App\Services\R401DocxExporter;
use App\Services\R401ReportBuilder;
use App\Services\ResumenDocxExporter;
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
            'purok' => ['required', 'string', 'max:20'],
            'kalagayan' => ['required', 'string', 'in:MS,UWP'],
        ]);
        $data = $this->r401ReportData($request);
        abort_if($data['reportRows'] === [], 404, 'No records match the selected Purok and Kalagayan.');
        $globalVariables = $data['globalVariables'];
        $kalihim = $data['selectedStatus'] === 'UWP'
            ? ($globalVariables?->kalihim_uwp ?: $globalVariables?->kalihim_ms)
            : ($globalVariables?->kalihim_ms ?: $globalVariables?->kalihim_uwp);
        $reportPages = array_map(function (array $page) use ($data, $kalihim): array {
            $page['kalihim_name'] = $data['usePurokSecretary']
                ? $page['secretary_name']
                : (string) $kalihim;

            return $page;
        }, $data['reportPages']);
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

        return view('forms.monthly-r4-01-snapshots', [
            'snapshots' => $snapshots,
            'filters' => $filters,
            'years' => $years,
            'canDeleteSnapshots' => in_array(mb_strtolower(trim((string) $request->session()->get('login_user.tungkulin', ''))), [
                'head secretary',
                'secretary head',
            ], true),
        ]);
    }

    public function viewR401Snapshot(R401Snapshot $snapshot)
    {
        abort_unless($snapshot->report_type === 'Monthly session', 404);
        $previewPath = preg_replace('/\.docx$/i', '.html', $snapshot->file_path);
        abort_unless(is_string($previewPath) && Storage::disk('local')->exists($previewPath), 404);

        return response(Storage::disk('local')->get($previewPath), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.str_replace('.docx', '.html', $snapshot->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

        $disk = Storage::disk('local');
        $previewPath = preg_replace('/\.docx$/i', '.html', $snapshot->file_path);
        foreach (array_filter([$snapshot->file_path, $previewPath]) as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new \RuntimeException('Unable to delete the saved monthly snapshot file.');
            }
        }
        $snapshot->delete();

        return redirect()
            ->route('forms.monthly.r4-01.snapshots')
            ->with('status', 'Monthly snapshot deleted.');
    }

    public function downloadR401Snapshot(R401Snapshot $snapshot)
    {
        abort_unless($snapshot->report_type === 'Monthly session', 404);
        abort_unless($snapshot->format === 'docx', 404);
        abort_unless(Storage::disk('local')->exists($snapshot->file_path), 404);

        return response()->download(
            Storage::disk('local')->path($snapshot->file_path),
            $snapshot->file_name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
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
            'purok' => ['nullable', 'string', 'max:20'],
            'kalagayan' => ['nullable', 'string', 'in:MS,UWP'],
        ]);
        if (isset($validated['purok']) && $validated['purok'] !== 'all') {
            $request->validate(['purok' => ['integer', 'exists:puroks,purok_number']]);
        }
        $session = MonthlyMovementSession::query()
            ->whereNotNull('period_month')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->first();
        $month = Carbon::parse($session?->period_month ?? now())->startOfMonth();
        $puroks = Purok::query()->orderBy('purok_number')->get(['purok_number', 'purok_name']);
        $selectedPurok = $validated['purok'] ?? null;
        $selectedStatus = $validated['kalagayan'] ?? null;
        $report = $selectedPurok !== null && $selectedStatus !== null
            ? $this->r401ReportBuilder->build($selectedPurok, $selectedStatus, $session)
            : ['rows' => [], 'pages' => []];
        $globalVariables = GlobalVariable::query()->first();
        $usePurokSecretary = (bool) ($globalVariables?->r401_use_purok_secretary ?? false);
        $globalKalihimName = $selectedStatus === 'UWP'
            ? (($globalVariables?->kalihim_uwp ?: $globalVariables?->kalihim_ms) ?? '')
            : (($globalVariables?->kalihim_ms ?: $globalVariables?->kalihim_uwp) ?? '');
        $reportPages = array_map(function (array $page) use ($usePurokSecretary, $globalKalihimName): array {
            $page['kalihim_name'] = $usePurokSecretary
                ? $page['secretary_name']
                : $globalKalihimName;

            return $page;
        }, $report['pages']);

        return [
            'month' => $month,
            'reportRows' => $report['rows'],
            'reportPages' => $reportPages,
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

    private function selectedMemberRows(string $purok, string $status): array
    {
        $candidateRows = Record::query()
            ->orderBy('full_name')
            ->get([
                'id',
                'full_name',
                'registry',
                'gender',
                'purok',
                'grupo',
                'kapisanan',
                'date_of_baptism',
                'evangelist',
                'status',
                'monthly_code',
            ])
            ->map(function (Record $record): array {
                $monthlyCode = trim((string) $record->monthly_code);
                [$codeStatus, $code] = $this->monthlyCodeParts(
                    $monthlyCode,
                    mb_strtoupper(trim((string) $record->status))
                );

                return [
                    'record_id' => $record->id,
                    'status' => $codeStatus ?: mb_strtoupper(trim((string) $record->status)),
                    'code' => $monthlyCode === '' ? 'A' : ($code ?: 'A'),
                    'name' => trim((string) $record->full_name),
                    'registry' => strtoupper(trim((string) $record->registry)),
                    'gender' => $this->displayGender((string) $record->gender),
                    'purok' => trim((string) $record->purok),
                    'grupo' => trim((string) $record->grupo),
                    'kapisanan' => $this->displayKapisanan((string) $record->kapisanan),
                    'date_of_baptism' => $record->date_of_baptism?->format('d/m/Y') ?? '',
                    'doctrinator' => trim((string) $record->evangelist),
                ];
            })
            ->filter(fn (array $row): bool => $row['status'] === $status);

        $activeSessionId = MonthlyMovementSession::query()
            ->where('active_key', 'active')
            ->value('id');
        if ($activeSessionId !== null) {
            $movementRows = MonthlySessionMovement::query()
                ->where('session_id', $activeSessionId)
                ->orderBy('id')
                ->get()
                ->map(function (MonthlySessionMovement $movement): ?array {
                    $previous = $movement->previous_values ?? [];
                    [$codeStatus, $code] = $this->monthlyCodeParts(
                        (string) $movement->monthly_code,
                        mb_strtoupper(trim((string) ($previous['status'] ?? $movement->status)))
                    );
                    $memberStatus = $codeStatus ?: mb_strtoupper(trim((string) ($previous['status'] ?? $movement->status)));
                    $memberPurok = trim((string) ($previous['purok'] ?? $movement->previous_purok));
                    $memberGrupo = trim((string) ($previous['grupo'] ?? $movement->previous_grupo));
                    if (! in_array($memberStatus, ['MS', 'UWP'], true) || $memberPurok === '') {
                        return null;
                    }

                    return [
                        'record_id' => $movement->record_id,
                        'status' => $memberStatus,
                        'code' => $code ?: 'A',
                        'name' => trim((string) $movement->full_name),
                        'registry' => strtoupper(trim((string) $movement->registry)),
                        'gender' => $this->displayGender((string) $movement->gender),
                        'purok' => $memberPurok,
                        'grupo' => $memberGrupo,
                        'kapisanan' => $this->displayKapisanan((string) $movement->kapisanan),
                        'date_of_baptism' => $movement->date_of_baptism?->format('d/m/Y') ?? '',
                        'doctrinator' => trim((string) $movement->evangelist),
                    ];
                })
                ->filter(fn (?array $row): bool => $row !== null && $row['status'] === $status);
            $candidateRows = $candidateRows->concat($movementRows);
        }

        $candidateRows = $candidateRows
            ->unique(fn (array $row): string => implode('|', [
                (string) ($row['record_id'] ?? $row['name'].'|'.$row['registry']),
                $row['status'],
                $row['code'],
                $this->numericSortKey($row['purok']),
                preg_replace('/\D+/', '', $row['grupo']) ?? '',
            ]))
            ->when($purok !== 'all', fn ($rows) => $rows->filter(
                fn (array $row): bool => $this->numericSortKey($row['purok']) === (int) $purok
            ))
            ->groupBy(fn (array $row): string => (string) $this->numericSortKey($row['purok']))
            ->sortBy(fn ($group, string $key): int => $this->numericSortKey($key));
        $purokDetails = Purok::query()
            ->orderBy('purok_number')
            ->orderBy('grupo_start')
            ->get(['id', 'purok_number', 'purok_name', 'manggagawa', 'ministerial_id', 'grupo_start', 'grupo_end', 'full_purok']);
        $reportRows = [];
        $reportPages = [];

        foreach ($candidateRows as $purokNumber => $purokRecords) {
            $number = (int) $purokNumber;
            $splitPuroks = $purokDetails->where('purok_number', $number)->values();
            $assignedRecords = $splitPuroks->mapWithKeys(function (Purok $splitPurok) use ($purokRecords): array {
                $min = $splitPurok->full_purok ? 1 : (int) $splitPurok->grupo_start;
                $max = $splitPurok->full_purok ? 24 : (int) $splitPurok->grupo_end;

                return [
                    $splitPurok->id => $purokRecords->filter(
                        fn (array $row): bool => (int) $row['grupo'] >= $min && (int) $row['grupo'] <= $max
                    ),
                ];
            });
            if ($splitPuroks->isEmpty()) {
                $assignedRecords->put(null, $purokRecords);
            }

            foreach ($assignedRecords as $splitPurokId => $splitRecords) {
                if ($splitRecords->isEmpty()) {
                    continue;
                }
                $splitPurok = $splitPuroks->firstWhere('id', $splitPurokId);
                $rows = $splitRecords
                    ->sort(function (array $left, array $right): int {
                        $codeOrder = ($left['code'] === 'A' ? 0 : 1) <=> ($right['code'] === 'A' ? 0 : 1);
                        if ($codeOrder !== 0) {
                            return $codeOrder;
                        }
                        $grupoOrder = (int) $left['grupo'] <=> (int) $right['grupo'];

                        return $grupoOrder !== 0 ? $grupoOrder : strcasecmp($left['name'], $right['name']);
                    })
                    ->values()
                    ->map(function (array $row): array {
                        return [
                            'code' => $row['code'],
                            'name' => $row['name'],
                            'registry' => $row['registry'],
                            'gender' => $row['gender'],
                            'purok_grupo' => $this->numericSortKey($row['purok']).'-'.trim($row['grupo'], " \t\n\r\0\x0B-"),
                            'kapisanan' => $row['kapisanan'],
                            'date_of_baptism' => $row['date_of_baptism'],
                            'doctrinator' => $row['doctrinator'],
                        ];
                    })->all();
                $reportRows = [...$reportRows, ...$rows];
                foreach (array_chunk($rows, 20) as $pageIndex => $pageRows) {
                    $reportPages[] = [
                        'purok_number' => $purokNumber,
                        'purok_name' => $splitPurok?->purok_name ?? 'Purok '.$purokNumber,
                        'maysakop_name' => trim((string) ($splitPurok?->manggagawa ?? '')),
                        'assigned_number' => trim((string) ($splitPurok?->ministerial_id ?? '')),
                        'page_number' => $pageIndex + 1,
                        'rows' => $pageRows,
                    ];
                }
            }
        }

        return ['rows' => $reportRows, 'pages' => $reportPages];
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

        return [
            $status,
            mb_strtoupper(trim(explode('/', $parts[1], 2)[0], " \t\n\r\0\x0B\"'")),
        ];
    }

    private function numericSortKey(string $value): int
    {
        return (int) preg_replace('/\D+/', '', $value);
    }

    private function displayGender(string $gender): string
    {
        return match (mb_strtolower(trim($gender))) {
            'l', 'male' => 'L',
            'b', 'female' => 'B',
            default => '',
        };
    }

    private function displayKapisanan(string $kapisanan): string
    {
        return match (mb_strtolower(trim($kapisanan))) {
            'b', 'buklod' => 'B',
            'k', 'kadiwa' => 'K',
            's', 'suliranin' => 'S',
            'binhi' => 'b',
            default => trim($kapisanan),
        };
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
