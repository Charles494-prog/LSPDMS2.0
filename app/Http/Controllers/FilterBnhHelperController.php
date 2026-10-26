<?php

namespace App\Http\Controllers;

use App\Models\Purok;
use App\Models\Record;
use App\Support\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilterBnhHelperController extends Controller
{
    public function index(Request $request): View
    {
        $canViewMk = $this->canViewMk($request);

        return view('filter-bnh-helper', [
            'puroks' => Purok::query()
                ->orderBy('purok_number')
                ->orderBy('grupo_start')
                ->orderBy('id')
                ->get(),
            'canViewMk' => $canViewMk,
        ]);
    }

    public function matrix(Request $request): JsonResponse
    {
        [$puroks, $records, $purokIndex] = $this->filteredData($request);
        $rows = $this->matrixRows($puroks, $records, $purokIndex);
        $totals = $this->countTotals($records);

        return response()->json([
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purok_id' => ['nullable', 'string'],
            'purok_selection' => ['nullable', Rule::in(['all', 'selected'])],
            'purok_ids' => ['nullable', 'array'],
            'purok_ids.*' => ['integer', 'distinct', 'exists:puroks,id'],
            'grupo' => ['nullable', 'integer', 'min:0'],
            'gender' => ['required', Rule::in(['male', 'female', 'all'])],
            'status' => ['nullable', Rule::in(['MS', 'UWP', 'MK', 'ALL'])],
            'status_selection' => ['nullable', Rule::in(['selected'])],
            'status_ids' => ['nullable', 'array'],
            'status_ids.*' => ['string', 'distinct', Rule::in(['MS', 'UWP', 'MK'])],
        ]);
        $allPuroks = $this->allPuroks();
        $puroks = ($validated['purok_selection'] ?? null) === 'selected'
            ? $this->selectedPuroks($validated['purok_ids'] ?? [], $allPuroks)
            : $this->selectedPuroks($validated['purok_id'] ?? 'all', $allPuroks);
        $purokIndex = $this->purokIndex($allPuroks);
        $records = $this->recordsFor($puroks, $this->selectedStatuses($validated, $request), $purokIndex)
            ->filter(fn (Record $record): bool => (! isset($validated['grupo']) || (int) $record->grupo === (int) $validated['grupo'])
                && ($validated['gender'] === 'all' || $this->normalizeGender($record->gender) === $validated['gender']))
            ->sortBy(fn (Record $record): string => mb_strtolower((string) $record->full_name))
            ->values();

        return response()->json([
            'count' => $records->count(),
            'records' => $records->map(function (Record $record) use ($purokIndex): array {
                $purok = $this->resolvePurok($record, $purokIndex);

                return [
                    'name' => $record->full_name ?: 'Unnamed record',
                    'status' => mb_strtoupper(trim((string) $record->status)),
                    'location' => ($purok ? $this->shortPurokName($purok) : trim((string) $record->purok)).'-'.$record->grupo,
                ];
            }),
        ]);
    }

    public function bnhRecords(Request $request): JsonResponse
    {
        [, $records, $purokIndex] = $this->filteredData($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 100;
        $resolved = $this->searchBnhRecords($records, (string) $request->query('q', ''))
            ->map(fn (Record $record): array => $this->bnhRecord($record, $purokIndex))
            ->sortBy(fn (array $row): array => $this->bnhRecordSortKey($row))
            ->values();
        $total = $resolved->count();

        return response()->json([
            'records' => $resolved->forPage($page, $perPage)->values(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function updateChecks(Request $request, Record $record): JsonResponse
    {
        $validated = $request->validate([
            'is_valid1' => ['required', 'boolean'],
            'is_valid2' => ['required', 'boolean'],
        ]);

        abort_if(mb_strtoupper(trim((string) $record->status)) === 'INACTIVE', 404);

        $record->forceFill([
            'is_valid1' => (bool) $validated['is_valid1'],
            'is_valid2' => (bool) $validated['is_valid2'],
        ])->save();

        return response()->json([
            'message' => 'BNH helper checks saved.',
            'is_valid1' => (bool) $record->is_valid1,
            'is_valid2' => (bool) $record->is_valid2,
            'qualified' => (bool) $record->is_valid1 && (bool) $record->is_valid2,
        ]);
    }

    public function qualifiedCounts(Request $request): JsonResponse
    {
        [$puroks, $records, $purokIndex] = $this->filteredData($request);
        $qualified = $records->filter(fn (Record $record): bool => $record->is_valid1 && $record->is_valid2);
        $minuses = $records->reject(fn (Record $record): bool => $record->is_valid1 && $record->is_valid2);

        return response()->json([
            'rows' => $this->resumenRows($puroks, $qualified, $minuses, $purokIndex),
            'totals' => $this->countTotals($qualified),
            'minuses_totals' => $this->countTotals($minuses),
        ]);
    }

    public function downloadBnhWord(Request $request): StreamedResponse
    {
        [, $records, $purokIndex] = $this->filteredData($request);
        $rows = $this->searchBnhRecords($records, (string) $request->query('q', ''))
            ->map(fn (Record $record): array => $this->bnhRecord($record, $purokIndex))
            ->sortBy(fn (array $row): array => $this->bnhRecordSortKey($row))
            ->values()
            ->all();

        return $this->wordDownload('BNH Form Helper', 'bnh-form-helper.doc', 'filter-bnh-helper.word-list', [
            'rows' => $rows,
            'filters' => $this->filterLabels($request),
        ]);
    }

    public function downloadCountsWord(Request $request): StreamedResponse
    {
        $display = $request->validate([
            'show_qualified' => ['sometimes', 'boolean'],
            'show_minuses' => ['sometimes', 'boolean'],
        ]);
        $showQualified = (bool) ($display['show_qualified'] ?? true);
        $showMinuses = (bool) ($display['show_minuses'] ?? true);
        abort_if(! $showQualified && ! $showMinuses, 422, 'Select at least one count to include in the Resumen.');

        [$puroks, $records, $purokIndex] = $this->filteredData($request);
        $qualified = $records->filter(fn (Record $record): bool => $record->is_valid1 && $record->is_valid2);
        $minuses = $records->reject(fn (Record $record): bool => $record->is_valid1 && $record->is_valid2);

        return $this->wordDownload('BNH Helper Form Resumen', 'bnh-helper-form-resumen.doc', 'filter-bnh-helper.word-counts', [
            'rows' => $this->resumenRows($puroks, $qualified, $minuses, $purokIndex),
            'showQualified' => $showQualified,
            'showMinuses' => $showMinuses,
            'filters' => $this->filterLabels($request),
        ]);
    }

    /**
     * @return array{0: Collection<int, Purok>, 1: Collection<int, Record>, 2: Collection<int, Collection<int, Purok>>}
     */
    private function filteredData(Request $request): array
    {
        $validated = $request->validate([
            'purok_id' => ['nullable', 'string'],
            'purok_selection' => ['nullable', Rule::in(['all', 'selected'])],
            'purok_ids' => ['nullable', 'array'],
            'purok_ids.*' => ['integer', 'distinct', 'exists:puroks,id'],
            'status' => ['nullable', Rule::in(['MS', 'UWP', 'MK', 'ALL'])],
            'status_selection' => ['nullable', Rule::in(['selected'])],
            'status_ids' => ['nullable', 'array'],
            'status_ids.*' => ['string', 'distinct', Rule::in(['MS', 'UWP', 'MK'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);
        $allPuroks = $this->allPuroks();
        $puroks = ($validated['purok_selection'] ?? null) === 'selected'
            ? $this->selectedPuroks($validated['purok_ids'] ?? [], $allPuroks)
            : $this->selectedPuroks($validated['purok_id'] ?? 'all', $allPuroks);
        $purokIndex = $this->purokIndex($allPuroks);
        $records = $this->recordsFor($puroks, $this->selectedStatuses($validated, $request), $purokIndex);

        return [$puroks, $records, $purokIndex];
    }

    /**
     * Every Purok setting in Purok-list order, loaded once per request.
     *
     * @return Collection<int, Purok>
     */
    private function allPuroks(): Collection
    {
        return Purok::query()
            ->orderBy('purok_number')
            ->orderBy('grupo_start')
            ->orderBy('id')
            ->get();
    }

    /**
     * The Purok settings grouped by Purok number, in Purok-list order, so a record's
     * Purok is resolved without scanning all settings (see resolvePurok()).
     *
     * @param  Collection<int, Purok>  $allPuroks  from allPuroks()
     * @return Collection<int, Collection<int, Purok>>
     */
    private function purokIndex(Collection $allPuroks): Collection
    {
        return $allPuroks->groupBy(fn (Purok $purok): int => (int) $purok->purok_number);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return string|list<string>
     */
    private function selectedStatuses(array $validated, Request $request): string|array
    {
        $canViewMk = $this->canViewMk($request);

        if (($validated['status_selection'] ?? null) !== 'selected') {
            $status = $validated['status'] ?? 'ALL';
            abort_if($status === 'MK' && ! $canViewMk, 403, 'You do not have permission to view MK records.');

            return $status === 'ALL' && ! $canViewMk ? ['MS', 'UWP'] : $status;
        }

        $statuses = array_values(array_filter(
            $validated['status_ids'] ?? [],
            fn (string $status): bool => $canViewMk || $status !== 'MK'
        ));

        return $statuses;
    }

    private function canViewMk(Request $request): bool
    {
        $user = $request->session()->get('login_user', []);
        return Role::isHeadSecretary($user['tungkulin'] ?? '')
            || (bool) ($user['Delistment_allow'] ?? false);
    }

    /**
     * @param  Collection<int, Record>  $records
     * @return Collection<int, Record>
     */
    private function searchBnhRecords(Collection $records, string $term): Collection
    {
        $term = mb_strtolower(trim($term));
        if ($term === '') {
            return $records;
        }

        return $records->filter(fn (Record $record): bool => str_contains(
            mb_strtolower(trim((string) $record->full_name)),
            $term
        ))->values();
    }

    /**
     * @param  Collection<int, Purok>  $allPuroks  from allPuroks()
     * @return Collection<int, Purok>
     */
    private function selectedPuroks(string|array $purokId, Collection $allPuroks): Collection
    {
        if (is_array($purokId)) {
            $ids = array_flip(array_map('intval', $purokId));

            return $allPuroks->filter(fn (Purok $purok): bool => isset($ids[(int) $purok->id]))->values();
        }

        if ($purokId !== 'all') {
            abort_unless(ctype_digit($purokId), 422, 'Choose a valid Purok.');

            return $allPuroks->filter(fn (Purok $purok): bool => (int) $purok->id === (int) $purokId)->values();
        }

        return $allPuroks;
    }

    /**
     * @param  Collection<int, Purok>  $puroks
     * @param  Collection<int, Collection<int, Purok>>  $purokIndex
     * @return Collection<int, Record>
     */
    private function recordsFor(Collection $puroks, string|array $status, Collection $purokIndex): Collection
    {
        if ($puroks->isEmpty() || $status === []) {
            return collect();
        }

        $selectedPurokIds = array_flip($puroks->map(fn (Purok $purok): int => (int) $purok->id)->all());

        return Record::query()
            ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) != 'INACTIVE'")
            ->when($status !== 'ALL', function (Builder $query) use ($status): Builder {
                if (is_array($status)) {
                    return $query->whereIn(\Illuminate\Support\Facades\DB::raw('UPPER(TRIM(COALESCE(status, \'\')))'), $status);
                }

                return $query->whereRaw('UPPER(TRIM(COALESCE(status, \'\'))) = ?', [$status]);
            })
            ->get([
                'id',
                'full_name',
                'gender',
                'purok',
                'grupo',
                'status',
                'is_valid1',
                'is_valid2',
            ])
            ->filter(function (Record $record) use ($selectedPurokIds, $purokIndex): bool {
                $resolved = $this->resolvePurok($record, $purokIndex);

                return $resolved !== null && isset($selectedPurokIds[(int) $resolved->id]);
            })
            ->values();
    }

    /**
     * @param  Collection<int, Purok>  $puroks
     * @param  Collection<int, Record>  $records
     * @param  Collection<int, Collection<int, Purok>>  $purokIndex
     * @return list<array{type: string, id: string, purok_id: int|null, grupo: int|null, label: string, male: int, female: int, total: int}>
     */
    private function matrixRows(Collection $puroks, Collection $records, Collection $purokIndex): array
    {
        // Gender counts per resolved Purok and Grupo, counted in one pass over the records.
        $groupCounts = [];
        foreach ($records as $record) {
            $purokId = (int) $this->resolvePurok($record, $purokIndex)?->id;
            $grupo = (int) $record->grupo;
            $groupCounts[$purokId][$grupo] ??= ['male' => 0, 'female' => 0, 'total' => 0];
            $gender = $this->normalizeGender($record->gender);
            if ($gender !== null) {
                $groupCounts[$purokId][$grupo][$gender]++;
            }
            $groupCounts[$purokId][$grupo]['total']++;
        }
        $rows = [];
        foreach ($puroks as $purok) {
            $purokMale = 0;
            $purokFemale = 0;
            $purokTotal = 0;
            for ($grupo = (int) $purok->grupo_start; $grupo <= (int) $purok->grupo_end; $grupo++) {
                $counts = $groupCounts[(int) $purok->id][$grupo] ?? ['male' => 0, 'female' => 0, 'total' => 0];
                $male = $counts['male'];
                $female = $counts['female'];
                $purokMale += $male;
                $purokFemale += $female;
                $purokTotal += $counts['total'];

                $rows[] = [
                    'type' => 'group',
                    'id' => $purok->id.'-'.$grupo,
                    'purok_id' => (int) $purok->id,
                    'grupo' => $grupo,
                    'label' => $this->shortPurokName($purok).'-'.$grupo,
                    'male' => $male,
                    'female' => $female,
                    'total' => $counts['total'],
                ];
            }
            $rows[] = [
                'type' => 'purok_total',
                'id' => 'purok-'.$purok->id,
                'purok_id' => (int) $purok->id,
                'grupo' => null,
                'label' => 'Total '.$this->shortPurokName($purok),
                'male' => $purokMale,
                'female' => $purokFemale,
                'total' => $purokTotal,
            ];
        }

        $overall = $this->countTotals($records);
        $rows[] = array_merge([
            'type' => 'grand_total',
            'id' => 'grand-total',
            'purok_id' => null,
            'grupo' => null,
            'label' => 'Overall Total',
        ], $overall);

        return $rows;
    }

    /**
     * @param  Collection<int, Purok>  $puroks
     * @param  Collection<int, Record>  $qualified
     * @param  Collection<int, Record>  $minuses
     * @param  Collection<int, Collection<int, Purok>>  $purokIndex
     * @return list<array<string, mixed>>
     */
    private function resumenRows(Collection $puroks, Collection $qualified, Collection $minuses, Collection $purokIndex): array
    {
        $qualifiedRows = $this->matrixRows($puroks, $qualified, $purokIndex);
        $minusesById = [];

        foreach ($this->matrixRows($puroks, $minuses, $purokIndex) as $row) {
            $minusesById[$row['id']] = $row;
        }

        return array_map(static function (array $row) use ($minusesById): array {
            $minusRow = $minusesById[$row['id']];

            return array_merge($row, [
                'minuses_male' => $minusRow['male'],
                'minuses_female' => $minusRow['female'],
                'minuses_total' => $minusRow['total'],
            ]);
        }, $qualifiedRows);
    }

    /**
     * @param  Collection<int, Record>  $records
     * @return array{male: int, female: int, total: int}
     */
    private function countTotals(Collection $records): array
    {
        return [
            'male' => $records->filter(fn (Record $record): bool => $this->normalizeGender($record->gender) === 'male')->count(),
            'female' => $records->filter(fn (Record $record): bool => $this->normalizeGender($record->gender) === 'female')->count(),
            'total' => $records->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{int, int, string}
     */
    private function bnhRecordSortKey(array $row): array
    {
        $location = (string) $row['location'];
        $purok = preg_match('/^(\d+)/', $location, $matches) === 1
            ? (int) $matches[1]
            : PHP_INT_MAX;
        $grupo = preg_match('/-(\d+)$/', $location, $matches) === 1
            ? (int) $matches[1]
            : PHP_INT_MAX;

        return [$purok, $grupo, mb_strtolower((string) $row['name'])];
    }

    /**
     * @param  Collection<int, Collection<int, Purok>>  $purokIndex  from purokIndex()
     */
    private function resolvePurok(Record $record, Collection $purokIndex): ?Purok
    {
        $number = $this->normalizePurokNumber($record->purok);
        if ($number === null) {
            return null;
        }

        $matches = $purokIndex->get($number, collect());
        if ($matches->count() <= 1) {
            return $matches->first();
        }

        return $matches->first(fn (Purok $purok): bool => $purok->full_purok
                || (is_numeric($record->grupo)
                    && (int) $record->grupo >= (int) $purok->grupo_start
                    && (int) $record->grupo <= (int) $purok->grupo_end));
    }

    /**
     * @param  Collection<int, Collection<int, Purok>>  $purokIndex
     * @return array<string, mixed>
     */
    private function bnhRecord(Record $record, Collection $purokIndex): array
    {
        $purok = $this->resolvePurok($record, $purokIndex);
        $isValid1 = (bool) $record->is_valid1;
        $isValid2 = (bool) $record->is_valid2;

        return [
            'id' => (int) $record->id,
            'name' => $record->full_name ?: 'Unnamed record',
            'status' => mb_strtoupper(trim((string) $record->status)),
            'location' => $purok ? $this->shortPurokName($purok).'-'.$record->grupo : trim((string) $record->purok).'-'.trim((string) $record->grupo),
            'is_valid1' => $isValid1,
            'is_valid2' => $isValid2,
            'qualified' => $isValid1 && $isValid2,
        ];
    }

    private function normalizeGender(mixed $gender): ?string
    {
        return match (mb_strtolower(trim((string) $gender))) {
            'male', 'm', 'lalaki', 'l' => 'male',
            'female', 'f', 'babae', 'b' => 'female',
            default => null,
        };
    }

    private function normalizePurokNumber(mixed $value): ?int
    {
        $value = trim((string) $value);
        if (preg_match('/^(?:Purok\s*)?(\d+)$/i', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function shortPurokName(Purok $purok): string
    {
        $name = trim((string) $purok->purok_name);
        if ($name === '') {
            return (string) $purok->purok_number;
        }

        return trim((string) preg_replace('/^Purok\s*/i', '', $name));
    }

    private function wordDownload(string $title, string $filename, string $view, array $data): StreamedResponse
    {
        $html = view($view, ['title' => $title] + $data)->render();

        return Response::streamDownload(
            static function () use ($html): void {
                echo $html;
            },
            $filename,
            ['Content-Type' => 'application/msword; charset=UTF-8']
        );
    }

    /**
     * @return array{purok: string, status: string}
     */
    private function filterLabels(Request $request): array
    {
        $purokIds = $request->query('purok_ids', []);
        $purok = ($request->query('purok_selection') === 'selected' && is_array($purokIds))
            ? Purok::query()->whereIn('id', $purokIds)->orderBy('purok_number')->orderBy('grupo_start')->pluck('purok_name')->implode(', ')
            : (((string) $request->query('purok_id', 'all')) === 'all'
                ? 'All Puroks'
                : (Purok::query()->find($request->query('purok_id'))?->purok_name ?: 'Selected Purok'));
        if ($purok === '') {
            $purok = 'No Puroks';
        }

        $statuses = $request->query('status_ids', []);
        if ($request->query('status_selection') === 'selected' && is_array($statuses)) {
            $canViewMk = $this->canViewMk($request);
            $statuses = array_values(array_filter($statuses, fn (string $status): bool => $canViewMk || $status !== 'MK'));
            $statusLabel = count($statuses) === ($canViewMk ? 3 : 2)
                ? 'ALL'
                : implode(', ', $statuses);
        } else {
            $statusLabel = (string) $request->query('status', 'ALL');
        }
        if ($statusLabel === '') {
            $statusLabel = 'None';
        } elseif ($statusLabel === 'ALL' && ! $this->canViewMk($request)) {
            $statusLabel = 'MS, UWP';
        }

        return [
            'purok' => $purok,
            'status' => $statusLabel,
        ];
    }
}
