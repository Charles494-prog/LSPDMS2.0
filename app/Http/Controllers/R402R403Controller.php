<?php

namespace App\Http\Controllers;

use App\Models\MonthlyMovementSession;
use App\Models\Purok;
use App\Models\R402GeneratedForm;
use App\Models\R402Hakbang;
use App\Services\PurokSelection;
use App\Services\R401ReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class R402R403Controller extends Controller
{
    public function __construct(private readonly R401ReportBuilder $r401ReportBuilder) {}

    public function r402(Request $request): View
    {
        $filters = $request->validate([
            'purok' => ['nullable', 'string', 'max:'.PurokSelection::MAX_LENGTH],
            'kalagayan' => ['nullable', Rule::in(['MS', 'UWP'])],
        ]);
        // Tokens: "all", "N" (every segment of purok number N) or "N:ID" (one segment, e.g. Purok 2a), comma separated.
        $selection = isset($filters['purok']) ? PurokSelection::fromRequestValue($filters['purok']) : null;
        $selectedPurok = isset($filters['purok']) ? (string) $filters['purok'] : null;
        $selectedStatus = $filters['kalagayan'] ?? null;
        $rows = [];
        $purokGroups = [];
        $warnings = [];

        if ($selection !== null && $selectedStatus !== null) {
            $report = $this->r401ReportBuilder->build($selection, $selectedStatus, $this->reportSession());
            $rows = $report['rows'];
            // One group per Purok segment, numbered from 1, each posting its own segment token to storeR402().
            $purokGroups = array_map(fn (array $segment): array => [
                'number' => $segment['token'],
                'name' => $segment['purok_name'],
                'rows' => $segment['rows'],
            ], $report['segments']);
            $warnings = $this->r401ReportBuilder->outsideRangeWarnings($report['outside_range']);
        }
        $puroks = Purok::selectionOptions();

        return view('forms.weekly-r4-02', [
            'puroks' => $puroks,
            'selectedPurok' => $selectedPurok,
            'selectedStatus' => $selectedStatus,
            'purokNames' => $puroks->pluck('purok_name', 'selection_token'),
            'rows' => $rows,
            'purokGroups' => $purokGroups,
            'reportWarnings' => $warnings,
            'hakbangs' => $selectedStatus
                ? R402Hakbang::query()->where('status', $selectedStatus)->orderBy('code')->get()
                : collect(),
            'recentForms' => R402GeneratedForm::query()
                ->withCount('formRecords')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function storeR402(Request $request)
    {
        $validated = $request->validate([
            // Validated (type, length, existence, segment match) by PurokSelection::fromRequestValue() below.
            'purok' => ['required'],
            'kalagayan' => ['required', Rule::in(['MS', 'UWP'])],
            'selection' => ['required', 'string', 'max:5000'],
            'split_codes' => ['sometimes', 'boolean'],
            'hakbang_code' => [
                'nullable',
                'required_unless:split_codes,1',
                'string',
                Rule::exists('r4_02_hakbangs', 'code')->where('status', $request->input('kalagayan')),
            ],
            'code_assignments' => ['nullable', 'required_if:split_codes,1', 'string', 'max:5000'],
            'date_of_dalaw' => ['nullable', 'date'],
            'date_of_ulat' => ['nullable', 'date'],
        ]);

        // A saved R4-02 belongs to exactly one Purok segment, so the posted token must resolve to one segment.
        $selection = PurokSelection::fromRequestValue($validated['purok']);
        $segmentIds = $selection->coveredSegmentIds();
        if (count($segmentIds) !== 1) {
            throw ValidationException::withMessages([
                'purok' => 'Select a single Purok segment (for example Purok 2a or Purok 2b) before saving an R4-02.',
            ]);
        }
        $segmentId = $segmentIds[0];
        $purokNumber = (int) Purok::query()->whereKey($segmentId)->value('purok_number');

        // Rebuild exactly the list the generator displayed for this segment so row numbers map to the same people.
        $segment = collect($this->r401ReportBuilder
            ->build($selection, $validated['kalagayan'], $this->reportSession())['segments'])
            ->firstWhere('purok_id', $segmentId);
        $rows = $segment['rows'] ?? [];
        if ($rows === []) {
            throw ValidationException::withMessages([
                'selection' => 'There are no R4-01 rows for the selected Purok and Kalagayan.',
            ]);
        }

        $selectedNumbers = $this->parseRowNumbers($validated['selection']);
        if (max($selectedNumbers) > count($rows)) {
            throw ValidationException::withMessages([
                'selection' => 'A selected row number is outside the displayed R4-01 list.',
            ]);
        }

        $assignments = $this->codeAssignments(
            $selectedNumbers,
            (bool) ($validated['split_codes'] ?? false),
            $validated['hakbang_code'] ?? null,
            $validated['code_assignments'] ?? '',
            $validated['kalagayan']
        );
        $sessionUser = $request->session()->get('login_user', []);
        $creator = trim(implode(' ', array_filter([
            $sessionUser['first_name'] ?? null,
            $sessionUser['last_name'] ?? null,
        ]))) ?: (string) ($sessionUser['gmail'] ?? $sessionUser['name'] ?? 'Secretary');

        $form = DB::transaction(function () use ($validated, $selectedNumbers, $rows, $assignments, $creator, $segmentId, $purokNumber): R402GeneratedForm {
            $form = R402GeneratedForm::query()->create([
                'purok_number' => $purokNumber,
                'purok_id' => $segmentId,
                'kalagayan' => $validated['kalagayan'],
                'date_of_dalaw' => $validated['date_of_dalaw'] ?? null,
                'date_of_ulat' => $validated['date_of_ulat'] ?? null,
                'created_by' => $creator,
            ]);

            foreach ($selectedNumbers as $number) {
                $row = $rows[$number - 1];
                $form->formRecords()->create([
                    'record_id' => $row['record_id'] ?? null,
                    'source_row_number' => $number,
                    'hakbang_code' => $assignments[$number],
                    'full_name' => $row['name'] ?? '',
                    'registry' => $row['registry'] ?? '',
                    'gender' => $row['gender'] ?? '',
                    'purok_grupo' => $row['purok_grupo'] ?? '',
                    'kapisanan' => $row['kapisanan'] ?? '',
                    'record_code' => $row['code'] ?? '',
                    'date_of_baptism' => $row['date_of_baptism'] ?? '',
                    'doctrinator' => $row['doctrinator'] ?? '',
                ]);
            }

            return $form;
        });

        return redirect()
            ->route('forms.weekly.r4-02.print', $form)
            ->with('status', 'R4-02 saved. Use Print / Save as PDF to produce a copy.');
    }

    public function printR402(R402GeneratedForm $generatedForm): View
    {
        $generatedForm->load('formRecords');
        // Forms saved per segment name that segment; older forms only know the purok number.
        $purok = $generatedForm->purok_id !== null
            ? Purok::query()->find($generatedForm->purok_id)
            : null;
        $purok ??= Purok::query()
            ->where('purok_number', $generatedForm->purok_number)
            ->orderBy('grupo_start')
            ->orderBy('id')
            ->first();
        $segmentCount = $purok === null
            ? 0
            : Purok::query()->where('purok_number', $purok->purok_number)->count();

        return view('forms.weekly-r4-02-print', [
            'generatedForm' => $generatedForm,
            'purok' => $purok,
            'purokToken' => $generatedForm->purok_id !== null && $purok !== null
                ? PurokSelection::tokenFor((int) $purok->purok_number, (int) $purok->id, $segmentCount)
                : (string) $generatedForm->purok_number,
        ]);
    }

    public function r403(Request $request): View
    {
        $filters = $request->validate(['kalagayan' => ['nullable', Rule::in(['all', 'MS', 'UWP'])]]);
        $forms = $this->generatedForms($filters['kalagayan'] ?? 'all');

        return view('forms.weekly-r4-03', [
            'selectedStatus' => $filters['kalagayan'] ?? 'all',
            'groups' => $this->groupFormsByPurok($forms),
        ]);
    }

    public function printR403(Request $request): View
    {
        $filters = $request->validate(['kalagayan' => ['nullable', Rule::in(['all', 'MS', 'UWP'])]]);
        $forms = $this->generatedForms($filters['kalagayan'] ?? 'all');

        return view('forms.weekly-r4-03-print', [
            'selectedStatus' => $filters['kalagayan'] ?? 'all',
            'groups' => $this->groupFormsByPurok($forms),
        ]);
    }

    private function generatedForms(string $status)
    {
        return R402GeneratedForm::query()
            ->with('formRecords')
            ->when($status !== 'all', fn ($query) => $query->where('kalagayan', $status))
            ->orderBy('purok_number')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    private function groupFormsByPurok($forms)
    {
        $puroks = Purok::query()
            ->orderBy('purok_number')
            ->orderBy('grupo_start')
            ->orderBy('id')
            ->get(['id', 'purok_number', 'purok_name', 'grupo_start']);
        $segmentsById = $puroks->keyBy('id');
        // Legacy forms without purok_id are grouped by number and named after every segment of that number.
        $names = $puroks->groupBy('purok_number')->map(
            fn ($segments): string => $segments->pluck('purok_name')->filter()->implode(' / ')
        );

        return $forms
            ->groupBy(fn (R402GeneratedForm $form): string => $form->purok_id !== null && $segmentsById->has($form->purok_id)
                ? 'segment:'.$form->purok_id
                : 'number:'.$form->purok_number)
            ->map(function ($purokForms, string $key) use ($segmentsById, $names): array {
                $form = $purokForms->first();
                $number = (int) $form->purok_number;
                $segment = str_starts_with($key, 'segment:') ? $segmentsById->get($form->purok_id) : null;

                return [
                    'number' => $number,
                    'purok_id' => $segment?->id,
                    'name' => $segment !== null
                        ? ($segment->purok_name ?: 'Purok '.$number)
                        : (($names[$number] ?? null) ?: 'Purok '.$number),
                    'forms' => $purokForms,
                    'record_count' => $purokForms->sum(fn (R402GeneratedForm $form): int => $form->formRecords->count()),
                    'sort' => [$number, $segment !== null ? (int) $segment->grupo_start : PHP_INT_MAX, (int) ($segment?->id ?? 0)],
                ];
            })
            ->sort(fn (array $left, array $right): int => $left['sort'] <=> $right['sort'])
            ->values();
    }

    private function reportSession(): ?MonthlyMovementSession
    {
        return MonthlyMovementSession::query()
            ->whereNotNull('period_month')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->first();
    }

    private function parseRowNumbers(string $input): array
    {
        $numbers = [];
        foreach (explode(',', $input) as $part) {
            $part = trim($part);
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $range) === 1) {
                $start = (int) $range[1];
                $end = (int) $range[2];
                if ($start < 1 || $end < $start || $end - $start > 2000) {
                    throw ValidationException::withMessages(['selection' => 'Enter valid ascending row ranges, such as 1-5.']);
                }
                foreach (range($start, $end) as $number) {
                    $numbers[$number] = $number;
                }
                continue;
            }
            if (preg_match('/^\d+$/', $part) !== 1 || (int) $part < 1) {
                throw ValidationException::withMessages([
                    'selection' => 'Use row numbers separated by commas, such as 1-5 or 1,3,5.',
                ]);
            }
            $numbers[(int) $part] = (int) $part;
        }

        if ($numbers === [] || count($numbers) > 2000) {
            throw ValidationException::withMessages(['selection' => 'Select between 1 and 2,000 R4-01 rows.']);
        }

        ksort($numbers);

        return array_values($numbers);
    }

    private function codeAssignments(
        array $selectedNumbers,
        bool $splitCodes,
        ?string $defaultCode,
        string $rules,
        string $status
    ): array {
        if (! $splitCodes) {
            return array_fill_keys($selectedNumbers, (string) $defaultCode);
        }

        $assignments = [];
        foreach (preg_split('/[;\r\n]+/', trim($rules), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $rule) {
            if (preg_match('/^\s*(.+?)\s*=\s*(\d+-\d+)\s*$/', $rule, $matches) !== 1) {
                throw ValidationException::withMessages([
                    'code_assignments' => 'Use rules such as 1-3=1-7;5,7=1-8.',
                ]);
            }
            $code = trim($matches[2]);
            $isConfigured = R402Hakbang::query()
                ->where('status', $status)
                ->where('code', $code)
                ->exists();
            if (! $isConfigured) {
                throw ValidationException::withMessages([
                    'code_assignments' => "Hakbang code {$code} is not configured for {$status}.",
                ]);
            }

            foreach ($this->parseRowNumbers($matches[1]) as $number) {
                if (! in_array($number, $selectedNumbers, true)) {
                    throw ValidationException::withMessages([
                        'code_assignments' => "Row {$number} has a code rule but is not selected.",
                    ]);
                }
                if (isset($assignments[$number])) {
                    throw ValidationException::withMessages([
                        'code_assignments' => "Row {$number} has more than one code assignment.",
                    ]);
                }
                $assignments[$number] = $code;
            }
        }

        if (array_diff($selectedNumbers, array_keys($assignments)) !== []) {
            throw ValidationException::withMessages([
                'code_assignments' => 'Assign a configured hakbang code to every selected row.',
            ]);
        }

        ksort($assignments);

        return $assignments;
    }
}
