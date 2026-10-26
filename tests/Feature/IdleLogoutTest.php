<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\IdleTimeout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdleLogoutTest extends TestCase
{
    use RefreshDatabase;

    private const NOTICE = 'You were signed out after 2 hours of inactivity.';

    private const BACKGROUND = ['X-LSPDMS-Background' => '1'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.idle_timeout' => 120]);
        $this->freezeSecond();
    }

    public function test_a_normal_request_counts_as_activity(): void
    {
        $user = $this->user();

        $this->signedIn($user, minutesAgo: 60)->get('/home')->assertOk();

        $this->assertSame(now()->timestamp, session(IdleTimeout::SESSION_KEY));
    }

    public function test_background_requests_never_count_as_activity(): void
    {
        $user = $this->user();
        $lastActivity = now()->subMinutes(60)->timestamp;

        // Marked by the header (a poll of an ordinary data route) ...
        $this->signedIn($user, minutesAgo: 60)
            ->getJson(route('secretary-todos.index', ['scope' => 'private']), self::BACKGROUND)
            ->assertOk();
        $this->assertSame($lastActivity, session(IdleTimeout::SESSION_KEY));

        // ... or by the server's own list of polling routes, even without the header.
        foreach (['session.status', 'messages.unread-count', 'assigned-tasks.unseen'] as $route) {
            $this->signedIn($user, minutesAgo: 60)->getJson(route($route))->assertOk();
            $this->assertSame($lastActivity, session(IdleTimeout::SESSION_KEY), $route);
        }
    }

    public function test_the_session_status_poll_reports_the_time_left(): void
    {
        $this->signedIn($this->user(), minutesAgo: 100)
            ->getJson(route('session.status'))
            ->assertOk()
            ->assertJsonPath('idle_remaining', 20 * 60);
    }

    public function test_an_idle_background_request_gets_401_json_and_the_session_ends(): void
    {
        $user = $this->user();

        $this->signedIn($user, minutesAgo: 121, extra: ['_token' => 'old-csrf-token'])
            ->getJson(route('session.status'), self::BACKGROUND)
            ->assertUnauthorized()
            ->assertJson(['session_invalid' => true, 'reason' => 'idle', 'message' => self::NOTICE]);

        $this->assertFalse(session()->has('login_user'));
        $this->assertNotSame('old-csrf-token', session()->token());
        // Only this browser is signed out: other devices of the account keep working.
        $this->assertSame(1, (int) $user->fresh()->auth_version);
    }

    public function test_polling_alone_does_not_keep_the_session_alive(): void
    {
        $user = $this->user();
        $this->signedIn($user, minutesAgo: 0);

        foreach (range(1, 12) as $step) {
            $this->travel(10)->minutes();
            $response = $this->getJson(route('session.status'), self::BACKGROUND);
            if ($step < 12) {
                $response->assertOk();
            }
        }

        $response->assertUnauthorized()->assertJsonPath('reason', 'idle');
    }

    public function test_an_idle_page_load_redirects_to_sign_in_with_the_notice(): void
    {
        $this->signedIn($this->user(), minutesAgo: 120)
            ->get('/home')
            ->assertRedirect('/login?idle=1');

        $this->get('/login?idle=1')->assertOk()->assertSee(self::NOTICE);
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_the_notice_follows_the_configured_limit(): void
    {
        config(['session.idle_timeout' => 2]);

        $this->assertSame('You were signed out after 2 minutes of inactivity.', IdleTimeout::notice());
        config(['session.idle_timeout' => 60]);
        $this->assertSame('You were signed out after 1 hour of inactivity.', IdleTimeout::notice());
    }

    public function test_the_heartbeat_records_activity_the_browser_saw(): void
    {
        $this->signedIn($this->user(), minutesAgo: 100)
            ->postJson(route('session.heartbeat'), ['idle_seconds' => 30])
            ->assertOk()
            ->assertJsonPath('remaining', 120 * 60 - 30);

        $this->assertSame(now()->subSeconds(30)->timestamp, session(IdleTimeout::SESSION_KEY));

        // An older report never moves the time backwards.
        $this->postJson(route('session.heartbeat'), ['idle_seconds' => 600])->assertOk();
        $this->assertSame(now()->subSeconds(30)->timestamp, session(IdleTimeout::SESSION_KEY));
    }

    public function test_a_heartbeat_after_the_limit_is_too_late(): void
    {
        $this->signedIn($this->user(), minutesAgo: 125)
            ->postJson(route('session.heartbeat'), ['idle_seconds' => 0])
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'idle');

        $this->assertFalse(session()->has('login_user'));
    }

    public function test_the_browser_countdown_ending_signs_out_only_when_the_server_agrees(): void
    {
        $this->signedIn($this->user(), minutesAgo: 90)
            ->postJson(route('session.idle-logout'))
            ->assertOk()
            ->assertJson(['signed_out' => false, 'remaining' => 30 * 60]);
        $this->assertTrue(session()->has('login_user'));

        $this->signedIn($this->user('second@example.test'), minutesAgo: 120, secondsAgo: -20)
            ->postJson(route('session.idle-logout'))
            ->assertOk()
            ->assertJson(['signed_out' => true]);
        $this->assertFalse(session()->has('login_user'));
    }

    public function test_the_signed_out_page_shows_the_notice_on_the_sign_in_page(): void
    {
        $this->get(route('session.signed-out'))->assertRedirect('/login?idle=1');
        // Each tab shows the notice itself, so several tabs arriving at once never share one flash.
        $this->get('/login?idle=1')->assertOk()->assertSee(self::NOTICE);
        $this->get('/login?idle=1')->assertOk()->assertSee(self::NOTICE);

        // A session that is still active is not signed out by visiting it.
        $this->signedIn($this->user(), minutesAgo: 10)
            ->get(route('session.signed-out'))
            ->assertRedirect('/home');
        $this->assertTrue(session()->has('login_user'));
    }

    public function test_idle_sign_out_revokes_this_browsers_remember_me(): void
    {
        $user = $this->user('remember@example.test', [
            'remember_token' => Hash::make('remember-secret'),
            'remember_token_expires_at' => now()->addDays(10),
        ]);

        $this->signedIn($user, minutesAgo: 130)
            ->withCookie('remember_token', $user->id.'|remember-secret')
            ->get('/home')
            ->assertRedirect('/login?idle=1')
            ->assertCookieExpired('remember_token');

        $this->assertNull($user->fresh()->remember_token);
        $this->assertSame(1, (int) $user->fresh()->auth_version);
    }

    public function test_logout_on_an_idle_session_still_signs_out_everywhere(): void
    {
        $user = $this->user();

        $this->signedIn($user, minutesAgo: 130)
            ->post('/logout')
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'You have been logged out.');

        $this->assertSame(2, (int) $user->fresh()->auth_version);
    }

    public function test_signing_in_starts_the_countdown(): void
    {
        $user = $this->user('login@example.test');

        $this->post('/login', ['email' => 'login@example.test', 'password' => 'valid-password'])
            ->assertRedirect('/home');

        $this->assertSame((int) $user->id, (int) session('login_user')['id']);
        $this->assertSame(now()->timestamp, session(IdleTimeout::SESSION_KEY));
    }

    public function test_guest_pages_are_unaffected(): void
    {
        $this->withSession([IdleTimeout::SESSION_KEY => now()->subDay()->timestamp]);

        $this->get('/login')->assertOk()->assertDontSee('inactivity');
        $this->get('/register')->assertOk();
        $this->get('/intro')->assertOk();
        $this->get(route('it-recovery.login'))->assertOk();
    }

    public function test_signed_in_pages_carry_the_idle_settings_for_the_browser(): void
    {
        $this->signedIn($this->user(), minutesAgo: 0)
            ->get('/home')
            ->assertOk()
            ->assertSee('name="lspdms-idle" content="7200"', false)
            ->assertSee(route('session.heartbeat'), false)
            ->assertSee(route('session.signed-out'), false);
    }

    public function test_zero_minutes_turns_idle_sign_out_off(): void
    {
        config(['session.idle_timeout' => 0]);

        $this->signedIn($this->user(), minutesAgo: 600)->get('/home')->assertOk();
    }

    public function test_the_session_outlives_the_idle_limit(): void
    {
        $config = require config_path('session.php');

        $this->assertGreaterThanOrEqual($config['idle_timeout'] + 30, $config['lifetime']);
    }

    private function signedIn(User $user, int $minutesAgo, int $secondsAgo = 0, array $extra = []): static
    {
        return $this->withSession($extra + [
            'login_user' => ['id' => $user->id, 'auth_version' => 1],
            IdleTimeout::SESSION_KEY => now()->subMinutes($minutesAgo)->subSeconds($secondsAgo)->timestamp,
        ]);
    }

    private function user(string $email = 'idle@example.test', array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'gmail' => $email,
            'password' => 'valid-password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
    }
}
