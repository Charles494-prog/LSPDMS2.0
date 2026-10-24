<?php

namespace Tests\Feature;

use App\Models\MonthlyMovementSession;
use App\Models\Purok;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManilaDateValidationTest extends TestCase
{
    use RefreshDatabase;

    private const MANILA_TODAY = '2026-10-25';

    private const MANILA_TOMORROW = '2026-10-26';

    protected function setUp(): void
    {
        parent::setUp();
        // 07:30 in Manila is still the previous day (23:30) in UTC, the app timezone.
        Carbon::setTestNow(Carbon::parse(self::MANILA_TODAY.' 07:30:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_records_update_accepts_the_manila_date_of_today_and_rejects_tomorrow(): void
    {
        $record = Record::query()->create(['full_name' => 'Early Morning', 'status' => 'MS']);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), [
                'date_of_baptism' => self::MANILA_TODAY,
                'date_of_birth' => self::MANILA_TODAY,
            ])
            ->assertOk();
        $this->assertSame(self::MANILA_TODAY, $record->fresh()->date_of_baptism->format('Y-m-d'));

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['date_of_baptism' => self::MANILA_TOMORROW])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_of_baptism');
    }

    public function test_delistment_date_updates_accept_the_manila_date_of_today(): void
    {
        $record = Record::query()->create(['full_name' => 'Early Morning', 'status' => 'UWP', 'date_of_entry' => '2020-01-01']);
        $session = ['login_user' => ['id' => 1, 'first_name' => 'Charles', 'last_name' => 'Galvan']];

        $this->withSession($session)
            ->putJson(route('de-listment.update-dates'), ['changes' => [['id' => $record->id, 'baptism' => self::MANILA_TODAY]]])
            ->assertOk();
        $this->assertSame(self::MANILA_TODAY, $record->fresh()->date_of_baptism->format('Y-m-d'));

        $this->withSession($session)
            ->putJson(route('de-listment.update-dates'), ['changes' => [['id' => $record->id, 'baptism' => self::MANILA_TOMORROW]]])
            ->assertUnprocessable();
    }

    public function test_delistment_mk_add_accepts_the_manila_date_of_today(): void
    {
        $user = User::query()->create([
            'gmail' => 'mk-early@example.test',
            'password' => 'password',
            'is_verified' => true,
            'Delistment_allow' => true,
        ]);
        $purok = Purok::query()->create(['purok_number' => 4, 'purok_name' => 'Purok Four', 'grupo_start' => 1, 'grupo_end' => 12]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('de-listment.mk.store'), [
                'full_name' => 'Early MK',
                'registry' => 'MKEARLY123456',
                'gender' => 'Male',
                'purok_id' => $purok->id,
                'grupo' => 3,
                'kapisanan' => 'Kadiwa',
                'is_married' => false,
                'date_of_baptism' => self::MANILA_TODAY,
                'date_of_entry' => self::MANILA_TODAY,
            ])
            ->assertCreated();
        $record = Record::query()->where('registry', 'MKEARLY123456')->sole();
        $this->assertSame(self::MANILA_TODAY, $record->date_of_entry->format('Y-m-d'));
    }

    public function test_deliberation_add_accepts_the_manila_date_of_today(): void
    {
        $user = User::query()->create([
            'gmail' => 'delib-early@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
        ]);
        MonthlyMovementSession::query()->create([
            'active_key' => 'active',
            'status' => 'active',
            'period_month' => '2026-11-01',
            'started_by' => $user->gmail,
            'started_at' => now(),
        ]);
        $purok = Purok::query()->create(['purok_number' => 1, 'purok_name' => 'Central', 'grupo_start' => 1, 'grupo_end' => 3]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), [
                'full_name' => 'Early Member',
                'registry' => '1234567890123',
                'is_rfid' => false,
                'gender' => 'Male',
                'purok_id' => $purok->id,
                'grupo' => 2,
                'kapisanan' => 'Kadiwa',
                'date_of_baptism' => self::MANILA_TODAY,
                'evangelist' => 'Evangelist Name',
                'status' => 'MS',
                'date_of_birth' => null,
                'is_married' => 0,
                'description' => null,
            ])
            ->assertSuccessful();
        $record = Record::query()->where('registry', '1234567890123')->sole();
        $this->assertSame(self::MANILA_TODAY, $record->date_of_baptism->format('Y-m-d'));
    }
}
