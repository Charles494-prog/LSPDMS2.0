<?php

namespace Tests\Feature;

use App\Models\Record;
use App\Services\R210Eligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManilaTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 07:30 in Manila is still 2026-10-24 23:30 in UTC, the app timezone.
        Carbon::setTestNow(Carbon::parse('2026-10-25 07:30', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_r210_entry_anniversary_counts_on_the_manila_date(): void
    {
        $eligibility = new R210Eligibility;
        $anniversaryToday = Record::query()->create(['full_name' => 'Anniversary Today', 'status' => 'MS', 'date_of_entry' => '2025-10-25']);
        $anniversaryTomorrow = Record::query()->create(['full_name' => 'Anniversary Tomorrow', 'status' => 'MS', 'date_of_entry' => '2025-10-26']);

        $this->assertTrue($eligibility->forRecord($anniversaryToday->fresh(), null));
        $this->assertFalse($eligibility->forRecord($anniversaryTomorrow->fresh(), null));
    }

    public function test_dashboard_new_baptisees_use_the_manila_date(): void
    {
        Record::query()->create(['full_name' => 'Baptized Today', 'status' => 'MS', 'date_of_baptism' => '2026-10-25']);
        Record::query()->create(['full_name' => 'Exactly One Year', 'status' => 'MS', 'date_of_baptism' => '2025-10-25']);
        Record::query()->create(['full_name' => 'Over One Year', 'status' => 'MS', 'date_of_baptism' => '2025-10-24']);
        Record::query()->create(['full_name' => 'Tomorrow', 'status' => 'MS', 'date_of_baptism' => '2026-10-26']);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('newBaptisees', fn (array $newBaptisees): bool => collect($newBaptisees)->pluck('name')->sort()->values()->all()
                === ['Baptized Today', 'Exactly One Year']);
    }

    public function test_records_update_counts_age_on_the_manila_date(): void
    {
        $turns18Today = Record::query()->create(['full_name' => 'Turns 18 Today', 'status' => 'MS']);
        $turns18Tomorrow = Record::query()->create(['full_name' => 'Turns 18 Tomorrow', 'status' => 'MS']);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $turns18Today), ['date_of_birth' => '2008-10-25'])
            ->assertOk();
        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $turns18Tomorrow), ['date_of_birth' => '2008-10-26'])
            ->assertOk();

        $this->assertSame('K', $turns18Today->fresh()->kapisanan);
        $this->assertSame('binhi', $turns18Tomorrow->fresh()->kapisanan);
    }
}
