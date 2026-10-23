<?php

namespace Tests\Unit;

use App\Models\GlobalVariable;
use App\Models\Record;
use App\Services\R210Eligibility;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression matrix for the single R2-10 eligibility rule. The behaviour is
 * pinned as it is today; any intended change must update these cases.
 */
class R210EligibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-23 00:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function entryYearCases(): array
    {
        $cases = [];
        foreach (['MS', 'UWP'] as $status) {
            foreach ([1, 2, 3] as $years) {
                $anniversary = Carbon::parse('2026-10-23')->subYears($years);
                $cases["{$status} {$years}y exactly on the anniversary"] = [$status, $years, $anniversary->toDateString(), true];
                $cases["{$status} {$years}y one day before the anniversary"] = [$status, $years, $anniversary->copy()->addDay()->toDateString(), false];
                $cases["{$status} {$years}y one day after the anniversary"] = [$status, $years, $anniversary->copy()->subDay()->toDateString(), true];
            }
        }
        foreach ([1, 2, 3] as $years) {
            $cases["MK is exempt even with {$years}y and a fresh entry"] = ['MK', $years, '2026-10-22', true];
        }
        $cases['MK is exempt without an entry date'] = ['MK', 3, null, true];
        $cases['MS without an entry date fails'] = ['MS', 1, null, false];
        $cases['INACTIVE is never eligible'] = ['INACTIVE', 1, '2000-01-01', false];
        $cases['lower-case status is normalised'] = ['ms', 1, '2020-01-01', true];

        return $cases;
    }

    #[DataProvider('entryYearCases')]
    public function test_minimum_years_in_the_system(string $status, int $years, ?string $entry, bool $expected): void
    {
        $record = $this->record($status, $entry, '2020-01-01');
        $rules = $this->rules($years, false, null);

        $this->assertSame($expected, app(R210Eligibility::class)->forRecord($record, $rules));
    }

    public function test_leap_day_entry_dates(): void
    {
        $eligibility = app(R210Eligibility::class);
        $rules = $this->rules(1, false, null);

        // Feb 29 2024 + 1 year rolls to Mar 1 2025 (Carbon overflow), so it is eligible from Mar 1 2025.
        Carbon::setTestNow(Carbon::parse('2025-02-28'));
        $this->assertFalse($eligibility->forRecord($this->record('MS', '2024-02-29', null), $rules));
        Carbon::setTestNow(Carbon::parse('2025-03-01'));
        $this->assertTrue($eligibility->forRecord($this->record('MS', '2024-02-29', null), $rules));
        // Four years later the anniversary exists again.
        Carbon::setTestNow(Carbon::parse('2028-02-29'));
        $this->assertTrue($eligibility->forRecord($this->record('UWP', '2024-02-29', null), $this->rules(3, false, null)));
        $this->assertFalse($eligibility->forRecord($this->record('UWP', '2025-03-01', null), $this->rules(3, false, null)));
    }

    public static function baptismCases(): array
    {
        return [
            'toggle off ignores a missing baptism date' => [false, '2022-12-31', null, true],
            'toggle off ignores a late baptism date' => [false, '2022-12-31', '2024-05-05', true],
            'baptised on the cutoff passes' => [true, '2022-12-31', '2022-12-31', true],
            'baptised before the cutoff passes' => [true, '2022-12-31', '2022-12-30', true],
            'baptised after the cutoff fails' => [true, '2022-12-31', '2023-01-01', false],
            'missing baptism date fails only when enforced' => [true, '2022-12-31', null, false],
            'enforced without a cutoff date fails' => [true, null, '2020-01-01', false],
            'leap-day cutoff boundary' => [true, '2024-02-29', '2024-02-29', true],
        ];
    }

    #[DataProvider('baptismCases')]
    public function test_independent_baptism_cutoff(bool $enforced, ?string $cutoff, ?string $baptism, bool $expected): void
    {
        $record = $this->record('MS', '2020-01-01', $baptism);

        $this->assertSame($expected, app(R210Eligibility::class)->forRecord($record, $this->rules(1, $enforced, $cutoff)));
    }

    public function test_baptism_cutoff_never_overrides_the_entry_year_rule(): void
    {
        $record = $this->record('MS', '2026-06-01', '2010-01-01');

        $this->assertFalse(app(R210Eligibility::class)->forRecord($record, $this->rules(1, true, '2022-12-31')));
    }

    public function test_missing_settings_default_to_one_year_without_a_baptism_rule(): void
    {
        $eligibility = app(R210Eligibility::class);

        $this->assertTrue($eligibility->forRecord($this->record('MS', '2025-10-23', null), null));
        $this->assertFalse($eligibility->forRecord($this->record('MS', '2025-10-24', null), null));
    }

    private function record(string $status, ?string $entry, ?string $baptism): Record
    {
        return new Record([
            'status' => $status,
            'date_of_entry' => $entry,
            'date_of_baptism' => $baptism,
        ]);
    }

    private function rules(int $years, bool $enforceBaptism, ?string $cutoff): GlobalVariable
    {
        return new GlobalVariable([
            'entry_years_requirement' => $years,
            'dateofbaptism_requirement_boolean' => $enforceBaptism,
            'baptism_requirement' => $cutoff,
        ]);
    }
}
