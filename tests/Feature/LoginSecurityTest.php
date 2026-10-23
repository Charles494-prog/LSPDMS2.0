<?php

namespace Tests\Feature;

use App\Mail\LoginVerificationCodeMail;
use App\Mail\PasswordResetLinkMail;
use App\Models\GlobalVariable;
use App\Models\SystemSmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_wrong_passwords_lock_the_account_progressively(): void
    {
        $user = $this->user('lock@example.test');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login'), ['email' => $user->gmail, 'password' => 'wrong-password'])
                ->assertSessionHasErrors(['login' => 'Invalid username or password.']);
        }

        // Even the correct password is refused while locked, with a generic message.
        $this->post(route('login'), ['email' => $user->gmail, 'password' => 'valid-password'])
            ->assertSessionHasErrors('login');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('login'));
        $this->assertFalse(session()->has('login_user'));

        $this->travel(61)->seconds();
        $this->post(route('login'), ['email' => $user->gmail, 'password' => 'valid-password'])
            ->assertRedirect('/home');
    }

    public function test_a_legacy_plaintext_password_works_once_and_is_rehashed(): void
    {
        $user = $this->user('legacy@example.test');
        DB::table('login_user')->where('id', $user->id)->update(['password' => 'Legacy-plain-1!']);

        $this->post(route('login'), ['email' => $user->gmail, 'password' => 'Legacy-plain-1!'])
            ->assertRedirect('/home');

        $stored = (string) DB::table('login_user')->where('id', $user->id)->value('password');
        $this->assertTrue(Hash::isHashed($stored));
        $this->assertTrue(Hash::check('Legacy-plain-1!', $stored));
        $this->assertSame(1, (int) $user->fresh()->auth_version);
    }

    public function test_plaintext_passwords_are_never_accepted_by_reauthentication_prompts(): void
    {
        $head = $this->user('head@example.test', ['tungkulin' => 'Head Secretary']);
        DB::table('login_user')->where('id', $head->id)->update(['password' => 'Legacy-plain-1!']);

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->put(route('profile.password.update'), [
                'current_password' => 'Legacy-plain-1!',
                'password' => 'Brand-new-pass-1!',
                'password_confirmation' => 'Brand-new-pass-1!',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_otp_code_is_limited_to_five_wrong_attempts(): void
    {
        $this->enableOtpWithVerifiedSender();
        Mail::fake();
        $user = $this->user('otp@example.test');

        $this->post(route('login'), ['email' => $user->gmail, 'password' => 'valid-password'])
            ->assertRedirect('/verify-code');
        Mail::assertSent(LoginVerificationCodeMail::class);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->post('/verify-code', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/verify-code', ['code' => '000000'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login');
        $this->assertFalse(session()->has('pending_login_user'));
        $this->assertFalse(session()->has('login_user'));
    }

    public function test_resend_is_capped_per_pending_login(): void
    {
        $this->enableOtpWithVerifiedSender();
        Mail::fake();
        $user = $this->user('resend@example.test');
        $this->post(route('login'), ['email' => $user->gmail, 'password' => 'valid-password']);

        for ($resend = 1; $resend <= 5; $resend++) {
            $this->travel(61)->seconds();
            $this->post(route('verify-code.resend'))->assertSessionHasNoErrors();
        }
        $this->travel(61)->seconds();
        $this->post(route('verify-code.resend'))->assertRedirect('/login');
        Mail::assertSent(LoginVerificationCodeMail::class, 6);
    }

    public function test_failed_otp_delivery_falls_back_to_password_only_and_turns_otp_off(): void
    {
        $this->enableOtpWithVerifiedSender();
        Mail::shouldReceive('to')->andThrow(new TransportException('Connection could not be established with host "smtp.gmail.com:587"'));
        Mail::shouldReceive('purge');
        $user = $this->user('fallback@example.test');

        $this->post(route('login'), ['email' => $user->gmail, 'password' => 'valid-password'])
            ->assertRedirect('/home')
            ->assertSessionHas('login_user.id', $user->id)
            ->assertSessionHas('status', 'Email verification is temporarily unavailable, so you were signed in with your password only.');

        $this->assertFalse(GlobalVariable::query()->firstOrFail()->require_otp);
        $this->assertSame('connection', SystemSmtpSetting::query()->firstOrFail()->last_failure_class);
    }

    public function test_otp_without_a_verified_sender_never_locks_users_out(): void
    {
        GlobalVariable::query()->create(['require_otp' => true, 'allow_forgot_password' => false]);
        $user = $this->user('nosmtp@example.test');

        $this->post(route('login'), ['email' => $user->gmail, 'password' => 'valid-password'])
            ->assertRedirect('/home')
            ->assertSessionHas('login_user.id', $user->id);
        $this->assertFalse(GlobalVariable::query()->firstOrFail()->require_otp);
    }

    public function test_verify_code_page_without_a_pending_login_redirects_instead_of_erroring(): void
    {
        $this->get('/verify-code')->assertRedirect('/login');

        $user = $this->user('signed-in@example.test');
        $this->withSession(['login_user' => ['id' => $user->id, 'auth_version' => 1]])
            ->get('/verify-code')
            ->assertRedirect('/home');
    }

    public function test_guest_403_page_renders_without_the_signed_in_layout(): void
    {
        // Guests hitting a 403 previously crashed with "Undefined array key id".
        $response = $this->view('errors.403', ['exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Denied.')]);
        $response->assertSee('Denied.')->assertDontSee('id="chatWidget"', false);
    }

    public function test_logout_destroys_the_session_and_rotates_the_csrf_token(): void
    {
        $user = $this->user('logout@example.test');
        $this->withSession(['_token' => 'old-token', 'login_user' => ['id' => $user->id, 'auth_version' => 1]])
            ->post('/logout', ['_token' => 'old-token'])
            ->assertRedirect('/login');

        $this->assertFalse(session()->has('login_user'));
        $this->assertNotSame('old-token', session()->token());
        $this->getJson(route('session.status'))
            ->assertUnauthorized()
            ->assertJsonPath('session_invalid', true);
    }

    public function test_session_status_reports_identity_and_signed_in_pages_are_not_cached(): void
    {
        $user = $this->user('status@example.test');
        $user->forceFill(['auth_version' => 3])->save();

        $this->withSession(['login_user' => ['id' => $user->id, 'auth_version' => 3]])
            ->getJson(route('session.status'))
            ->assertOk()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('auth_version', 3);

        $response = $this->withSession(['login_user' => ['id' => $user->id, 'auth_version' => 3]])->get('/home');
        $response->assertOk()->assertSee('name="lspdms-user" content="'.$user->id.':3"', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_forgot_password_gives_the_same_answer_for_known_unknown_and_failed_sends(): void
    {
        GlobalVariable::query()->create(['allow_forgot_password' => true]);
        $this->verifiedSender();
        Mail::fake();
        $user = $this->user('known@example.test');

        $known = $this->post(route('password.email'), ['email' => $user->gmail]);
        $unknown = $this->post(route('password.email'), ['email' => 'unknown@example.test']);

        foreach ([$known, $unknown] as $response) {
            $response->assertRedirect(route('password.request', ['sent' => 1]))->assertSessionHasNoErrors();
        }
        Mail::assertSent(PasswordResetLinkMail::class, 1);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@example.test']);
    }

    public function test_failed_reset_email_is_not_revealed_and_switches_the_feature_off(): void
    {
        GlobalVariable::query()->create(['allow_forgot_password' => true]);
        $this->verifiedSender();
        Mail::shouldReceive('to')->andThrow(new TransportException('Failed to authenticate on SMTP server', 535));
        Mail::shouldReceive('purge');
        $user = $this->user('fails@example.test');

        $this->post(route('password.email'), ['email' => $user->gmail])
            ->assertRedirect(route('password.request', ['sent' => 1]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->gmail]);
        $this->assertFalse(GlobalVariable::query()->firstOrFail()->allow_forgot_password);
        $this->get(route('password.request'))->assertNotFound();
    }

    public function test_head_secretary_cannot_clear_an_accounts_role(): void
    {
        $head = $this->user('head2@example.test', ['tungkulin' => 'Head Secretary']);
        $secretary = $this->user('role@example.test');

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->putJson(route('superadmin.users.update', $secretary), ['tungkulin' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tungkulin');
        $this->assertSame('Secretary', $secretary->fresh()->tungkulin);
    }

    private function user(string $email, array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'gmail' => $email,
            'password' => 'valid-password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
    }

    private function verifiedSender(): void
    {
        $setting = SystemSmtpSetting::query()->create(['email' => 'sender@example.test', 'app_password' => 'abcdabcdabcdabcd']);
        $setting->forceFill([
            'verified_at' => now(),
            'verified_fingerprint' => SystemSmtpSetting::fingerprintFor('sender@example.test', 'abcdabcdabcdabcd'),
        ])->save();
    }

    private function enableOtpWithVerifiedSender(): void
    {
        $this->verifiedSender();
        GlobalVariable::query()->create(['require_otp' => true, 'allow_forgot_password' => false]);
    }
}
