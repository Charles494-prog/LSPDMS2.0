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

        $statusLabels = $records
            ->map(fn (Record $record): string => mb_strtoupper(trim((string) $record->status)))
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
        $hasUnspecifiedStatus = false;
        foreach ($records as $record) {
            $status = mb_strtoupper(trim((string) $record->status));
            if ($status !== '' && array_key_exists($status, $statusCounts)) {
                $statusCounts[$status]++;
            } elseif ($status === '') {
                $hasUnspecifiedStatus = true;
            }
        }
        $statusChart = array_map(fn (string $status): array => [
            'code' => $status,
            'label' => $status,
            'count' => $statusCounts[$status],
        ], $statusLabels);
        if ($hasUnspecifiedStatus) {
            $statusChart[] = [
                'code' => 'UNSPECIFIED',
                'label' => 'Unspecified',
                'count' => $records->filter(fn (Record $record): bool => trim((string) $record->status) === '')->count(),
            ];
        }

        $activeStatuses = array_values(array_filter($statusLabels, fn (string $status): bool => $status !== 'INACTIVE'));
        $activeRecords = $records->filter(fn (Record $record): bool => trim((string) $record->status) !== ''
            && mb_strtoupper(trim((string) $record->status)) !== 'INACTIVE');
        $activeTotal = $activeRecords->count();
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
        $puroks = $purokSettings->map(function (Purok $purok) use (
            $activeRecords,
            $activeStatuses,
            $normalizePurok,
            $purokSettingsByNumber
        ): array {
            $purokNumber = (string) $purok->purok_number;
            $isSplitHeader = $purokSettingsByNumber->get($purokNumber)->count() > 1;
            $members = $activeRecords->filter(
                fn (Record $record): bool => $normalizePurok($record->purok) === $purokNumber
                    && (! $isSplitHeader || $purok->full_purok || (
                        is_numeric($record->grupo)
                        && (int) $record->grupo >= $purok->grupo_start
                        && (int) $record->grupo <= $purok->grupo_end
                    ))
            );
            $name = trim((string) $purok->purok_name) ?: 'Purok '.$purokNumber;
            $counts = [];
            foreach ($activeStatuses as $status) {
                $counts[$status] = $members->filter(fn (Record $record): bool => mb_strtoupper(trim((string) $record->status)) === $status)->count();
            }

            return [
                'id' => $purok->id,
                'number' => $purokNumber,
                'name' => $name,
                'counts' => $counts,
                'total' => array_sum($counts),
                'recordTotal' => $members->count(),
            ];
        })->all();

        $genderCounts = [];
        $genderRecords = [];
        foreach (['female' => 'B', 'male' => 'L'] as $gender => $code) {
            $genderCounts[$gender] = array_fill_keys($activeStatuses, 0);
            $genderRecords[$gender] = [];
            foreach ($activeRecords as $record) {
                if (mb_strtoupper(trim((string) $record->gender)) === $code) {
                    $status = mb_strtoupper(trim((string) $record->status));
                    $genderCounts[$gender][$status]++;
                    $genderRecords[$gender][] = [
                        'id' => $record->id,
                        'name' => trim((string) $record->full_name) ?: 'Unnamed record',
                        'status' => $status,
                        'purok' => trim((string) $record->purok) ?: 'Unassigned',
                    ];
                }
            }
        }

        $groupLabels = ['Binhi', 'Kadiwa', 'Buklod'];
        $groupStatuses = ['MS', 'MK', 'UWP'];
        $groupCounts = array_fill_keys($groupStatuses, array_fill_keys($groupLabels, 0));
        $groupRecords = array_fill_keys($groupStatuses, array_fill_keys($groupLabels, []));
        foreach ($activeRecords as $record) {
            $status = mb_strtoupper(trim((string) $record->status));
            if (! array_key_exists($status, $groupCounts)) {
                continue;
            }

            $group = match (mb_strtolower(trim((string) $record->kapisanan))) {
                'binhi' => 'Binhi',
                'k', 'kadiwa' => 'Kadiwa',
                'b', 'buklod' => 'Buklod',
                default => null,
            };
            if ($group !== null) {
                $groupCounts[$status][$group]++;
                $groupRecords[$status][$group][] = [
                    'id' => $record->id,
                    'name' => trim((string) $record->full_name) ?: 'Unnamed record',
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
        $registryRecords = $records->filter(fn (Record $record): bool => in_array(
            mb_strtoupper(trim((string) $record->status)),
            $registryStatuses,
            true
        ));
        $registryPurokKeys = $registryRecords
            ->map(fn (Record $record): string => trim((string) $record->purok) ?: 'Unassigned')
            ->unique()
            ->sort(fn (string $left, string $right): int => $left === 'Unassigned'
                ? 1
                : ($right === 'Unassigned' ? -1 : strnatcasecmp($left, $right)))
            ->values();
        $registryPuroks = $registryPurokKeys->map(fn (string $purok): array => [
            'id' => $purok,
            'recordTotal' => $registryRecords->filter(
                fn (Record $record): bool => (trim((string) $record->purok) ?: 'Unassigned') === $purok
            )->count(),
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
        $registryReport = [];
        foreach ($registryFields as $label => $column) {
            $isMissing = fn (Record $record): bool => $column === 'registry'
                ? in_array(mb_strtoupper(trim((string) $record->{$column})), ['', 'RFID'], true)
                : trim((string) $record->{$column}) === '';
            $fieldDetails = $registryPurokKeys->map(fn (string $purok): array => $registryRecords
                ->filter(fn (Record $record): bool => (trim((string) $record->purok) ?: 'Unassigned') === $purok
                    && $isMissing($record))
                ->map(function (Record $record) use ($column, $purokSettingsByNumber): array {
                    $recordPurok = trim((string) $record->purok);
                    preg_match('/\d+/', $recordPurok, $purokNumber);
                    $matchingPurokSettings = isset($purokNumber[0])
                        ? $purokSettingsByNumber->get((string) (int) $purokNumber[0])
                        : null;
                    $purokSettingsForRecord = $matchingPurokSettings?->first(
                        fn (Purok $setting): bool => $setting->full_purok
                            || (is_numeric($record->grupo)
                                && (int) $record->grupo >= $setting->grupo_start
                                && (int) $record->grupo <= $setting->grupo_end)
                    ) ?? $matchingPurokSettings?->first();

                    return [
                        'id' => $record->id,
                        'name' => trim((string) $record->full_name) ?: 'Unnamed record',
                        'status' => mb_strtoupper(trim((string) $record->status)),
                        'field' => $column,
                        'purok_id' => $recordPurok ?: 'Unassigned',
                        'purok' => $recordPurok ?: 'Unassigned',
                        'can_edit_grupo' => $recordPurok !== '' && $recordPurok !== 'Unassigned',
                        'grupo_min' => $purokSettingsForRecord?->grupo_start ?? 1,
                        'grupo_max' => $purokSettingsForRecord?->grupo_end ?? 24,
                    ];
                })
                ->values()
                ->all());
            $registryReport[] = [
                'label' => $label,
                'missing' => $registryPurokKeys->map(fn (string $purok): int => $registryRecords
                    ->filter(fn (Record $record): bool => (trim((string) $record->purok) ?: 'Unassigned') === $purok
                        && $isMissing($record))
                    ->count())->all(),
                'details' => $fieldDetails->all(),
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
