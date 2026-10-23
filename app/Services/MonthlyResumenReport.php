<?php

namespace App\Services;

use App\Models\MonthlyMovementSession;
use App\Models\MonthlyRecordSnapshot;
use App\Models\Purok;
use App\Models\Record;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MonthlyResumenReport
{
    private const CODES = ['A', 'B', 'C+', 'C-', 'D', 'DD', 'E', 'G', 'H'];

    /**
     * Where the opening (A) counts came from: 'previous_snapshot',
     * 'current_snapshot', 'live_records' or 'missing' (no snapshot and no
     * session for that month, so the counts are unknown rather than zero).
     */
    private string $baselineSource = 'missing';

    /** @var Collection<int, Record>|null */
    private ?Collection $records = null;

    public function generate(
        Carbon $month,
        bool $includeCompletedSessionMovements = false,
        bool $reconcileToCurrentRecords = false
    ): array
    {
        $month = $month->copy()->startOfMonth();
        $this->baselineSource = 'missing';
        // Loaded once per report; the baseline and the segment weights both use it.
        $this->records = Record::query()->get(['purok', 'grupo', 'status']);
        $previousMonth = $month->copy()->subMonthNoOverflow();
        $previousSnapshots = MonthlyRecordSnapshot::query()
            ->whereDate('month', $previousMonth->toDateString())
            ->get(['purok_number', 'purok_id', 'ms_count', 'uwp_count']);
        $monthSnapshots = MonthlyRecordSnapshot::query()
            ->whereDate('month', $month->toDateString())
            ->get(['purok_number', 'purok_id', 'ms_count', 'uwp_count', 'movement_counts']);
        $session = MonthlyMovementSession::query()
            ->with('movements')
            ->whereDate('period_month', $month->toDateString())
            ->first();
        $sessionCompleted = $session !== null && $session->status !== 'active';
        $movementCounts = $sessionCompleted
            ? ($includeCompletedSessionMovements ? $this->snapshotMovementCounts($monthSnapshots) : [])
            : $this->movementCounts($monthSnapshots, $session);
        $baselineCounts = $sessionCompleted
            ? $this->completedSessionBaseline(
                $previousSnapshots,
                $monthSnapshots,
                $month,
                $includeCompletedSessionMovements
            )
            : $this->baselineCounts($previousSnapshots, $monthSnapshots, $session, $month);

        $purokDetails = Purok::query()
            ->orderBy('purok_number')
            ->orderBy('grupo_start')
            ->get(['id', 'purok_number', 'purok_name', 'manggagawa', 'grupo_start', 'grupo_end', 'full_purok']);
        $exactMovementCounts = $sessionCompleted && $includeCompletedSessionMovements
            ? $this->completedSessionSegmentMovements($session, $purokDetails)
            : $this->activeSessionSegmentMovements($session, $purokDetails);
        $records = $this->records;
        $purokNumbers = $purokDetails->pluck('purok_number')
            ->merge($previousSnapshots->pluck('purok_number'))
            ->merge($monthSnapshots->pluck('purok_number'))
            ->merge($records->pluck('purok')->map(fn ($value): int => $this->purokNumber((string) $value)))
            ->filter(fn ($number): bool => $number > 0)
            ->unique()
            ->sort()
            ->values();

        $rows = $purokNumbers->flatMap(function (int $number) use ($purokDetails, $records, $baselineCounts, $movementCounts, $exactMovementCounts, $reconcileToCurrentRecords): array {
            $segments = $purokDetails->where('purok_number', $number)->values();
            if ($segments->isEmpty()) {
                $segments = collect([[
                    'id' => null,
                    'purok_number' => $number,
                    'purok_name' => 'Purok '.$number,
                    'manggagawa' => '',
                    'grupo_start' => 1,
                    'grupo_end' => PHP_INT_MAX,
                    'full_purok' => true,
                ]]);
            }
            $weights = $segments->mapWithKeys(function ($segment) use ($records, $number): array {
                $id = is_array($segment) ? 'fallback' : (string) $segment->id;
                $fullPurok = is_array($segment) || $segment->full_purok;
                $start = is_array($segment) ? 1 : (int) $segment->grupo_start;
                $end = is_array($segment) ? PHP_INT_MAX : (int) $segment->grupo_end;
                $memberCounts = $records->filter(fn (Record $record): bool =>
                    $this->purokNumber((string) $record->purok) === $number
                    && ($fullPurok || (
                        (int) $record->grupo >= $start
                        && (int) $record->grupo <= $end
                    ))
                )->countBy(fn (Record $record): string => mb_strtoupper(trim((string) $record->status)));

                return [$id => $memberCounts->only(['MS', 'UWP'])->all()];
            });
            $allocatedBaseline = [];
            $allocatedMovements = [];
            foreach (['MS', 'UWP'] as $status) {
                $statusWeights = $weights->map(fn (array $counts): int => (int) ($counts[$status] ?? 0))->all();
                foreach (self::CODES as $code) {
                    $allocatedBaseline[$status][$code] = $code === 'A'
                        ? $this->allocateBaselineCount(
                            (int) ($baselineCounts[$number][$status] ?? 0),
                            $statusWeights,
                            $baselineCounts[$number][$status.'_segments'] ?? []
                        )
                        : $this->allocate(0, $statusWeights);
                }
                foreach (($movementCounts[$number][$status] ?? []) as $code => $count) {
                    $code = strtoupper(trim((string) $code));
                    if (in_array($code, self::CODES, true)) {
                        $exact = $exactMovementCounts[$number][$status][$code] ?? [];
                        $exactCount = array_sum($exact);
                        $allocatedMovements[$status][$code] = $this->allocate(
                            max(0, (int) $count - $exactCount),
                            $statusWeights
                        );
                        foreach ($exact as $segmentId => $quantity) {
                            $allocatedMovements[$status][$code][$segmentId] =
                                ($allocatedMovements[$status][$code][$segmentId] ?? 0) + $quantity;
                        }
                    }
                }
            }
            foreach (($exactMovementCounts[$number] ?? []) as $status => $codes) {
                foreach ($codes as $code => $segmentCounts) {
                    if (isset($allocatedMovements[$status][$code])) {
                        continue;
                    }
                    $allocatedMovements[$status][$code] = $segmentCounts;
                }
            }

            return $segments->map(function ($segment) use ($number, $weights, $allocatedBaseline, $allocatedMovements, $reconcileToCurrentRecords): array {
                $id = is_array($segment) ? 'fallback' : (string) $segment->id;
                $codesByStatus = [];
                foreach (['MS', 'UWP'] as $status) {
                    $codes = array_fill_keys(self::CODES, 0);
                    foreach (self::CODES as $code) {
                        $codes[$code] = $allocatedBaseline[$status][$code][$id] ?? 0;
                    }
                    foreach (($allocatedMovements[$status] ?? []) as $code => $distribution) {
                        $codes[$code] += $distribution[$id] ?? 0;
                    }
                    if ($reconcileToCurrentRecords) {
                        $openingCount = $codes['A']
                            + (int) ($weights[$id][$status] ?? 0)
                            - $this->endingCount($codes);
                        if ($openingCount < 0) {
                            throw new RuntimeException("Monthly {$status} movement totals exceed the current record count.");
                        }
                        $codes['A'] = $openingCount;
                    }
                    $codesByStatus[$status] = $codes;
                }

                return [
                    'purok_number' => $number,
                    'purok_label' => is_array($segment) ? 'Purok '.$number : ($segment->purok_name ?: 'Purok '.$number),
                    'maysakop' => trim((string) (is_array($segment) ? '' : $segment->manggagawa)),
                    'ms' => $codesByStatus['MS'],
                    'ms_total' => $this->endingCount($codesByStatus['MS']),
                    'uwp' => $codesByStatus['UWP'],
                    'uwp_total' => $this->endingCount($codesByStatus['UWP']),
                ];
            })->all();
        });

        $totals = [];
        foreach (['MS' => 'ms', 'UWP' => 'uwp'] as $status => $key) {
            $totals[$key] = array_fill_keys(self::CODES, 0);
            foreach ($rows as $row) {
                foreach (self::CODES as $code) {
                    $totals[$key][$code] += $row[$key][$code];
                }
            }
            $totals[$key.'_total'] = $this->endingCount($totals[$key]);
        }

        return [
            'month' => $month,
            'rows' => $rows,
            'totals' => $totals,
            'baseline_source' => $this->baselineSource,
            'baseline_missing' => $this->baselineSource === 'missing',
        ];
    }

    private function snapshotMovementCounts(Collection $snapshots): array
    {
        $counts = [];
        foreach ($snapshots as $snapshot) {
            foreach (($snapshot->movement_counts ?? []) as $status => $codes) {
                $status = mb_strtoupper(trim((string) $status));
                if (! in_array($status, ['MS', 'UWP'], true)) {
                    continue;
                }
                foreach ($codes as $code => $quantity) {
                    $code = strtoupper(trim((string) $code));
                    if (in_array($code, self::CODES, true)) {
                        $counts[$snapshot->purok_number][$status][$code] =
                            ($counts[$snapshot->purok_number][$status][$code] ?? 0) + (int) $quantity;
                    }
                }
            }
        }

        return $counts;
    }

    private function activeSessionSegmentMovements(?MonthlyMovementSession $session, Collection $purokDetails): array
    {
        if ($session === null || $session->status !== 'active') {
            return [];
        }

        $counts = [];
        foreach ($session->movements as $movement) {
            $previous = $movement->previous_values ?? [];
            $next = $movement->next_values ?? [];
            [$status, $code] = $this->monthlyCodeParts(
                (string) $movement->monthly_code,
                mb_strtoupper(trim((string) $movement->status))
            );
            if ($movement->action === 'add') {
                $this->incrementSegment($counts, $next['purok'] ?? null, $next['grupo'] ?? null, $next['status'] ?? $status, 'B', $purokDetails);
            } elseif ($movement->action === 'subtract') {
                $this->incrementSegment($counts, $previous['purok'] ?? null, $previous['grupo'] ?? null, $previous['status'] ?? $status, $code, $purokDetails);
            } elseif ($movement->action === 'transfer') {
                $this->incrementSegment($counts, $previous['purok'] ?? null, $previous['grupo'] ?? null, $previous['status'] ?? $status, 'C-', $purokDetails);
                $this->incrementSegment($counts, $next['purok'] ?? null, $next['grupo'] ?? null, $next['status'] ?? $status, 'C+', $purokDetails);
            } elseif ($movement->action === 'status') {
                $oldStatus = mb_strtoupper(trim((string) ($previous['status'] ?? $movement->status)));
                $newStatus = mb_strtoupper(trim((string) ($next['status'] ?? '')));
                $this->incrementSegment($counts, $previous['purok'] ?? null, $previous['grupo'] ?? null, $oldStatus, $newStatus === 'MK' ? 'E' : 'G', $purokDetails);
                if ($newStatus !== 'MK') {
                    $this->incrementSegment($counts, $next['purok'] ?? null, $next['grupo'] ?? null, $newStatus, 'B', $purokDetails);
                }
            }
        }

        foreach ($session->activities()->whereNull('session_movement_id')->whereIn('action', ['add', 'reactivate', 'subtract'])->get() as $activity) {
            [$status, $code] = $this->monthlyCodeParts((string) $activity->monthly_code);
            $this->incrementSegment(
                $counts,
                $activity->purok,
                $activity->grupo,
                $status,
                $activity->action === 'subtract' ? $code : 'B',
                $purokDetails
            );
        }

        return $counts;
    }

    private function completedSessionSegmentMovements(?MonthlyMovementSession $session, Collection $purokDetails): array
    {
        if ($session === null || $session->status === 'active') {
            return [];
        }

        $history = DB::table('permanent_history')
            ->where('session_id', $session->id)
            ->orderBy('id')
            ->get();
        $pairedTransfers = $history->filter(fn ($row): bool => $row->action === 'transfer'
            && preg_match('/C-\s*\/\s*(?:MS|UWP)\s*:\s*C\+/i', (string) $row->monthly_code) === 1)
            ->keyBy('record_id');
        $counts = [];
        $seen = [];
        foreach ($history as $row) {
            $previous = json_decode((string) ($row->previous_values ?? ''), true) ?: [];
            $next = json_decode((string) ($row->next_values ?? ''), true) ?: [];
            [$status, $code] = $this->monthlyCodeParts(
                (string) $row->monthly_code,
                mb_strtoupper(trim((string) $row->status))
            );
            if (! in_array($status, ['MS', 'UWP'], true) || ! in_array($code, self::CODES, true)) {
                continue;
            }

            if ($row->action === 'transfer' && isset($pairedTransfers[$row->record_id])) {
                if ($pairedTransfers[$row->record_id]->id !== $row->id) {
                    continue;
                }
                $this->incrementSegment($counts, $previous['purok'] ?? null, $previous['grupo'] ?? null, $status, 'C-', $purokDetails);
                $this->incrementSegment($counts, $next['purok'] ?? null, $next['grupo'] ?? null, $status, 'C+', $purokDetails);

                continue;
            }

            $isPreviousSide = in_array($code, ['C-', 'D', 'DD', 'E', 'G', 'H'], true);
            $values = $isPreviousSide ? $previous : $next;
            $purok = $values['purok'] ?? null;
            $grupo = $values['grupo'] ?? null;
            $key = implode('|', [(string) $row->record_id, $status, $code, (string) $purok, (string) $grupo]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $this->incrementSegment($counts, $purok, $grupo, $status, $code, $purokDetails);
        }

        return $counts;
    }

    private function incrementSegment(
        array &$counts,
        mixed $purok,
        mixed $grupo,
        mixed $status,
        string $code,
        Collection $purokDetails
    ): void {
        $number = $this->purokNumber((string) $purok);
        $status = mb_strtoupper(trim((string) $status));
        if ($number < 1 || ! in_array($status, ['MS', 'UWP'], true) || ! in_array($code, self::CODES, true)) {
            return;
        }

        $groupNumber = (int) preg_replace('/\D+/', '', (string) $grupo);
        $segment = $purokDetails->first(function (Purok $candidate) use ($number, $groupNumber): bool {
            if ((int) $candidate->purok_number !== $number) {
                return false;
            }
            if ($candidate->full_purok) {
                return true;
            }
            $start = (int) $candidate->grupo_start;
            $end = (int) $candidate->grupo_end;

            return $groupNumber >= $start && $groupNumber <= $end;
        });
        $segmentId = $segment?->id !== null ? (string) $segment->id : 'fallback';
        $counts[$number][$status][$code][$segmentId] =
            ($counts[$number][$status][$code][$segmentId] ?? 0) + 1;
    }

    private function monthlyCodeParts(string $monthlyCode, string $fallbackStatus = ''): array
    {
        $part = trim(explode('/', trim($monthlyCode), 2)[0]);
        $parts = explode(':', $part, 2);
        if (count($parts) < 2) {
            return [mb_strtoupper(trim($fallbackStatus)), mb_strtoupper(trim($part, " \t\n\r\0\x0B\"'"))];
        }

        $status = mb_strtoupper(trim($parts[0]));
        if (! in_array($status, ['MS', 'UWP'], true)) {
            $status = mb_strtoupper(trim($fallbackStatus));
        }

        return [$status, mb_strtoupper(trim($parts[1], " \t\n\r\0\x0B\"'"))];
    }

    private function movementCounts(Collection $snapshots, ?MonthlyMovementSession $session): array
    {
        $counts = [];
        foreach ($snapshots as $snapshot) {
            foreach (($snapshot->movement_counts ?? []) as $status => $codes) {
                $status = mb_strtoupper(trim((string) $status));
                if (! in_array($status, ['MS', 'UWP'], true)) {
                    continue;
                }
                foreach ($codes as $code => $quantity) {
                    $code = strtoupper(trim((string) $code));
                    $counts[$snapshot->purok_number][$status][$code] =
                        ($counts[$snapshot->purok_number][$status][$code] ?? 0) + (int) $quantity;
                }
            }
        }

        if ($session === null || $session->status !== 'active') {
            return $counts;
        }

        foreach ($session->movements as $movement) {
            $previous = $movement->previous_values ?? [];
            $next = $movement->next_values ?? [];
            if ($movement->action === 'add') {
                $this->increment($counts, $next['purok'] ?? null, $next['status'] ?? null, 'B');
            } elseif ($movement->action === 'subtract') {
                $code = strtoupper(trim(explode(':', (string) $movement->monthly_code, 2)[1] ?? ''));
                $this->increment($counts, $previous['purok'] ?? null, $previous['status'] ?? null, $code);
            } elseif ($movement->action === 'transfer') {
                $status = $previous['status'] ?? null;
                $this->increment($counts, $previous['purok'] ?? null, $status, 'C-');
                $this->increment($counts, $next['purok'] ?? null, $status, 'C+');
            } elseif ($movement->action === 'status') {
                $oldStatus = mb_strtoupper(trim((string) ($previous['status'] ?? '')));
                $newStatus = mb_strtoupper(trim((string) ($next['status'] ?? '')));
                $this->increment($counts, $previous['purok'] ?? null, $oldStatus, $newStatus === 'MK' ? 'E' : 'G');
                if ($newStatus !== 'MK') {
                    $this->increment($counts, $next['purok'] ?? null, $newStatus, 'B');
                }
            }
        }

        foreach ($session->activities()
            ->whereNull('session_movement_id')
            ->whereIn('action', ['add', 'reactivate', 'subtract'])
            ->get() as $activity) {
            [$status, $code] = array_pad(explode(':', (string) $activity->monthly_code, 2), 2, '');
            $status = mb_strtoupper(trim($status));
            $code = mb_strtoupper(trim($code));
            if ($activity->action === 'subtract') {
                $this->increment($counts, $activity->purok, $status, $code);
            } else {
                $this->increment($counts, $activity->purok, $status, 'B');
            }
        }

        return $counts;
    }

    private function baselineCounts(
        Collection $previousSnapshots,
        Collection $monthSnapshots,
        ?MonthlyMovementSession $session,
        Carbon $month
    ): array {
        if ($previousSnapshots->isNotEmpty()) {
            $this->baselineSource = 'previous_snapshot';

            return $this->snapshotBaselineCounts($previousSnapshots, false);
        }

        if ($monthSnapshots->isNotEmpty()) {
            $this->baselineSource = 'current_snapshot';

            return $this->snapshotBaselineCounts($monthSnapshots, true);
        }

        if ($session === null && ! $month->isSameMonth(now('Asia/Manila'))) {
            $this->baselineSource = 'missing';

            return [];
        }

        $this->baselineSource = 'live_records';
        $counts = [];
        foreach ($this->records ?? Record::query()->get(['purok', 'status']) as $record) {
            $number = $this->purokNumber((string) $record->purok);
            $status = mb_strtoupper(trim((string) $record->status));
            if ($number > 0 && in_array($status, ['MS', 'UWP'], true)) {
                $counts[$number][$status] = ($counts[$number][$status] ?? 0) + 1;
            }
        }

        foreach ($session?->movements ?? [] as $movement) {
            $previous = $movement->previous_values ?? [];
            $next = $movement->next_values ?? [];
            if ($movement->action === 'add') {
                $this->adjust($counts, $next['purok'] ?? null, $next['status'] ?? null, -1);
            } elseif ($movement->action === 'subtract') {
                $this->adjust($counts, $previous['purok'] ?? null, $previous['status'] ?? null, 1);
            } elseif ($movement->action === 'transfer') {
                $status = $previous['status'] ?? null;
                $this->adjust($counts, $previous['purok'] ?? null, $status, 1);
                $this->adjust($counts, $next['purok'] ?? null, $status, -1);
            } elseif ($movement->action === 'status') {
                $oldStatus = $previous['status'] ?? null;
                $newStatus = mb_strtoupper(trim((string) ($next['status'] ?? '')));
                $this->adjust($counts, $previous['purok'] ?? null, $oldStatus, 1);
                if ($newStatus !== 'MK') {
                    $this->adjust($counts, $next['purok'] ?? null, $newStatus, -1);
                }
            }
        }

        return $counts;
    }

    private function completedSessionBaseline(
        Collection $previousSnapshots,
        Collection $monthSnapshots,
        Carbon $month,
        bool $includeCompletedSessionMovements
    ): array {
        if (! $includeCompletedSessionMovements) {
            if ($monthSnapshots->isNotEmpty()) {
                $this->baselineSource = 'current_snapshot';

                return $this->snapshotBaselineCounts($monthSnapshots, false);
            }

            return $this->baselineCounts($previousSnapshots, $monthSnapshots, null, $month);
        }
        if ($previousSnapshots->isNotEmpty()) {
            $this->baselineSource = 'previous_snapshot';

            return $this->snapshotBaselineCounts($previousSnapshots, false);
        }
        if ($monthSnapshots->isNotEmpty()) {
            $this->baselineSource = 'current_snapshot';

            return $this->snapshotBaselineCounts($monthSnapshots, true);
        }

        return $this->baselineCounts($previousSnapshots, $monthSnapshots, null, $month);
    }

    private function snapshotBaselineCounts(Collection $snapshots, bool $subtractMovements): array
    {
        $counts = [];
        foreach ($snapshots as $snapshot) {
            $segmentId = $snapshot->purok_id === null ? null : (string) $snapshot->purok_id;
            foreach (['MS' => 'ms_count', 'UWP' => 'uwp_count'] as $status => $column) {
                $quantity = (int) $snapshot->{$column};
                if ($subtractMovements) {
                    $movementCodes = ($snapshot->movement_counts ?? [])[$status] ?? [];
                    $quantity -= $this->endingCount(array_merge(['A' => 0], $movementCodes));
                }
                $counts[$snapshot->purok_number][$status] =
                    ($counts[$snapshot->purok_number][$status] ?? 0) + $quantity;
                if ($segmentId !== null) {
                    $counts[$snapshot->purok_number][$status.'_segments'][$segmentId] =
                        ($counts[$snapshot->purok_number][$status.'_segments'][$segmentId] ?? 0) + $quantity;
                }
            }
        }

        return $counts;
    }

    private function allocateBaselineCount(int $total, array $weights, array $segmentCounts): array
    {
        if ($segmentCounts === []) {
            return $this->allocate($total, $weights);
        }

        $allocation = array_fill_keys(array_keys($weights), 0);
        foreach ($segmentCounts as $segmentId => $quantity) {
            if (array_key_exists($segmentId, $allocation)) {
                $allocation[$segmentId] = (int) $quantity;
            }
        }
        $assigned = array_sum($allocation);
        if ($assigned >= $total) {
            return $allocation;
        }

        $remainingWeights = array_diff_key($weights, $segmentCounts);
        if ($remainingWeights === []) {
            $remainingWeights = $weights;
        }
        foreach ($this->allocate($total - $assigned, $remainingWeights) as $segmentId => $quantity) {
            $allocation[$segmentId] = ($allocation[$segmentId] ?? 0) + $quantity;
        }

        return $allocation;
    }

    private function increment(array &$counts, mixed $purok, mixed $status, string $code): void
    {
        $number = $this->purokNumber((string) $purok);
        $status = mb_strtoupper(trim((string) $status));
        if ($number < 1 || ! in_array($status, ['MS', 'UWP'], true) || ! in_array($code, self::CODES, true)) {
            return;
        }
        $counts[$number][$status][$code] = ($counts[$number][$status][$code] ?? 0) + 1;
    }

    private function adjust(array &$counts, mixed $purok, mixed $status, int $amount): void
    {
        $number = $this->purokNumber((string) $purok);
        $status = mb_strtoupper(trim((string) $status));
        if ($number > 0 && in_array($status, ['MS', 'UWP'], true)) {
            $counts[$number][$status] = ($counts[$number][$status] ?? 0) + $amount;
        }
    }

    private function endingCount(array $codes): int
    {
        return (int) ($codes['A'] ?? 0)
            + (int) ($codes['B'] ?? 0)
            + (int) ($codes['C+'] ?? 0)
            - (int) ($codes['C-'] ?? 0)
            - (int) ($codes['D'] ?? 0)
            - (int) ($codes['DD'] ?? 0)
            - (int) ($codes['E'] ?? 0)
            - (int) ($codes['G'] ?? 0)
            - (int) ($codes['H'] ?? 0);
    }

    private function allocate(int $amount, array $weights): array
    {
        $keys = array_keys($weights);
        $totalWeight = array_sum($weights);
        if ($keys === []) {
            return [];
        }
        if ($totalWeight < 1) {
            $allocation = array_fill_keys($keys, 0);
            $allocation[$keys[0]] = $amount;

            return $allocation;
        }

        $allocation = [];
        $fractions = [];
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            $exact = $amount * $weight / $totalWeight;
            $floor = (int) floor($exact);
            $allocation[$key] = $floor;
            $fractions[$key] = $exact - $floor;
            $allocated += $floor;
        }
        arsort($fractions);
        foreach (array_slice(array_keys($fractions), 0, max(0, $amount - $allocated)) as $key) {
            $allocation[$key]++;
        }

        return $allocation;
    }

    private function purokNumber(string $value): int
    {
        return (int) preg_replace('/\D+/', '', $value);
    }
}
