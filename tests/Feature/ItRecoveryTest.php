<?php

namespace Tests\Feature;

use App\Console\Commands\ItRecoveryProvision;
use App\Models\GlobalVariable;
use App\Models\ItRecoveryAccount;
use App\Models\ItRecoveryAuditLog;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use LogicException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Tests\TestCase;

class ItRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const IT_EMAIL = 'it.breakglass@example.test';

    private const IT_PASSWORD = 'Correct-Horse-9-Battery!';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(array $attributes = []): ItRecoveryAccount
    {
        return ItRecoveryAccount::query()->forceCreate(array_merge([
            'email' => self::IT_EMAIL,
            'password' => Hash::make(self::IT_PASSWORD),
            'is_active' => true,
            'failed_attempts' => 0,
            'password_changed_at' => now()->subDay(),
        ], $attributes));
    }

    private function loginUser(array $attributes = []): User
    {
        $user = new User;
        $user->forceFill(array_merge([
            'gmail' => 'secretary@example.test',
            'password' => 'Original-Password-1!',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'first_name' => 'Sec',
            'last_name' => 'Retary',
        ], $attributes))->save();

        return $user;
    }

    private function signIn(string $ip = '10.0.0.1'): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('it-recovery.login.attempt'), [
                'email' => self::IT_EMAIL,
                'password' => self::IT_PASSWORD,
            ])
            ->assertRedirect(route('it-recovery.dashboard'));
    }

    private function stdin(string $contents): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $this->app->instance(ItRecoveryProvision::STDIN_BINDING, $stream);
    }

    private function auditEvents(): array
    {
        return ItRecoveryAuditLog::query()->orderBy('id')->pluck('event')->all();
    }

    // ---- Provisioning -------------------------------------------------

    public function test_provision_with_password_stdin_creates_hashed_account_and_audits(): void
    {
        $this->stdin(self::IT_PASSWORD."\n");

        $this->artisan('lspdms:it-recovery:provision', [
            'email' => 'IT.BreakGlass@Example.test',
            '--password-stdin' => true,
        ])->assertExitCode(0);

        $account = ItRecoveryAccount::query()->sole();
        $this->assertSame(self::IT_EMAIL, $account->email);
        $this->assertTrue($account->is_active);
        $this->assertNotSame(self::IT_PASSWORD, $account->password);
        $this->assertTrue(Hash::check(self::IT_PASSWORD, $account->password));
        $this->assertNotNull($account->password_changed_at);

        $log = ItRecoveryAuditLog::query()->sole();
        $this->assertSame('account_provisioned', $log->event);
        $this->assertSame(self::IT_EMAIL, $log->account_email);
        $this->assertStringNotContainsString(self::IT_PASSWORD, json_encode($log->getAttributes()));
        $this->assertStringNotContainsString($account->password, json_encode($log->getAttributes()));
    }

    public function test_provision_defaults_to_configured_email(): void
    {
        config(['it_recovery.email' => self::IT_EMAIL]);
        $this->stdin(self::IT_PASSWORD."\n");

        $this->artisan('lspdms:it-recovery:provision', ['--password-stdin' => true])->assertExitCode(0);

        $this->assertSame(self::IT_EMAIL, ItRecoveryAccount::query()->sole()->email);
    }

    public function test_provision_without_email_fails(): void
    {
        config(['it_recovery.email' => null]);

        $this->artisan('lspdms:it-recovery:provision', ['--password-stdin' => true])->assertExitCode(1);

        $this->assertSame(0, ItRecoveryAccount::query()->count());
    }

    public function test_provision_interactive_prompts_twice_and_rejects_mismatch(): void
    {
        $this->artisan('lspdms:it-recovery:provision', ['email' => self::IT_EMAIL])
            ->expectsQuestion('IT recovery password (min 16 chars, upper/lowercase letters, numbers and symbols)', self::IT_PASSWORD)
            ->expectsQuestion('Repeat the IT recovery password', 'Different-Password-9!')
            ->assertExitCode(1);
        $this->assertSame(0, ItRecoveryAccount::query()->count());

        $this->artisan('lspdms:it-recovery:provision', ['email' => self::IT_EMAIL])
            ->expectsQuestion('IT recovery password (min 16 chars, upper/lowercase letters, numbers and symbols)', self::IT_PASSWORD)
            ->expectsQuestion('Repeat the IT recovery password', self::IT_PASSWORD)
            ->assertExitCode(0);
        $this->assertTrue(Hash::check(self::IT_PASSWORD, ItRecoveryAccount::query()->sole()->password));
    }

    public function test_provision_rejects_weak_passwords(): void
    {
        foreach (['short-Pass1!', 'onlylettersandmorelettersHere', 'NoSymbolsHere12345678', 'no-numbers-at-all-Here!'] as $weak) {
            $this->stdin($weak."\n");
            $this->artisan('lspdms:it-recovery:provision', [
                'email' => self::IT_EMAIL,
                '--password-stdin' => true,
            ])->assertExitCode(1);
        }

        $this->assertSame(0, ItRecoveryAccount::query()->count());
        $this->assertSame(0, ItRecoveryAuditLog::query()->count());
    }

    public function test_provision_refuses_non_interactive_without_password_stdin(): void
    {
        $this->artisan('lspdms:it-recovery:provision', [
            'email' => self::IT_EMAIL,
            '--no-interaction' => true,
        ])->assertExitCode(1);

        $this->assertSame(0, ItRecoveryAccount::query()->count());
    }

    public function test_provision_never_accepts_password_as_cli_option(): void
    {
        $this->expectException(InvalidOptionException::class);

        $this->artisan('lspdms:it-recovery:provision', [
            'email' => self::IT_EMAIL,
            '--password' => self::IT_PASSWORD,
        ])->run();
    }

    public function test_provision_never_accepts_password_as_cli_argument(): void
    {
        $this->expectException(ConsoleRuntimeException::class);

        $this->app->make(Kernel::class)
            ->call('lspdms:it-recovery:provision '.self::IT_EMAIL.' '.self::IT_PASSWORD);
    }

    public function test_provision_refuses_duplicate_and_rotate_changes_password(): void
    {
        $account = $this->account();
        $this->stdin('Another-Strong-Pass-42!'."\n");
        $this->artisan('lspdms:it-recovery:provision', ['email' => self::IT_EMAIL, '--password-stdin' => true])
            ->assertExitCode(1);
        $this->assertTrue(Hash::check(self::IT_PASSWORD, $account->fresh()->password));

        $this->stdin('Another-Strong-Pass-42!'."\n");
        $this->artisan('lspdms:it-recovery:provision', [
            'email' => self::IT_EMAIL,
            '--rotate' => true,
            '--password-stdin' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Hash::check('Another-Strong-Pass-42!', $account->fresh()->password));
        $this->assertSame(['password_rotated'], $this->auditEvents());
    }

    public function test_rotate_requires_existing_account(): void
    {
        $this->stdin(self::IT_PASSWORD."\n");
        $this->artisan('lspdms:it-recovery:provision', [
            'email' => self::IT_EMAIL,
            '--rotate' => true,
            '--password-stdin' => true,
        ])->assertExitCode(1);

        $this->assertSame(0, ItRecoveryAccount::query()->count());
    }

    public function test_deactivate_and_activate_are_audited_and_block_login(): void
    {
        $account = $this->account();

        $this->artisan('lspdms:it-recovery:provision', ['email' => self::IT_EMAIL, '--deactivate' => true])
            ->assertExitCode(0);
        $this->assertFalse($account->fresh()->is_active);

        $this->from(route('it-recovery.login'))
            ->post(route('it-recovery.login.attempt'), ['email' => self::IT_EMAIL, 'password' => self::IT_PASSWORD])
            ->assertRedirect(route('it-recovery.login'))
            ->assertSessionHasErrors(['email' => 'These credentials could not be verified.']);
        $this->assertFalse(session()->has('it_recovery'));

        $this->artisan('lspdms:it-recovery:provision', ['email' => self::IT_EMAIL, '--activate' => true])
            ->assertExitCode(0);
        $this->assertTrue($account->fresh()->is_active);

        $this->assertSame(['account_deactivated', 'login_failed', 'account_activated'], $this->auditEvents());
    }

    public function test_deactivating_ends_an_open_it_session(): void
    {
        $this->account();
        $this->signIn();

        $this->artisan('lspdms:it-recovery:provision', ['email' => self::IT_EMAIL, '--deactivate' => true])
            ->assertExitCode(0);

        $this->get(route('it-recovery.dashboard'))->assertRedirect(route('it-recovery.login'));
        $this->assertContains('session_revoked', $this->auditEvents());
    }

    public function test_rotating_password_ends_an_open_it_session(): void
    {
        Carbon::setTestNow('2026-10-24 08:00:00');
        $this->account();
        $this->signIn();

        Carbon::setTestNow('2026-10-24 08:01:00');
        $this->stdin('Rotated-Strong-Pass-77!'."\n");
        $this->artisan('lspdms:it-recovery:provision', [
            'email' => self::IT_EMAIL,
            '--rotate' => true,
            '--password-stdin' => true,
        ])->assertExitCode(0);

        $this->get(route('it-recovery.dashboard'))->assertRedirect(route('it-recovery.login'));
    }

    public function test_no_account_is_created_automatically(): void
    {
        $this->get(route('it-recovery.login'))->assertOk();

        $this->assertSame(0, ItRecoveryAccount::query()->count());
    }

    // ---- Login --------------------------------------------------------

    public function test_login_page_is_standalone_and_not_cached(): void
    {
        $response = $this->get(route('it-recovery.login'))
            ->assertOk()
            ->assertSee('IT Recovery Sign-in')
            ->assertSee('name="_token"', false)
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_successful_login_creates_separate_session_and_audits(): void
    {
        $account = $this->account(['failed_attempts' => 3]);

        $this->signIn();

        $session = session('it_recovery');
        $this->assertSame($account->id, $session['account_id']);
        $this->assertFalse(session()->has('login_user'));
        $this->assertSame(0, $account->fresh()->failed_attempts);
        $this->assertNotNull($account->fresh()->last_login_at);
        $this->assertSame(['login_succeeded'], $this->auditEvents());
        $this->assertSame('10.0.0.1', ItRecoveryAuditLog::query()->sole()->ip);

        $this->get(route('it-recovery.dashboard'))->assertOk()->assertSee('IT Recovery Actions');
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $this->account();
        $this->get(route('it-recovery.login'))->assertOk();
        $before = session()->getId();

        $this->signIn();

        $this->assertNotSame($before, session()->getId());
    }

    public function test_failed_login_uses_generic_message_for_unknown_and_wrong_password(): void
    {
        $this->account();

        $this->from(route('it-recovery.login'))
            ->post(route('it-recovery.login.attempt'), ['email' => self::IT_EMAIL, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'These credentials could not be verified.']);
        $this->from(route('it-recovery.login'))
            ->post(route('it-recovery.login.attempt'), ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'These credentials could not be verified.']);

        $this->assertFalse(session()->has('it_recovery'));
        $this->assertSame(1, ItRecoveryAccount::query()->sole()->failed_attempts);

        $logs = ItRecoveryAuditLog::query()->orderBy('id')->get();
        $this->assertSame(['login_failed', 'login_failed'], $logs->pluck('event')->all());
        $this->assertSame(['bad_password', 'unknown_account'], $logs->pluck('details.reason')->all());
        $this->assertStringNotContainsString('wrong-password', $logs->toJson());
    }

    public function test_login_is_throttled_per_email_and_ip(): void
    {
        $this->account();

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
                ->post(route('it-recovery.login.attempt'), ['email' => self::IT_EMAIL, 'password' => 'wrong-'.$i])
                ->assertSessionHasErrors(['email' => 'These credentials could not be verified.']);
        }

        // Even the right password is refused while throttled.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->post(route('it-recovery.login.attempt'), ['email' => self::IT_EMAIL, 'password' => self::IT_PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertFalse(session()->has('it_recovery'));
        $this->assertSame('login_throttled', ItRecoveryAuditLog::query()->latest('id')->first()->event);

        // A different IP is not affected by that throttle.
        $this->signIn('10.0.0.6');
    }

    public function test_account_locks_after_ten_consecutive_failures(): void
    {
        Carbon::setTestNow('2026-10-24 09:00:00');
        $account = $this->account();

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.'.$i])
                ->post(route('it-recovery.login.attempt'), ['email' => self::IT_EMAIL, 'password' => 'wrong']);
        }

        $account->refresh();
        $this->assertTrue($account->isLocked());
        $this->assertTrue($account->locked_until->equalTo(Carbon::parse('2026-10-24 09:30:00')));
        $this->assertContains('account_locked', $this->auditEvents());

        // Correct password while locked: same generic failure, audited as a locked attempt.
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.1'])
            ->post(route('it-recovery.login.attempt'), ['email' => self::IT_EMAIL, 'password' => self::IT_PASSWORD])
            ->assertSessionHasErrors(['email' => 'These credentials could not be verified.']);
        $this->assertFalse(session()->has('it_recovery'));
        $this->assertSame('login_locked', ItRecoveryAuditLog::query()->latest('id')->first()->event);

        Carbon::setTestNow('2026-10-24 09:31:00');
        $this->signIn('10.2.0.2');
    }

    public function test_logout_ends_session_and_audits(): void
    {
        $this->account();
        $this->signIn();

        $this->post(route('it-recovery.logout'))->assertRedirect(route('it-recovery.login'));

        $this->assertFalse(session()->has('it_recovery'));
        $this->get(route('it-recovery.dashboard'))->assertRedirect(route('it-recovery.login'));
        $this->assertSame(['login_succeeded', 'logout'], $this->auditEvents());
    }

    public function test_idle_timeout_after_fifteen_minutes(): void
    {
        Carbon::setTestNow('2026-10-24 10:00:00');
        $this->account();
        $this->signIn();

        Carbon::setTestNow('2026-10-24 10:14:00');
        $this->get(route('it-recovery.dashboard'))->assertOk();

        Carbon::setTestNow('2026-10-24 10:29:30');
        $this->get(route('it-recovery.dashboard'))->assertRedirect(route('it-recovery.login'));

        $this->assertFalse(session()->has('it_recovery'));
        $log = ItRecoveryAuditLog::query()->latest('id')->first();
        $this->assertSame('session_timeout', $log->event);
        $this->assertSame(['reason' => 'idle'], $log->details);
    }

    public function test_absolute_session_lifetime_is_two_hours(): void
    {
        Carbon::setTestNow('2026-10-24 10:00:00');
        $this->account();
        $this->signIn();

        foreach (range(10, 120, 10) as $minutes) {
            Carbon::setTestNow(Carbon::parse('2026-10-24 10:00:00')->addMinutes($minutes));
            $this->get(route('it-recovery.dashboard'))->assertOk();
        }

        Carbon::setTestNow('2026-10-24 12:00:30');
        $this->get(route('it-recovery.dashboard'))->assertRedirect(route('it-recovery.login'));
        $this->assertSame(['reason' => 'absolute'], ItRecoveryAuditLog::query()->latest('id')->first()->details);
    }

    public function test_allowed_ip_list_hides_the_surface_from_other_ips(): void
    {
        config(['it_recovery.allowed_ips' => ['127.0.0.1']]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->get(route('it-recovery.login'))
            ->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('it-recovery.login'))
            ->assertOk();
    }

    // ---- Separation from the normal application -------------------------

    public function test_head_secretary_session_cannot_reach_it_recovery(): void
    {
        $this->account();
        $head = $this->loginUser(['gmail' => 'head@example.test', 'tungkulin' => 'Head Secretary']);
        $this->withSession(['login_user' => ['id' => $head->id, 'tungkulin' => 'Head Secretary']]);

        $this->get(route('it-recovery.login'))->assertForbidden();
        $this->get(route('it-recovery.dashboard'))->assertForbidden();
        $this->get(route('it-recovery.audit'))->assertForbidden();
        $this->post(route('it-recovery.login.attempt'), ['email' => self::IT_EMAIL, 'password' => self::IT_PASSWORD])
            ->assertForbidden();
        $this->post(route('it-recovery.actions.reset-password'), ['user_id' => $head->id, 'it_password' => self::IT_PASSWORD])
            ->assertForbidden();
        $this->post(route('it-recovery.actions.disable-email-features'), ['it_password' => self::IT_PASSWORD])
            ->assertForbidden();
        $this->post(route('it-recovery.actions.reenable-account'), ['user_id' => $head->id, 'it_password' => self::IT_PASSWORD])
            ->assertForbidden();

        $this->assertSame(0, ItRecoveryAuditLog::query()->count());
    }

    public function test_normal_login_session_cannot_be_combined_with_an_it_session(): void
    {
        $account = $this->account();
        $head = $this->loginUser(['gmail' => 'head@example.test', 'tungkulin' => 'Head Secretary']);

        $this->withSession([
            'login_user' => ['id' => $head->id, 'tungkulin' => 'Head Secretary'],
            'it_recovery' => [
                'account_id' => $account->id,
                'email' => $account->email,
                'password_changed_at' => $account->password_changed_at->getTimestamp(),
                'logged_in_at' => now()->getTimestamp(),
                'last_activity_at' => now()->getTimestamp(),
            ],
        ])->get(route('it-recovery.dashboard'))->assertForbidden();
    }

    public function test_it_session_cannot_access_normal_app_routes(): void
    {
        $this->account();
        $this->signIn();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->get(route('superadmin-dashboard'))->assertRedirect('/login');
        $this->get(route('superadmin.database.export-sql'))->assertRedirect('/login');
        $this->assertFalse(session()->has('login_user'));
    }

    public function test_superadmin_dashboard_and_export_never_show_it_accounts(): void
    {
        $this->account();
        ItRecoveryAuditLog::record('login_succeeded', self::IT_EMAIL);
        $head = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'first_name' => 'Head',
            'last_name' => 'Sec',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('head@example.test')
            ->assertDontSee(self::IT_EMAIL);

        $this->assertSame(0, User::query()->where('gmail', self::IT_EMAIL)->count());
    }

    public function test_backup_export_excludes_it_recovery_tables(): void
    {
        $this->account();
        ItRecoveryAuditLog::record('login_succeeded', self::IT_EMAIL);
        $service = app(DatabaseBackupService::class);

        $this->assertNotContains('it_recovery_accounts', $service->tableNames());
        $this->assertNotContains('it_recovery_audit_logs', $service->tableNames());

        $stream = fopen('php://memory', 'w+');
        $service->writeSql($stream);
        rewind($stream);
        $sql = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('login_user', $sql);
        $this->assertStringNotContainsString('it_recovery', $sql);
        $this->assertStringNotContainsString(self::IT_EMAIL, $sql);
    }

    // ---- Recovery actions ---------------------------------------------

    public function test_actions_require_it_session(): void
    {
        $user = $this->loginUser();

        $this->post(route('it-recovery.actions.reset-password'), ['user_id' => $user->id, 'it_password' => self::IT_PASSWORD])
            ->assertRedirect(route('it-recovery.login'));
        $this->get(route('it-recovery.audit'))->assertRedirect(route('it-recovery.login'));
    }

    public function test_actions_require_reentering_the_it_password(): void
    {
        $this->account();
        $user = $this->loginUser(['is_verified' => false]);
        GlobalVariable::query()->create(['require_otp' => true, 'allow_forgot_password' => true]);
        $this->signIn();
        $originalHash = DB::table('login_user')->where('id', $user->id)->value('password');

        $this->post(route('it-recovery.actions.reset-password'), ['user_id' => $user->id])
            ->assertSessionHasErrors('it_password');
        $this->post(route('it-recovery.actions.reset-password'), ['user_id' => $user->id, 'it_password' => 'wrong'])
            ->assertRedirect(route('it-recovery.dashboard'))
            ->assertSessionHasErrors('it_password');
        $this->post(route('it-recovery.actions.reenable-account'), ['user_id' => $user->id, 'it_password' => 'wrong'])
            ->assertSessionHasErrors('it_password');
        $this->post(route('it-recovery.actions.disable-email-features'), ['it_password' => 'wrong'])
            ->assertSessionHasErrors('it_password');

        $this->assertSame($originalHash, DB::table('login_user')->where('id', $user->id)->value('password'));
        $this->assertSame(0, (int) DB::table('login_user')->where('id', $user->id)->value('is_verified'));
        $settings = GlobalVariable::query()->first();
        $this->assertTrue((bool) $settings->require_otp);
        $this->assertTrue((bool) $settings->allow_forgot_password);

        $this->assertSame(3, ItRecoveryAuditLog::query()->where('event', 'reauth_failed')->count());
        $this->assertSame(3, ItRecoveryAccount::query()->sole()->failed_attempts);
        $this->assertSame(
            ['action' => 'reset_password', 'target_user_id' => $user->id],
            ItRecoveryAuditLog::query()->where('event', 'reauth_failed')->orderBy('id')->first()->details
        );
    }

    public function test_repeated_reauth_failures_lock_the_account_and_end_the_session(): void
    {
        $this->account(['failed_attempts' => 9]);
        $this->signIn();
        // A successful sign-in resets the counter, so set it back to the brink.
        ItRecoveryAccount::query()->update(['failed_attempts' => 9]);

        $this->post(route('it-recovery.actions.disable-email-features'), ['it_password' => 'wrong'])
            ->assertRedirect(route('it-recovery.login'));

        $this->assertTrue(ItRecoveryAccount::query()->sole()->isLocked());
        $this->assertFalse(session()->has('it_recovery'));
        $this->assertContains('account_locked', $this->auditEvents());
    }

    public function test_reset_password_shows_temporary_password_once_and_signs_user_out(): void
    {
        $this->account();
        $user = $this->loginUser();
        DB::table('login_user')->where('id', $user->id)->update([
            'auth_version' => 4,
            'remember_token' => Hash::make('remember-me-token'),
            'remember_token_expires_at' => now()->addDays(10),
        ]);
        $this->signIn();

        $response = $this->post(route('it-recovery.actions.reset-password'), [
            'user_id' => $user->id,
            'it_password' => self::IT_PASSWORD,
        ])->assertOk()->assertSee('Temporary password');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(1, preg_match('/<code class="secret">([^<]+)<\/code>/', $response->getContent(), $match));
        $temporary = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        $this->assertSame(20, mb_strlen($temporary));
        $this->assertMatchesRegularExpression('/[A-Za-z]/', $temporary);
        $this->assertMatchesRegularExpression('/[0-9]/', $temporary);
        $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $temporary);

        $row = DB::table('login_user')->where('id', $user->id)->first();
        $this->assertTrue(Hash::check($temporary, $row->password));
        $this->assertSame(5, (int) $row->auth_version);
        $this->assertSame('password_changed', $row->auth_notice);
        $this->assertNull($row->remember_token);
        $this->assertNull($row->remember_token_expires_at);

        $log = ItRecoveryAuditLog::query()->where('event', 'action_reset_password')->sole();
        $this->assertSame(['target_user_id' => $user->id, 'outcome' => 'password_reset'], $log->details);
        $this->assertStringNotContainsString($temporary, ItRecoveryAuditLog::query()->get()->toJson());
        $this->assertStringNotContainsString($temporary, json_encode(session()->all()));

        // The old remember-me cookie no longer signs the user in.
        $this->post(route('it-recovery.logout'));
        $this->withCookie('remember_token', $user->id.'|remember-me-token')
            ->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_reset_password_signs_out_an_existing_app_session(): void
    {
        $this->account();
        $user = $this->loginUser();
        DB::table('login_user')->where('id', $user->id)->update(['auth_version' => 2]);
        $this->signIn();
        $this->post(route('it-recovery.actions.reset-password'), ['user_id' => $user->id, 'it_password' => self::IT_PASSWORD])
            ->assertOk();
        $this->post(route('it-recovery.logout'));

        $this->withSession(['login_user' => ['id' => $user->id, 'auth_version' => 2, 'tungkulin' => 'Secretary']])
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Change of Password Detected, Please Login Again.');
    }

    public function test_reset_password_for_missing_target_is_audited(): void
    {
        $this->account();
        $this->signIn();

        $this->post(route('it-recovery.actions.reset-password'), ['user_id' => 999, 'it_password' => self::IT_PASSWORD])
            ->assertRedirect(route('it-recovery.dashboard'))
            ->assertSessionHasErrors('user_id');

        $this->assertSame(
            ['target_user_id' => 999, 'outcome' => 'target_not_found'],
            ItRecoveryAuditLog::query()->where('event', 'action_reset_password')->sole()->details
        );
    }

    public function test_reenable_account_sets_is_verified(): void
    {
        $this->account();
        $user = $this->loginUser(['is_verified' => false]);
        $this->signIn();

        $this->post(route('it-recovery.actions.reenable-account'), ['user_id' => $user->id, 'it_password' => self::IT_PASSWORD])
            ->assertRedirect(route('it-recovery.dashboard'))
            ->assertSessionHas('status');
        $this->assertSame(1, (int) DB::table('login_user')->where('id', $user->id)->value('is_verified'));

        $this->post(route('it-recovery.actions.reenable-account'), ['user_id' => $user->id, 'it_password' => self::IT_PASSWORD]);

        $this->assertSame(
            ['enabled', 'already_enabled'],
            ItRecoveryAuditLog::query()->where('event', 'action_reenable_account')->orderBy('id')->get()->pluck('details.outcome')->all()
        );
    }

    public function test_disable_email_features_uses_security_policy(): void
    {
        $this->account();
        GlobalVariable::query()->create(['require_otp' => true, 'allow_forgot_password' => true]);
        $this->signIn();

        $this->post(route('it-recovery.actions.disable-email-features'), ['it_password' => self::IT_PASSWORD])
            ->assertRedirect(route('it-recovery.dashboard'))
            ->assertSessionHas('status', 'Require OTP and Allow Forgot Password have been turned off.');

        $settings = GlobalVariable::query()->first();
        $this->assertFalse((bool) $settings->require_otp);
        $this->assertFalse((bool) $settings->allow_forgot_password);
        $this->assertDatabaseHas('system_maintenance_audits', [
            'action' => 'email_features_off:it_recovery',
            'changed_by' => 'it:'.self::IT_EMAIL,
        ]);

        $this->post(route('it-recovery.actions.disable-email-features'), ['it_password' => self::IT_PASSWORD])
            ->assertSessionHas('status', 'Require OTP and Allow Forgot Password were already off.');
        $this->assertSame(
            ['disabled', 'already_off'],
            ItRecoveryAuditLog::query()->where('event', 'action_disable_email_features')->orderBy('id')->get()->pluck('details.outcome')->all()
        );
    }

    public function test_dashboard_lists_only_minimal_account_fields(): void
    {
        $this->account();
        $user = $this->loginUser(['profile_picture' => 'profile-pictures/secret-avatar.png']);
        DB::table('login_user')->where('id', $user->id)->update(['remember_token' => 'remember-hash-value']);
        $this->signIn();

        $this->get(route('it-recovery.dashboard'))
            ->assertOk()
            ->assertSee('secretary@example.test')
            ->assertSee('Sec Retary')
            ->assertDontSee('secret-avatar.png')
            ->assertDontSee('remember-hash-value')
            ->assertDontSee(DB::table('login_user')->where('id', $user->id)->value('password'));
    }

    // ---- Audit log ----------------------------------------------------

    public function test_audit_log_is_readable_and_paginated(): void
    {
        $this->account();
        for ($i = 0; $i < 60; $i++) {
            ItRecoveryAuditLog::record('login_failed', 'seed'.$i.'@example.test');
        }
        $this->signIn();

        $this->get(route('it-recovery.audit'))
            ->assertOk()
            ->assertSee('login_succeeded')
            ->assertSee('Older');
        $this->get(route('it-recovery.audit', ['page' => 2]))
            ->assertOk()
            ->assertSee('seed0@example.test');

        $this->assertSame(2, ItRecoveryAuditLog::query()->where('event', 'audit_log_viewed')->count());
    }

    public function test_audit_log_has_no_update_or_delete_path(): void
    {
        $log = ItRecoveryAuditLog::record('login_succeeded', self::IT_EMAIL);

        $itRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'it-recovery'));
        $this->assertNotEmpty($itRoutes);
        foreach ($itRoutes as $route) {
            $this->assertEmpty(array_intersect($route->methods(), ['PUT', 'PATCH', 'DELETE']), $route->uri());
        }
        $this->assertFalse(collect(Route::getRoutes()->getRoutes())
            ->contains(fn (RoutingRoute $route): bool => ! str_starts_with($route->uri(), 'it-recovery')
                && str_contains((string) $route->getActionName(), 'ItRecovery')));

        try {
            $log->delete();
            $this->fail('Deleting an audit entry must be refused.');
        } catch (LogicException) {
        }
        try {
            $log->update(['event' => 'tampered']);
            $this->fail('Updating an audit entry must be refused.');
        } catch (LogicException) {
        }

        $this->assertSame('login_succeeded', $log->fresh()->event);
        $this->assertSame(1, ItRecoveryAuditLog::query()->count());
    }

    public function test_application_code_never_updates_or_deletes_the_it_audit_log(): void
    {
        $files = array_merge(
            glob(app_path('Http/Controllers/*.php')),
            glob(app_path('Services/*.php')),
            glob(app_path('Console/Commands/*.php')),
            [base_path('routes/web.php'), base_path('routes/it-recovery.php')]
        );

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                "/table\\(\\s*['\"]it_recovery_audit_logs['\"]\\s*\\)\\s*(->[^;]*)?->(update|delete|truncate)\\(/",
                $source,
                $file
            );
            $this->assertDoesNotMatchRegularExpression(
                '/ItRecoveryAuditLog::[^;]*->(update|delete|forceDelete|truncate)\(/',
                $source,
                $file
            );
        }
    }
}
