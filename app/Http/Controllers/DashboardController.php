<?php

namespace App\Http\Controllers;

use App\Models\GlobalVariable;
use App\Models\MonthlyRecordSnapshot;
use App\Models\Purok;
use App\Models\Record;
use App\Services\R210Eligibility;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, R210Eligibility $eligibility)
    {
        $user = $request->session()->get('login_user');
        $canEditRegistry = Role::isHeadSecretary($user['tungkulin'] ?? '')
            || (bool) ($user['Inquire_Allow'] ?? false);
        $records = Record::query()
            ->select([
                'id',
                'full_name',
                'registry',
                'gender',
                'purok',
                'grupo',
                'kapisanan',
                'date_of_baptism',
                'evangelist',
                'date_of_entry',
                'date_of_birth',
                'r2_10_endorsement',
                'status',
            ])
            ->get();

        // Each record's raw values are read and trimmed once. The reports below walk
        // these rows (grouped by Purok where they need it) instead of filtering every
        // record again for each Purok, status and field.
        $rows = [];
        foreach ($records as $key => $record) {
            $attributes = $record->getAttributes();
            $trimmedStatus = trim((string) $attributes['status']);
            $rows[$key] = [
                'id' => $record->id,
                'attributes' => $attributes,
                'trimmedStatus' => $trimmedStatus,
                'status' => mb_strtoupper($trimmedStatus),
                'purok' => trim((string) $attributes['purok']),
            ];
        }

        $statusLabels = collect(array_column($rows, 'status'))
            ->filter()
            ->unique()
            ->sortBy(fn (string $status): array => [
                array_search($status, ['MS', 'UWP', 'MK', 'INACTIVE'], true) === false
                    ? 4
                    : array_search($status, ['MS', 'UWP', 'MK', 'INACTIVE'], true),
                $status,
            ])
            ->values()
            ->all();

        $statusCounts = array_fill_keys($statusLabels, 0);
        $unspecifiedStatusCount = 0;
        foreach ($rows as $row) {
            $status = $row['status'];
            if ($status !== '' && array_key_exists($status, $statusCounts)) {
                $statusCounts[$status]++;
            } elseif ($status === '') {
                $unspecifiedStatusCount++;
            }
        }
        $statusChart = array_map(fn (string $status): array => [
            'code' => $status,
            'label' => $status,
            'count' => $statusCounts[$status],
        ], $statusLabels);
        if ($unspecifiedStatusCount > 0) {
            $statusChart[] = [
                'code' => 'UNSPECIFIED',
                'label' => 'Unspecified',
                'count' => $unspecifiedStatusCount,
            ];
        }

        $activeStatuses = array_values(array_filter($statusLabels, fn (string $status): bool => $status !== 'INACTIVE'));
        $activeRows = array_filter($rows, fn (array $row): bool => $row['trimmedStatus'] !== ''
            && $row['status'] !== 'INACTIVE');
        $activeRecords = $records->filter(fn (Record $record, int $key): bool => isset($activeRows[$key]));
        $activeTotal = count($activeRows);
        $inactiveTotal = $statusCounts['INACTIVE'] ?? 0;

        $purokSettings = Purok::query()
            ->orderBy('purok_number')
            ->orderBy('id')
            ->get(['id', 'purok_number', 'purok_name', 'grupo_start', 'grupo_end', 'full_purok']);
        $purokSettingsByNumber = $purokSettings->groupBy(
            fn (Purok $purok): string => (string) $purok->purok_number
        );
        $normalizePurok = static function (mixed $value): string {
            $value = trim((string) $value);
            if ($value === '') {
                return 'Unassigned';
            }
            if (preg_match('/^(?:Purok\s*)?(\d+)$/i', $value, $matches)) {
                return (string) (int) $matches[1];
            }

            return $value;
        };
        $activeRowsByPurok = [];
        foreach ($activeRows as $row) {
            $activeRowsByPurok[$normalizePurok($row['attributes']['purok'])][] = $row;
        }
        $puroks = $purokSettings->map(function (Purok $purok) use (
            $activeRowsByPurok,
            $activeStatuses,
            $purokSettingsByNumber
        ): array {
            $purokNumber = (string) $purok->purok_number;
            $isSplitHeader = $purokSettingsByNumber->get($purokNumber)->count() > 1;
            $members = [];
            foreach ($activeRowsByPurok[$purokNumber] ?? [] as $row) {
                $grupo = $row['attributes']['grupo'];
                if (! $isSplitHeader || $purok->full_purok || (
                    is_numeric($grupo)
                    && (int) $grupo >= $purok->grupo_start
                    && (int) $grupo <= $purok->grupo_end
                )) {
                    $members[] = $row;
                }
            }
            $name = trim((string) $purok->purok_name) ?: 'Purok '.$purokNumber;
            $counts = array_fill_keys($activeStatuses, 0);
            foreach ($members as $row) {
                if (array_key_exists($row['status'], $counts)) {
                    $counts[$row['status']]++;
                }
            }

            return [
                'id' => $purok->id,
                'number' => $purokNumber,
                'name' => $name,
                'counts' => $counts,
                'total' => array_sum($counts),
                'recordTotal' => count($members),
            ];
        })->all();

        $genderCounts = [];
        $genderRecords = [];
        foreach (['female' => 'B', 'male' => 'L'] as $gender => $code) {
            $genderCounts[$gender] = array_fill_keys($activeStatuses, 0);
            $genderRecords[$gender] = [];
            foreach ($activeRows as $row) {
                if (mb_strtoupper(trim((string) $row['attributes']['gender'])) === $code) {
                    $status = $row['status'];
                    $genderCounts[$gender][$status]++;
                    $genderRecords[$gender][] = [
                        'id' => $row['id'],
                        'name' => trim((string) $row['attributes']['full_name']) ?: 'Unnamed record',
                        'status' => $status,
                        'purok' => $row['purok'] ?: 'Unassigned',
                    ];
                }
            }
        }

        $groupLabels = ['Binhi', 'Kadiwa', 'Buklod'];
        $groupStatuses = ['MS', 'MK', 'UWP'];
        $groupCounts = array_fill_keys($groupStatuses, array_fill_keys($groupLabels, 0));
        $groupRecords = array_fill_keys($groupStatuses, array_fill_keys($groupLabels, []));
        foreach ($activeRows as $row) {
            $status = $row['status'];
            if (! array_key_exists($status, $groupCounts)) {
                continue;
            }

            $group = match (mb_strtolower(trim((string) $row['attributes']['kapisanan']))) {
                'binhi' => 'Binhi',
                'k', 'kadiwa' => 'Kadiwa',
                'b', 'buklod' => 'Buklod',
                default => null,
            };
            if ($group !== null) {
                $groupCounts[$status][$group]++;
                $groupRecords[$status][$group][] = [
                    'id' => $row['id'],
                    'name' => trim((string) $row['attributes']['full_name']) ?: 'Unnamed record',
                    'status' => $status,
                ];
            }
        }

        $monthlySnapshots = MonthlyRecordSnapshot::query()
            ->orderBy('month')
            ->orderBy('purok_number')
            ->get(['month', 'purok_number', 'purok_id', 'ms_count', 'uwp_count', 'mk_count', 'movement_counts']);
        // Only months that have a snapshot are charted; months without one are
        // skipped (never shown as 0).
        $monthKeys = $monthlySnapshots
            ->map(fn (MonthlyRecordSnapshot $snapshot): string => $snapshot->month->format('Y-m'))
            ->unique()
            ->values();
        $monthLabels = $monthKeys
            ->map(fn (string $monthKey): string => Carbon::createFromFormat('Y-m-d', $monthKey.'-01')->format('M Y'))
            ->all();
        $monthlyCounts = [];
        $monthlyPurokCounts = [];
        $movementDetails = [];
        foreach ($monthKeys as $monthKey) {
            $monthlyCounts[$monthKey] = ['MS' => 0, 'UWP' => 0, 'MK' => 0];
            $monthlyPurokCounts[$monthKey] = [];
        }
        // Puroks come from the Purok settings (segments such as 2a / 2b stay separate); a snapshot
        // saved before segments existed falls back to its Purok number.
        $purokSettings = Purok::query()->orderBy('purok_number')->orderBy('grupo_start')->orderBy('id')
            ->get(['id', 'purok_number', 'purok_name']);
        $purokKey = fn (MonthlyRecordSnapshot $snapshot): string => $snapshot->purok_id
            ? 'p'.$snapshot->purok_id
            : 'n'.(int) $snapshot->purok_number;
        foreach ($monthlySnapshots as $snapshot) {
            $monthKey = $snapshot->month->format('Y-m');
            $purok = $purokKey($snapshot);
            $monthlyPurokCounts[$monthKey][$purok] ??= ['MS' => 0, 'UWP' => 0, 'MK' => 0];
            foreach (['MS' => 'ms_count', 'UWP' => 'uwp_count', 'MK' => 'mk_count'] as $status => $column) {
                $quantity = (int) $snapshot->{$column};
                $monthlyPurokCounts[$monthKey][$purok][$status] += $quantity;
                $monthlyCounts[$monthKey][$status] += $quantity;
            }
            foreach (($snapshot->movement_counts ?? []) as $status => $codes) {
                foreach ($codes as $code => $count) {
                    $movementDetails[] = [
                        'month' => $monthKey,
                        'purok' => $purok,
                        'status' => $status,
                        'code' => $code,
                        'count' => (int) $count,
                    ];
                }
            }
        }
        $snapshotKeys = $monthlySnapshots->map($purokKey)->unique();
        $configured = $purokSettings
            ->map(fn (Purok $purok): array => ['id' => 'p'.$purok->id, 'label' => trim((string) $purok->purok_name) ?: 'Purok '.$purok->purok_number])
            ->filter(fn (array $purok): bool => $snapshotKeys->contains($purok['id']));
        $legacy = $snapshotKeys
            ->reject(fn (string $key): bool => $configured->contains('id', $key))
            ->map(fn (string $key): array => [
                'id' => $key,
                'label' => str_starts_with($key, 'n')
                    ? 'Purok '.substr($key, 1)
                    : 'Purok (removed setting #'.substr($key, 1).')',
            ]);
        $movementPuroks = $configured->concat($legacy)->values()->all();

        $registryStatuses = ['MS', 'UWP', 'MK'];
        $registryRows = array_filter($rows, fn (array $row): bool => in_array($row['status'], $registryStatuses, true));
        $registryRecords = $records->filter(fn (Record $record, int $key): bool => isset($registryRows[$key]));
        $registryPurokKeys = collect(array_map(fn (array $row): string => $row['purok'] ?: 'Unassigned', $registryRows))
            ->unique()
            ->sort(fn (string $left, string $right): int => $left === 'Unassigned'
                ? 1
                : ($right === 'Unassigned' ? -1 : strnatcasecmp($left, $right)))
            ->values();
        $registryRowsByPurok = [];
        foreach ($registryRows as $key => $row) {
            $registryRowsByPurok[$row['purok'] ?: 'Unassigned'][$key] = $row;
        }
        $registryPuroks = $registryPurokKeys->map(fn (string $purok): array => [
            'id' => $purok,
            'recordTotal' => count($registryRowsByPurok[$purok]),
        ])->all();
        $registryFields = [
            'Registry' => 'registry',
            'Gender' => 'gender',
            'Purok' => 'purok',
            'Grupo' => 'grupo',
            'Kapisanan' => 'kapisanan',
            'Baptism date' => 'date_of_baptism',
            'Evangelist' => 'evangelist',
            'Birthday' => 'date_of_birth',
        ];
        // A record's Grupo range comes from its Purok setting; it is looked up once per record.
        $grupoRanges = [];
        $grupoRange = function (int $key, array $row) use (&$grupoRanges, $purokSettingsByNumber): array {
            if (! isset($grupoRanges[$key])) {
                $grupo = $row['attributes']['grupo'];
                preg_match('/\d+/', $row['purok'], $purokNumber);
                $matchingPurokSettings = isset($purokNumber[0])
                    ? $purokSettingsByNumber->get((string) (int) $purokNumber[0])
                    : null;
                $purokSettingsForRecord = $matchingPurokSettings?->first(
                    fn (Purok $setting): bool => $setting->full_purok
                        || (is_numeric($grupo)
                            && (int) $grupo >= $setting->grupo_start
                            && (int) $grupo <= $setting->grupo_end)
                ) ?? $matchingPurokSettings?->first();
                $grupoRanges[$key] = [
                    $purokSettingsForRecord?->grupo_start ?? 1,
                    $purokSettingsForRecord?->grupo_end ?? 24,
                ];
            }

            return $grupoRanges[$key];
        };
        $registryReport = [];
        foreach ($registryFields as $label => $column) {
            $missing = [];
            $details = [];
            foreach ($registryPurokKeys as $purok) {
                $fieldDetails = [];
                foreach ($registryRowsByPurok[$purok] as $key => $row) {
                    // Dates are read raw: a stored date is never blank, just like its cast value.
                    $value = trim((string) $row['attributes'][$column]);
                    $isMissing = $column === 'registry'
                        ? in_array(mb_strtoupper($value), ['', 'RFID'], true)
                        : $value === '';
                    if (! $isMissing) {
                        continue;
                    }

                    $recordPurok = $row['purok'];
                    [$grupoMin, $grupoMax] = $grupoRange($key, $row);
                    $fieldDetails[] = [
                        'id' => $row['id'],
                        'name' => trim((string) $row['attributes']['full_name']) ?: 'Unnamed record',
                        'status' => $row['status'],
                        'field' => $column,
                        'purok_id' => $recordPurok ?: 'Unassigned',
                        'purok' => $recordPurok ?: 'Unassigned',
                        'can_edit_grupo' => $recordPurok !== '' && $recordPurok !== 'Unassigned',
                        'grupo_min' => $grupoMin,
                        'grupo_max' => $grupoMax,
                    ];
                }
                $missing[] = count($fieldDetails);
                $details[] = $fieldDetails;
            }
            $registryReport[] = [
                'label' => $label,
                'missing' => $missing,
                'details' => $details,
            ];
        }
        $reportFieldsCount = count($registryFields);
        $missingFieldsCount = array_sum(array_map(fn (array $field): int => array_sum($field['missing']), $registryReport));
        $registryRecordCount = $registryRecords->count();
        $registryCompletion = $registryRecordCount === 0 || $reportFieldsCount === 0
            ? 0
            : (($registryRecordCount * $reportFieldsCount - $missingFieldsCount) / ($registryRecordCount * $reportFieldsCount)) * 100;

        $globalVariables = GlobalVariable::query()->first();
        $r210EligibleRecords = $activeRecords->filter(
            fn (Record $record): bool => $eligibility->forRecord($record, $globalVariables)
        );
        $eligibleR210Count = $r210EligibleRecords->count();
        $endorsedR210Count = $r210EligibleRecords
            ->filter(fn (Record $record): bool => mb_strtoupper(trim((string) $record->r2_10_endorsement)) === 'ENDORSED')
            ->count();
        $r210Updating = $eligibleR210Count === 0 ? 0 : $endorsedR210Count / $eligibleR210Count * 100;
        $localeSNumber = trim((string) ($globalVariables?->s_number ?? ''));
        $localeSNumberTarget = is_numeric($localeSNumber) ? (float) $localeSNumber : 0.0;
        $localeActivePercentage = $localeSNumberTarget > 0
            ? $activeTotal / $localeSNumberTarget * 100
            : 0.0;

        // Manila's date (the app clock is UTC), at midnight like the stored baptism dates.
        $today = Carbon::parse(Carbon::now('Asia/Manila')->toDateString());
        $baptismCutoff = $today->copy()->subYear();
        $newBaptisees = $registryRecords
            ->filter(fn (Record $record): bool => $record->date_of_baptism !== null
                && $record->date_of_baptism->greaterThanOrEqualTo($baptismCutoff)
                && $record->date_of_baptism->lessThanOrEqualTo($today))
            ->sortByDesc(fn (Record $record): string => $record->date_of_baptism->format('Y-m-d'))
            ->map(fn (Record $record): array => [
                'name' => $record->full_name ?? '',
                'purok' => $record->purok ?? '',
                'grupo' => $record->grupo ?? '',
                'status' => mb_strtoupper(trim((string) $record->status)),
                'evangelist' => $record->evangelist ?? '',
                'baptized_at' => $record->date_of_baptism->format('Y-m-d'),
            ])
            ->values()
            ->all();

        return view('dashboard', [
            'user' => $user,
            'canEditRegistry' => $canEditRegistry,
            'dashboardMetrics' => [
                'recordCount' => $records->count(),
                'activeCount' => $activeTotal,
                'inactiveCount' => $inactiveTotal,
                'statuses' => $statusChart,
                'kalagayanCounts' => array_combine(
                    ['MS', 'MK', 'UWP'],
                    array_map(fn (string $status): int => $statusCounts[$status] ?? 0, ['MS', 'MK', 'UWP'])
                ),
                'activeStatuses' => $activeStatuses,
                'puroks' => $puroks,
                'gender' => $genderCounts,
                'genderRecords' => $genderRecords,
                'groupLabels' => $groupLabels,
                'groupCounts' => $groupCounts,
                'groupRecords' => $groupRecords,
                'monthLabels' => $monthLabels,
                'monthKeys' => $monthKeys->all(),
                'monthlyCounts' => array_values($monthlyCounts),
                'monthlyPurokCounts' => array_values($monthlyPurokCounts),
                'movementDetails' => $movementDetails,
                'movementPuroks' => $movementPuroks,
                'registryPuroks' => $registryPuroks,
                'registryReport' => $registryReport,
                'registryCompletion' => $registryCompletion,
                'registryRecordCount' => $registryRecordCount,
                'registryEncoded' => ($registryRecordCount * $reportFieldsCount) - $missingFieldsCount,
                'r210Updating' => $r210Updating,
                'localeSNumberTarget' => $localeSNumberTarget,
                'localeActivePercentage' => $localeActivePercentage,
            ],
            'newBaptisees' => $newBaptisees,
            'globalVariables' => $globalVariables,
        ]);
    }
}
