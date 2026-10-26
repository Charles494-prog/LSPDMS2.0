<?php

namespace Tests\Feature;

use App\Models\MonthlyMovementSession;
use App\Models\Purok;
use App\Models\R401Snapshot;
use App\Models\Record;
use App\Models\User;
use App\Services\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MonthlySessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_start_is_allowed_for_next_period_after_the_15th_even_when_a_session_started_this_calendar_month(): void
    {
        $this->completedSession('2026-10-01', '2026-10-03 02:00:00');
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();

        $this->withSession($secretary)
            ->getJson(route('forms.monthly.session.status'))
            ->assertOk()
            ->assertJsonPath('session', null)
            ->assertJsonPath('start_availability.allowed', true)
            ->assertJsonPath('start_availability.target_month', 'November 2026')
            ->assertJsonPath('start_availability.warning', null)
            ->assertJsonPath('start_availability.message', 'Monthly Session available right now for November 2026!');

        $this->withSession($secretary)
            ->postJson(route('forms.monthly.session.start'))
            ->assertOk()
            ->assertJsonPath('session.period_month', 'November 2026')
            ->assertJsonPath('warning', null);

        $started = MonthlyMovementSession::query()->where('active_key', 'active')->sole();
        // Written with now() (app timezone) like every other timestamp.
        $this->assertSame(
            Carbon::now()->format('Y-m-d H:i:s'),
            DB::table('monthly_movement_sessions')->where('id', $started->id)->value('started_at')
        );
    }

    public function test_start_is_blocked_and_names_the_period_that_already_exists(): void
    {
        $this->completedSession('2026-10-01', '2026-10-03 02:00:00');
        $this->completedSession('2026-11-01', '2026-10-25 02:00:00');
        Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();

        $this->withSession($secretary)
            ->getJson(route('forms.monthly.session.status'))
            ->assertOk()
            ->assertJsonPath('start_availability.allowed', false)
            ->assertJsonPath('start_availability.target_month', null)
            ->assertJsonPath('start_availability.existing_period', 'November 2026')
            ->assertJsonPath(
                'start_availability.message',
                'A Monthly Session for November 2026 already exists. Only one session is allowed per period month.'
            );

        $this->withSession($secretary)
            ->postJson(route('forms.monthly.session.start'))
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.session.0',
                'A Monthly Session for November 2026 already exists. Only one session is allowed per period month.'
            );
        $this->assertDatabaseCount('monthly_movement_sessions', 2);
    }

    public function test_start_warns_when_the_target_period_skips_a_month_without_a_session(): void
    {
        $this->completedSession('2026-10-01', '2026-10-03 02:00:00');
        Carbon::setTestNow(Carbon::parse('2026-11-20 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();
        $warning = 'Warning: no Monthly Session exists for November 2026. Starting now creates the December 2026 session and skips that month.';

        $this->withSession($secretary)
            ->getJson(route('forms.monthly.session.status'))
            ->assertOk()
            ->assertJsonPath('start_availability.allowed', true)
            ->assertJsonPath('start_availability.target_month', 'December 2026')
            ->assertJsonPath('start_availability.warning', $warning)
            ->assertJsonPath('start_availability.message', 'Monthly Session available right now for December 2026! '.$warning);

        $this->withSession($secretary)
            ->postJson(route('forms.monthly.session.start'))
            ->assertOk()
            ->assertJsonPath('session.period_month', 'December 2026')
            ->assertJsonPath('warning', $warning);

        // Nothing is created for the skipped month.
        $this->assertFalse(MonthlyMovementSession::query()->whereDate('period_month', '2026-11-01')->exists());
        $this->assertDatabaseCount('monthly_movement_sessions', 2);
    }

    public function test_an_already_active_session_is_returned_instead_of_a_second_one(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();

        $first = $this->withSession($secretary)->postJson(route('forms.monthly.session.start'))->assertOk();
        $this->withSession($secretary)
            ->postJson(route('forms.monthly.session.start'))
            ->assertOk()
            ->assertJsonPath('session.id', $first->json('session.id'));
        $this->assertDatabaseCount('monthly_movement_sessions', 1);
    }

    public function test_a_concurrent_start_that_loses_the_race_gets_a_friendly_422(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();
        // Simulate another request inserting the active session after this
        // request's checks but before its insert.
        MonthlyMovementSession::creating(function (): void {
            DB::table('monthly_movement_sessions')->insert([
                'active_key' => 'active',
                'status' => 'active',
                'period_month' => '2026-11-01',
                'started_by' => 'other@example.test',
                'started_at' => now(),
            ]);
        });

        $this->withSession($secretary)
            ->postJson(route('forms.monthly.session.start'))
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.session.0',
                'Another monthly session was started at the same time. Refresh the page to see the active session.'
            );

        MonthlyMovementSession::flushEventListeners();
        // The simulated rival insert shares this test's transaction, so it is rolled back with the
        // losing request; what matters is that the losing request created no second active session.
        $this->assertDatabaseMissing('monthly_movement_sessions', ['started_by' => $secretary['login_user']['gmail']]);
        $this->assertLessThanOrEqual(1, DB::table('monthly_movement_sessions')->where('active_key', 'active')->count());
    }

    public function test_finish_never_writes_started_at_and_a_double_finish_gets_a_friendly_error(): void
    {
        config(['session.idle_timeout' => 0]); // the clock jumps days ahead within one signed-in session
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();
        $this->withSession($secretary)->postJson(route('forms.monthly.session.start'))->assertOk();
        $session = MonthlyMovementSession::query()->where('active_key', 'active')->sole();
        $startedAt = DB::table('monthly_movement_sessions')->where('id', $session->id)->value('started_at');

        Carbon::setTestNow(Carbon::parse('2026-10-30 18:00:00', 'Asia/Manila'));
        $sessionUpdates = [];
        DB::listen(function ($query) use (&$sessionUpdates): void {
            if (preg_match('/^update\s+"?monthly_movement_sessions"?/i', $query->sql) === 1) {
                $sessionUpdates[] = $query->sql;
            }
        });
        $head = $this->headSession();
        $this->withSession($head)
            ->postJson(route('forms.monthly.session.finish'))
            ->assertOk()
            ->assertJsonPath('period_label', 'November 2026');

        $this->assertNotEmpty($sessionUpdates);
        foreach ($sessionUpdates as $sql) {
            $this->assertStringNotContainsString('started_at', $sql);
        }
        $this->assertSame($startedAt, DB::table('monthly_movement_sessions')->where('id', $session->id)->value('started_at'));
        $this->assertDatabaseHas('monthly_movement_sessions', [
            'id' => $session->id,
            'status' => 'completed',
            'active_key' => null,
        ]);

        $this->withSession($head)
            ->postJson(route('forms.monthly.session.finish'))
            ->assertStatus(409)
            ->assertJsonPath('message', 'This monthly session has already been finished. Refresh the page to see the latest status.');
        $this->assertSame(1, R401Snapshot::query()->where('session_id', $session->id)->count());
    }

    public function test_finish_keeps_activity_changed_at_when_unlinking_movements(): void
    {
        config(['session.idle_timeout' => 0]); // the clock jumps days ahead within one signed-in session
        Purok::query()->create(['purok_number' => 1, 'purok_name' => 'Central', 'grupo_start' => 1, 'grupo_end' => 5]);
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();
        $this->withSession($secretary)->postJson(route('forms.monthly.session.start'))->assertOk();
        $record = Record::query()->create(['full_name' => 'Status Mover', 'status' => 'MS', 'purok' => '1', 'grupo' => '1']);
        $this->withSession($secretary)
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $record->id,
                'action' => 'status',
                'status' => 'UWP',
            ])
            ->assertOk();
        $before = DB::table('monthly_session_activities')->orderBy('id')->pluck('changed_at', 'id')->all();
        $this->assertNotEmpty($before);

        Carbon::setTestNow(Carbon::parse('2026-10-30 18:00:00', 'Asia/Manila'));
        $this->withSession($this->headSession())->postJson(route('forms.monthly.session.finish'))->assertOk();

        $after = DB::table('monthly_session_activities')->orderBy('id')->pluck('changed_at', 'id')->all();
        $this->assertSame($before, $after);
        $this->assertSame(0, DB::table('monthly_session_activities')->whereNotNull('session_movement_id')->count());
    }

    public function test_finish_stores_snapshot_and_preview_in_the_database_without_overwriting_existing_files(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();
        $this->withSession($secretary)->postJson(route('forms.monthly.session.start'))->assertOk();
        $session = MonthlyMovementSession::query()->where('active_key', 'active')->sole();
        // A file left from an earlier database (for example after an import) uses the default name.
        $defaultPath = 'monthly-session-snapshots/2026-11/session-'.$session->id.'.docx';
        app(FileStore::class)->persist('local', $defaultPath, 'older snapshot');

        $this->withSession($this->headSession())->postJson(route('forms.monthly.session.finish'))->assertOk();

        $snapshot = R401Snapshot::query()->where('session_id', $session->id)->sole();
        $this->assertNotSame($defaultPath, $snapshot->file_path);
        $this->assertMatchesRegularExpression(
            '#^monthly-session-snapshots/2026-11/session-'.$session->id.'-[a-z0-9]{6}\.docx$#',
            $snapshot->file_path
        );
        $previewPath = str_replace('.docx', '.html', $snapshot->file_path);
        $fileStore = app(FileStore::class);
        $this->assertTrue($fileStore->isPersisted('local', $snapshot->file_path));
        $this->assertTrue($fileStore->isPersisted('local', $previewPath));
        Storage::disk('local')->assertExists($snapshot->file_path);
        Storage::disk('local')->assertExists($previewPath);
        $this->assertSame('older snapshot', $fileStore->get('local', $defaultPath));

        // The database copy alone is enough to bring the files back.
        Storage::disk('local')->delete([$snapshot->file_path, $previewPath]);
        $this->assertNotNull($fileStore->get('local', $snapshot->file_path));
        $this->assertStringContainsString('<', (string) $fileStore->get('local', $previewPath));
    }

    public function test_finish_uses_the_default_snapshot_name_when_it_is_free(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $this->withSession($this->secretarySession())->postJson(route('forms.monthly.session.start'))->assertOk();
        $session = MonthlyMovementSession::query()->where('active_key', 'active')->sole();

        $this->withSession($this->headSession())->postJson(route('forms.monthly.session.finish'))->assertOk();

        $this->assertDatabaseHas('r4_01_snapshots', [
            'session_id' => $session->id,
            'file_path' => 'monthly-session-snapshots/2026-11/session-'.$session->id.'.docx',
        ]);
    }

    public function test_only_head_secretary_can_finish(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 09:00:00', 'Asia/Manila'));
        $secretary = $this->secretarySession();
        $this->withSession($secretary)->postJson(route('forms.monthly.session.start'))->assertOk();

        $this->withSession($secretary)->postJson(route('forms.monthly.session.finish'))->assertForbidden();
        $this->assertDatabaseHas('monthly_movement_sessions', ['active_key' => 'active']);
    }

    private function completedSession(string $periodMonth, string $startedAtUtc): MonthlyMovementSession
    {
        return MonthlyMovementSession::query()->create([
            'active_key' => null,
            'status' => 'completed',
            'period_month' => $periodMonth,
            'started_by' => 'earlier@example.test',
            'started_at' => $startedAtUtc,
            'completed_by' => 'earlier@example.test',
            'completed_at' => $startedAtUtc,
        ]);
    }

    private function secretarySession(): array
    {
        $user = User::query()->create([
            'gmail' => 'lifecycle.secretary.'.fake()->unique()->numerify('####').'@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
        ]);

        return ['login_user' => ['id' => $user->id, 'gmail' => $user->gmail]];
    }

    private function headSession(): array
    {
        $user = User::query()->firstOrCreate(['gmail' => 'lifecycle.head@example.test'], [
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);

        return ['login_user' => ['id' => $user->id, 'gmail' => $user->gmail, 'tungkulin' => 'Head Secretary']];
    }
}
