<?php

namespace Tests\Feature;

use App\Models\MonthlyRecordSnapshot;
use App\Services\MonthlyResumenReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResumenBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_month_inside_the_history_gap_reports_a_missing_baseline_instead_of_zero(): void
    {
        MonthlyRecordSnapshot::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00', 'Asia/Manila'));

        $report = app(MonthlyResumenReport::class)->generate(Carbon::parse('2026-05-01'));

        $this->assertTrue($report['baseline_missing']);
        $this->assertSame('missing', $report['baseline_source']);
    }

    public function test_previous_month_snapshot_is_used_as_the_baseline(): void
    {
        MonthlyRecordSnapshot::query()->delete();
        MonthlyRecordSnapshot::query()->create([
            'month' => '2026-04-01',
            'purok_number' => 1,
            'ms_count' => 10,
            'uwp_count' => 2,
            'mk_count' => 0,
            'movement_counts' => [],
        ]);

        $report = app(MonthlyResumenReport::class)->generate(Carbon::parse('2026-05-01'));

        $this->assertFalse($report['baseline_missing']);
        $this->assertSame('previous_snapshot', $report['baseline_source']);
        $this->assertSame(10, $report['totals']['ms']['A']);
    }

    public function test_current_month_without_snapshots_uses_live_records_in_manila_time(): void
    {
        MonthlyRecordSnapshot::query()->delete();
        // 2026-10-31 20:00 UTC is already November 1 in Manila.
        Carbon::setTestNow(Carbon::parse('2026-10-31 20:00', 'UTC'));

        $report = app(MonthlyResumenReport::class)->generate(Carbon::parse('2026-11-01'));

        $this->assertSame('live_records', $report['baseline_source']);
    }
}
