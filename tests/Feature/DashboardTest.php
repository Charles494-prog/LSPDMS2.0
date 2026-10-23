<?php

namespace Tests\Feature;

use App\Models\GlobalVariable;
use App\Models\MonthlyRecordSnapshot;
use App\Models\Purok;
use App\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_and_recent_baptisees_come_from_database_records(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Purok 1',
            'grupo_start' => 1,
            'grupo_end' => 24,
        ]);
        Record::query()->create([
            'full_name' => 'Active Member',
            'registry' => 'REG-1001',
            'gender' => 'B',
            'purok' => '1',
            'grupo' => '3',
            'kapisanan' => 'K',
            'date_of_baptism' => now()->subDays(20)->toDateString(),
            'evangelist' => 'Evangelist One',
            'date_of_entry' => now()->toDateString(),
            'r2_10_endorsement' => 'ENDORSED',
            'status' => 'MS',
        ]);
        Record::query()->create([
            'full_name' => 'Eligible Without Endorsement',
            'registry' => 'REG-1003',
            'date_of_entry' => now()->subYears(2)->toDateString(),
            'status' => 'MS',
        ]);
        Record::query()->create([
            'full_name' => 'No Check Needed',
            'registry' => 'REG-1004',
            'date_of_entry' => now()->subYears(2)->toDateString(),
            'r2_10_endorsement' => 'ENDORSED',
            'status' => 'MS',
        ]);
        Record::query()->create([
            'full_name' => 'Not Eligible but Endorsed',
            'registry' => 'REG-1005',
            'date_of_entry' => now()->subYears(2)->toDateString(),
            'r2_10_endorsement' => 'ENDORSED',
            'status' => 'MS',
        ]);
        Record::query()->create([
            'full_name' => 'Inactive Member',
            'registry' => 'REG-1002',
            'gender' => 'L',
            'purok' => '2',
            'r2_10_endorsement' => 'ENDORSED',
            'status' => 'INACTIVE',
        ]);
        GlobalVariable::query()->create([
            'locale' => 'Test Locale',
            'lcode' => 'L-001',
            's_number' => '8',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Breakdown per CFO group')
            ->assertSee('Kalagayan per CFO group')
            ->assertDontSee('id="pie-chart"', false)
            ->assertDontSee('id="pie-legend"', false)
            ->assertSee('class="kap" id="cfo-a-host"', false)
            ->assertSee('Chart.defaults.animation = false', false)
            ->assertSee('grid-template-columns: minmax(100px, 150px)', false)
            ->assertSee("el('button', 'purok-row')", false)
            ->assertDontSee('id="purok-chart"', false)
            ->assertSee('id="cfo-status-seg"', false)
            ->assertSee('id="cfo-group-seg"', false)
            ->assertSee("['ALL', ...CFO_STATUS_KEYS]", false)
            ->assertSee("['ALL', ...CFO_GROUPS]", false)
            ->assertSee('purok-row-name', false)
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame(5, $metrics['recordCount']);
                $this->assertSame(4, $metrics['activeCount']);
                $this->assertSame(1, $metrics['inactiveCount']);
                $this->assertSame(['MS'], $metrics['activeStatuses']);
                $this->assertSame(1, $metrics['puroks'][0]['id']);
                $this->assertSame('Purok 1', $metrics['puroks'][0]['name']);
                $this->assertSame(1, $metrics['puroks'][0]['counts']['MS']);
                $this->assertSame(1, $metrics['gender']['female']['MS']);
                $this->assertSame(1, $metrics['groupCounts']['MS']['Kadiwa']);
                $this->assertCount(10, $metrics['monthLabels']);
                $this->assertSame('Oct 2025', $metrics['monthLabels'][9]);
                $this->assertSame(331, $metrics['monthlyCounts'][9]['MS']);
                $this->assertSame(51, $metrics['monthlyPurokCounts'][9]['1']['MS']);
                $this->assertSame(10, $metrics['registryEncoded']);
                $this->assertEqualsWithDelta(66.66666666666666, $metrics['r210Updating'], 0.000001);
                $this->assertSame(8.0, $metrics['localeSNumberTarget']);
                $this->assertSame(50.0, $metrics['localeActivePercentage']);

                return true;
            })
            ->assertViewHas('newBaptisees', fn (array $newBaptisees): bool => count($newBaptisees) === 1
                && $newBaptisees[0]['name'] === 'Active Member');
    }

    public function test_registry_monitoring_counts_only_ms_uwp_mk_and_drills_into_missing_fields(): void
    {
        foreach ([
            ['MS member missing registry', 'RFID', 'MS', null],
            ['UWP member missing registry', null, 'UWP', null],
            ['MK member complete', 'REG-2003', 'MK', now()->subYears(20)->toDateString()],
            ['Inactive missing everything', null, 'INACTIVE', null],
        ] as [$name, $registry, $status, $birthday]) {
            Record::query()->create([
                'full_name' => $name,
                'registry' => $registry,
                'gender' => 'B',
                'purok' => '1',
                'grupo' => '3',
                'kapisanan' => 'K',
                'date_of_baptism' => now()->subMonths(2)->toDateString(),
                'evangelist' => 'Evangelist One',
                'date_of_birth' => $birthday,
                'status' => $status,
            ]);
        }

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('MS, UWP, and MK records only.')
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame(3, $metrics['registryRecordCount']);
                $this->assertSame([['id' => '1', 'recordTotal' => 3]], $metrics['registryPuroks']);

                $labels = array_column($metrics['registryReport'], 'label');
                $this->assertContains('Birthday', $labels);
                $this->assertNotContains('Entry date', $labels);

                $registryRow = collect($metrics['registryReport'])->firstWhere('label', 'Registry');
                $this->assertSame([2], $registryRow['missing']);
                $this->assertSame(
                    ['MS member missing registry', 'UWP member missing registry'],
                    array_column($registryRow['details'][0], 'name')
                );
                $this->assertSame('registry', $registryRow['details'][0][0]['field']);
                $this->assertTrue($registryRow['details'][0][0]['can_edit_grupo']);

                $birthdayRow = collect($metrics['registryReport'])->firstWhere('label', 'Birthday');
                $this->assertSame([2], $birthdayRow['missing']);
                $this->assertEqualsWithDelta(83.33333333333334, $metrics['registryCompletion'], 0.000001);

                return true;
            })
            ->assertViewHas('newBaptisees', fn (array $newBaptisees): bool => count($newBaptisees) === 3
                && collect($newBaptisees)->pluck('status')->sort()->values()->all() === ['MK', 'MS', 'UWP']);
    }

    public function test_dashboard_charts_use_only_supported_kalagayan_and_kapisanan_values(): void
    {
        foreach ([
            ['MS Binhi', 'MS', 'Binhi'],
            ['MK Buklod', 'MK', 'Buklod'],
            ['UWP Kadiwa', 'UWP', 'K'],
            ['UWP unclassified', 'UWP', 'Other'],
            ['Inactive Binhi', 'INACTIVE', 'Binhi'],
        ] as [$name, $status, $kapisanan]) {
            Record::query()->create([
                'full_name' => $name,
                'kapisanan' => $kapisanan,
                'status' => $status,
            ]);
        }

        $this->withSession([
            'login_user' => ['id' => 1],
            'status' => 'Dashboard changes saved.',
        ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="appFlashToast"', false)
            ->assertSee('Dashboard changes saved.')
            ->assertSee('safe-area-inset-left', false)
            ->assertSee('.ot-chart { position: relative; height: 420px;', false)
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame(['MS', 'MK', 'UWP'], array_keys($metrics['kalagayanCounts']));
                $this->assertSame(['MS' => 1, 'MK' => 1, 'UWP' => 2], $metrics['kalagayanCounts']);
                $this->assertSame(['Binhi', 'Kadiwa', 'Buklod'], $metrics['groupLabels']);
                $this->assertSame([
                    'MS' => ['Binhi' => 1, 'Kadiwa' => 0, 'Buklod' => 0],
                    'MK' => ['Binhi' => 0, 'Kadiwa' => 0, 'Buklod' => 1],
                    'UWP' => ['Binhi' => 0, 'Kadiwa' => 1, 'Buklod' => 0],
                ], $metrics['groupCounts']);
                $this->assertSame(
                    [['id' => 1, 'name' => 'MS Binhi', 'status' => 'MS']],
                    $metrics['groupRecords']['MS']['Binhi']
                );
                $this->assertSame(
                    [['id' => 2, 'name' => 'MK Buklod', 'status' => 'MK']],
                    $metrics['groupRecords']['MK']['Buklod']
                );
                $this->assertSame([], $metrics['groupRecords']['UWP']['Binhi']);

                return true;
            });
    }

    public function test_active_records_by_purok_uses_configured_puroks_and_includes_empty_puroks(): void
    {
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Riverside',
            'grupo_start' => 1,
            'grupo_end' => 12,
        ]);
        Purok::query()->create([
            'purok_number' => 5,
            'purok_name' => 'Upland',
            'grupo_start' => 1,
            'grupo_end' => 12,
        ]);
        Record::query()->create([
            'full_name' => 'Riverside Member',
            'purok' => '2',
            'status' => 'MS',
        ]);
        Record::query()->create([
            'full_name' => 'Unconfigured Purok Member',
            'purok' => '7',
            'status' => 'MK',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame([
                    ['id' => 1, 'number' => '2', 'name' => 'Riverside', 'counts' => ['MS' => 1, 'MK' => 0], 'total' => 1, 'recordTotal' => 1],
                    ['id' => 2, 'number' => '5', 'name' => 'Upland', 'counts' => ['MS' => 0, 'MK' => 0], 'total' => 0, 'recordTotal' => 0],
                ], $metrics['puroks']);

                return true;
            });
    }

    public function test_active_records_by_purok_splits_shared_header_by_grupo_range(): void
    {
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2a',
            'grupo_start' => 1,
            'grupo_end' => 6,
        ]);
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2b',
            'grupo_start' => 7,
            'grupo_end' => 10,
        ]);
        Record::query()->create(['full_name' => 'A Member', 'purok' => '2', 'grupo' => '3', 'status' => 'MS']);
        Record::query()->create(['full_name' => 'B Member', 'purok' => '2', 'grupo' => '8', 'status' => 'UWP']);
        Record::query()->create(['full_name' => 'No Grupo', 'purok' => '2', 'status' => 'MK']);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame([
                    ['id' => 1, 'number' => '2', 'name' => 'Purok 2a', 'counts' => ['MS' => 1, 'UWP' => 0, 'MK' => 0], 'total' => 1, 'recordTotal' => 1],
                    ['id' => 2, 'number' => '2', 'name' => 'Purok 2b', 'counts' => ['MS' => 0, 'UWP' => 1, 'MK' => 0], 'total' => 1, 'recordTotal' => 1],
                ], $metrics['puroks']);

                return true;
            });
    }

    public function test_active_records_by_purok_is_empty_when_no_puroks_are_configured(): void
    {
        Record::query()->create([
            'full_name' => 'Member in Unconfigured Purok',
            'purok' => '1',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No Puroks are configured yet.', false)
            ->assertViewHas('dashboardMetrics', fn (array $metrics): bool => $metrics['puroks'] === []);
    }

    public function test_active_gender_dashboard_drilldown_lists_matching_records_only(): void
    {
        $sister = Record::query()->create([
            'full_name' => 'Active Sister',
            'gender' => 'B',
            'status' => 'MS',
            'purok' => '2',
        ]);
        $brother = Record::query()->create([
            'full_name' => 'Active Brother',
            'gender' => 'L',
            'status' => 'MK',
            'purok' => '3',
        ]);
        Record::query()->create([
            'full_name' => 'Inactive Sister',
            'gender' => 'B',
            'status' => 'INACTIVE',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="gender-drill" data-gender="female"', false)
            ->assertSee('id="gender-records-modal"', false)
            ->assertViewHas('dashboardMetrics', function (array $metrics) use ($sister, $brother): bool {
                $this->assertSame([
                    ['id' => $sister->id, 'name' => 'Active Sister', 'status' => 'MS', 'purok' => '2'],
                ], $metrics['genderRecords']['female']);
                $this->assertSame([
                    ['id' => $brother->id, 'name' => 'Active Brother', 'status' => 'MK', 'purok' => '3'],
                ], $metrics['genderRecords']['male']);

                return true;
            });
    }

    public function test_records_over_time_includes_imported_monthly_counts_and_movement_codes(): void
    {
        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Monthly MS, UWP, and MK snapshots.', false)
            ->assertSee('id="ot-current"', false)
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame('Jan 2025', $metrics['monthLabels'][0]);
                $this->assertSame('Oct 2025', $metrics['monthLabels'][9]);
                $this->assertCount(6, $metrics['monthlyPurokCounts'][0]);
                $this->assertSame(['MS', 'UWP', 'MK'], array_keys($metrics['monthlyCounts'][0]));
                $this->assertSame(67 + 27 + 84 + 92 + 39 + 114, $metrics['monthlyCounts'][0]['MS']);
                $this->assertContains([
                    'month' => '2025-10',
                    'purok' => 2,
                    'status' => 'MS',
                    'code' => 'DD',
                    'count' => 1,
                ], $metrics['movementDetails']);
                $this->assertContains([
                    'month' => '2025-09',
                    'purok' => 5,
                    'status' => 'UWP',
                    'code' => 'DD',
                    'count' => 1,
                ], $metrics['movementDetails']);
                $this->assertSame([], array_values(array_filter(
                    $metrics['movementDetails'],
                    fn (array $movement): bool => $movement['month'] === '2025-04'
                )));

                return true;
            });
    }

    public function test_records_over_time_picks_up_later_months_inserted_into_snapshot_table(): void
    {
        MonthlyRecordSnapshot::query()->create([
            'month' => '2025-11-01',
            'purok_number' => 11,
            'ms_count' => 25,
            'uwp_count' => 4,
            'mk_count' => 3,
            'movement_counts' => ['MS' => ['B' => 2]],
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nov 2025')
            ->assertSee('historyStatuses.map(dataset)', false)
            ->assertSee('otChart.data.datasets[i].data = totals[status]', false)
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame('Nov 2025', $metrics['monthLabels'][10]);
                $this->assertSame(25, $metrics['monthlyCounts'][10]['MS']);
                $this->assertSame(25, $metrics['monthlyPurokCounts'][10]['11']['MS']);
                $this->assertContains(11, $metrics['movementPuroks']);
                $this->assertContains([
                    'month' => '2025-11',
                    'purok' => 11,
                    'status' => 'MS',
                    'code' => 'B',
                    'count' => 2,
                ], $metrics['movementDetails']);

                return true;
            });
    }

    public function test_months_without_a_snapshot_are_skipped_not_zero(): void
    {
        // Imported history ends Oct 2025; the next snapshot is Oct 2026 (Nov 2025 - Sep 2026 were never recorded).
        MonthlyRecordSnapshot::query()->create([
            'month' => '2026-10-01',
            'purok_number' => 11,
            'ms_count' => 0,
            'uwp_count' => 0,
            'mk_count' => 0,
            'movement_counts' => [],
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                // Months with no snapshot are left out: Oct 2025 is followed directly by Oct 2026.
                $this->assertSame(11, count($metrics['monthLabels']));
                $this->assertSame('Oct 2025', $metrics['monthLabels'][9]);
                $this->assertSame('Oct 2026', $metrics['monthLabels'][10]);
                $this->assertNotContains('Nov 2025', $metrics['monthLabels']);
                $this->assertNotContains(null, $metrics['monthlyPurokCounts']);
                // A real snapshot of zero stays a verified zero.
                $this->assertSame(0, $metrics['monthlyPurokCounts'][10]['11']['MS']);

                return true;
            });
    }

    public function test_records_over_time_sums_segment_snapshots_for_the_same_purok_number(): void
    {
        $purok2a = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2A',
            'grupo_start' => 1,
            'grupo_end' => 6,
        ]);
        $purok2b = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2B',
            'grupo_start' => 7,
            'grupo_end' => 11,
        ]);
        foreach ([[$purok2a, 4], [$purok2b, 5]] as [$purok, $count]) {
            MonthlyRecordSnapshot::query()->create([
                'month' => '2025-11-01',
                'purok_number' => 2,
                'purok_id' => $purok->id,
                'ms_count' => $count,
                'uwp_count' => 0,
                'mk_count' => 0,
                'movement_counts' => [],
            ]);
        }

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame(9, $metrics['monthlyCounts'][10]['MS']);
                $this->assertSame(9, $metrics['monthlyPurokCounts'][10]['2']['MS']);

                return true;
            });
    }

    public function test_monthly_snapshot_migration_consolidates_legacy_movement_codes(): void
    {
        $snapshot = MonthlyRecordSnapshot::query()->create([
            'month' => '2025-11-01',
            'purok_number' => 2,
            'ms_count' => 25,
            'uwp_count' => 4,
            'mk_count' => 3,
            'movement_counts' => [
                'MS' => ['B' => 4, 'B-1' => 2, 'B-2' => 3, 'D-1' => 1, 'D-2' => 2, 'DD' => 3],
            ],
        ]);

        $migration = require database_path('migrations/2026_10_01_120300_normalize_monthly_snapshot_movement_codes.php');
        $migration->up();

        $this->assertSame([
            'MS' => ['B' => 9, 'D' => 1, 'DD' => 5],
        ], $snapshot->fresh()->movement_counts);
    }

    public function test_legacy_e_movement_codes_are_consolidated_and_counts_are_added(): void
    {
        $snapshot = MonthlyRecordSnapshot::query()->create([
            'month' => '2025-11-01',
            'purok_number' => 2,
            'ms_count' => 25,
            'uwp_count' => 4,
            'mk_count' => 3,
            'movement_counts' => [
                'MS' => ['E' => 2, 'E-1' => 1, 'E-2' => 3, 'E-3' => 4],
            ],
        ]);

        $migration = require database_path('migrations/2026_10_01_120400_normalize_legacy_e_movement_codes.php');
        $migration->up();

        $this->assertSame(['MS' => ['E' => 10]], $snapshot->fresh()->movement_counts);
    }

    public function test_registry_details_include_field_actions_and_the_current_puroks_grupo_range(): void
    {
        Purok::query()->create([
            'purok_number' => 2,
            'grupo_start' => 6,
            'grupo_end' => 12,
        ]);
        Record::query()->create([
            'full_name' => 'Missing Grupo Member',
            'status' => 'MS',
            'purok' => '2',
            'grupo' => null,
        ]);
        Record::query()->create([
            'full_name' => 'Missing Purok Member',
            'status' => 'UWP',
            'purok' => null,
            'grupo' => null,
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $grupo = collect($metrics['registryReport'])->firstWhere('label', 'Grupo');
                $grupoEntry = collect($grupo['details'])->flatten(1)->firstWhere('name', 'Missing Grupo Member');
                $this->assertSame('grupo', $grupoEntry['field']);
                $this->assertTrue($grupoEntry['can_edit_grupo']);
                $this->assertSame(6, $grupoEntry['grupo_min']);
                $this->assertSame(12, $grupoEntry['grupo_max']);

                $purok = collect($metrics['registryReport'])->firstWhere('label', 'Purok');
                $purokEntry = collect($purok['details'])->flatten(1)->firstWhere('name', 'Missing Purok Member');
                $this->assertSame('purok', $purokEntry['field']);
                $this->assertFalse($purokEntry['can_edit_grupo']);

                return true;
            });
    }

    public function test_registry_monitoring_is_view_only_without_inquire_modify_permission(): void
    {
        $user = \App\Models\User::query()->create([
            'gmail' => 'dashboard-only@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'dashboard_allow' => true,
            'Inquire_Allow' => false,
        ]);
        $record = Record::query()->create([
            'full_name' => 'Missing Registry Member',
            'registry' => null,
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-can-edit-registry="false"', false);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $record), ['registry_number' => 'ABCDEFGHIJKLM'])
            ->assertForbidden();
    }

    public function test_dashboard_renders_without_records_and_does_not_use_demo_members(): void
    {
        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('const DASHBOARD = {', false)
            ->assertSee('LSP / Locale')
            ->assertSee('id="cfo-group-modal"', false)
            ->assertSee('id="registry-details-modal"', false)
            ->assertSee('id="registry-edit-modal"', false)
            ->assertSee('Click any count to view the records.')
            ->assertSee('No newly baptized records were found in the past year.')
            ->assertSee('Filtered by kalagayan: MS, UWP, and MK.')
            ->assertDontSee('Maria Lourdes Santos')
            ->assertDontSee('Long-time Member (excluded)');
    }
}
