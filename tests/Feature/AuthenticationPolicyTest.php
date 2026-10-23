<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLinkMail;
use App\Models\GlobalVariable;
use App\Models\SystemSmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_policy_can_bypass_code_after_valid_credentials_when_disabled(): void
    {
        GlobalVariable::query()->create(['require_otp' => false]);
        $user = $this->verifiedUser('no-otp@example.test');

        $this->post(route('login'), [
            'email' => $user->gmail,
            'password' => 'valid-password',
        ])
            ->assertRedirect('/home')
            ->assertSessionHas('login_user.id', $user->id);

        $this->assertFalse(session()->has('pending_login_user'));
    }

    public function test_otp_is_disabled_by_default(): void
    {
        $user = $this->verifiedUser('otp@example.test');

        $this->post(route('login'), [
            'email' => $user->gmail,
            'password' => 'valid-password',
        ])
            ->assertRedirect('/home')
            ->assertSessionHas('login_user.id', $user->id);

        $this->assertFalse(session()->has('pending_login_user'));
    }

    public function test_invalid_credentials_return_to_login_with_an_error_even_if_referred_from_intro(): void
    {
        $this->withHeader('Referer', url('/intro'))
            ->post(route('login'), [
                'email' => 'unknown@example.test',
                'password' => 'incorrect-password',
            ])
            ->assertRedirect(route('login.form'))
            ->assertSessionHasErrors([
                'login' => 'Invalid username or password.',
            ]);

        $this->get(route('login.form'))
            ->assertOk()
            ->assertSee('Invalid username or password.')
            ->assertSee('value="unknown@example.test"', false);
    }

    public function test_login_page_can_refresh_its_csrf_token_without_changing_session(): void
    {
        $response = $this->get(route('login.csrf-token'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['token']);

        $this->assertSame(session()->token(), $response->json('token'));
        $this->get(route('login.form'))
            ->assertOk()
            ->assertSee('data-csrf-refresh-url', false)
            ->assertSee('response.status === 419', false)
            ->assertSee('responseDocument.querySelector(\'.login-error:not([hidden])\')', false)
            ->assertSee('Invalid username or password.', false);
    }

    public function test_secretary_feature_permissions_are_enforced_on_routes_and_navigation(): void
    {
        $secretary = $this->verifiedUser('secretary@example.test', [
            'tungkulin' => 'Secretary',
            'dashboard_allow' => true,
            'form_generator_allow' => false,
            'Delistment_allow' => false,
            'Inquire_Allow' => false,
        ]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            // Navigation links are filtered on the server (the dashboard's own help text may still name a feature).
            ->assertDontSee('href="'.route('de-listment').'"', false)
            ->assertDontSee('href="'.route('inquire-modify').'"', false)
            ->assertDontSee('href="'.route('tarheta-status').'"', false)
            ->assertDontSee('href="'.route('form-generator').'"', false);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('de-listment'))
            ->assertForbidden()
            ->assertSee('Your account does not have permission to access this feature.')
            ->assertSee('href="'.url('/home').'"', false)
            ->assertSee('Back to Home');
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('inquire-modify'))
            ->assertForbidden();
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('tarheta-status'))
            ->assertForbidden();
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('form-generator'))
            ->assertForbidden();
    }

    public function test_head_secretary_has_superadmin_and_feature_access_even_without_feature_flags(): void
    {
        $headSecretary = $this->verifiedUser('head@example.test', [
            'tungkulin' => 'Head Secretary',
            'dashboard_allow' => false,
            'form_generator_allow' => false,
            'Delistment_allow' => false,
            'Inquire_Allow' => false,
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk();
        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('dashboard'))
            ->assertOk();
        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('tarheta-status'))
            ->assertOk();
    }

    public function test_forgot_password_is_disabled_by_default(): void
    {
        $this->get(route('login.form'))
            ->assertOk()
            ->assertDontSee('Forgot password?');
        $this->get(route('password.request'))->assertNotFound();
        $this->post(route('password.email'), ['email' => 'person@example.test'])->assertNotFound();
        $this->post(route('password.email.resend'))->assertNotFound();
    }

    public function test_authentication_policy_database_defaults_are_disabled(): void
    {
        $settings = GlobalVariable::query()->create([]);

        $this->assertFalse($settings->fresh()->require_otp);
        $this->assertFalse($settings->fresh()->allow_forgot_password);
    }

    public function test_password_reset_email_flow_updates_password_and_revokes_remember_token(): void
    {
        GlobalVariable::query()->create([
            'require_otp' => false,
            'allow_forgot_password' => true,
        ]);
        $user = $this->verifiedUser('reset@example.test', [
            'remember_token' => Hash::make('remember-me'),
            'remember_token_expires_at' => now()->addDay(),
        ]);
        $this->verifiedSmtpAccount();
        Mail::fake();

        $this->post(route('password.email'), ['email' => $user->gmail])
            ->assertRedirect(route('password.request', ['sent' => 1]));
        $resetUrl = null;
        Mail::assertSent(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail) use ($user, &$resetUrl): bool {
            $resetUrl = $mail->url;
            $html = $mail->render();

            return $mail->hasTo($user->gmail)
                && str_contains($html, 'Choose a new password')
                && str_contains($html, '60 minutes')
                && str_contains($html, 'If you did not request a password reset');
        });
        $this->get(route('password.request', ['sent' => 1]))
            ->assertOk()
            ->assertSee('Request received')
            ->assertSee('we will try to email it a password reset link')
            ->assertSee('Send link again')
            ->assertSee('data-seconds="300"', false)
            ->assertSee('Go back to login');
        $this->assertNotNull($resetUrl);
        $parts = parse_url($resetUrl);
        parse_str($parts['query'] ?? '', $query);
        $token = basename($parts['path']);

        $this->get($resetUrl)
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('At least one special character');
        $this->post(route('password.reset'), [
            'email' => $user->gmail,
            'token' => $token,
            'password' => 'New-password-123!',
            'password_confirmation' => 'New-password-123!',
        ])
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Change of Password Detected, Please Login Again.');

        $this->assertTrue(Hash::check('New-password-123!', $user->fresh()->password));
        $this->assertNull($user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->gmail]);
        $this->assertSame(2, (int) $user->fresh()->auth_version);
        $this->assertSame('password_changed', $user->fresh()->auth_notice);
    }

    public function test_expired_password_reset_link_shows_the_expired_link_page(): void
    {
        GlobalVariable::query()->create(['allow_forgot_password' => true]);
        $this->verifiedSmtpAccount();
        $user = $this->verifiedUser('expired-reset@example.test');
        $token = str_repeat('a', 64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->gmail,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);
        $expiredUrl = URL::temporarySignedRoute(
            'password.reset.form',
            now()->subMinute(),
            ['token' => $token, 'email' => $user->gmail]
        );

        $this->get($expiredUrl)
            ->assertRedirect(route('password.reset.expired'));
        $this->get(route('password.reset.expired'))
            ->assertOk()
            ->assertSee('Link expired')
            ->assertSee('Request a new link');
    }

    public function test_password_reset_link_can_only_be_resent_after_five_minutes(): void
    {
        GlobalVariable::query()->create(['allow_forgot_password' => true]);
        $this->verifiedSmtpAccount();
        $user = $this->verifiedUser('resend-reset@example.test');
        Mail::fake();

        $this->post(route('password.email'), ['email' => $user->gmail])
            ->assertRedirect(route('password.request', ['sent' => 1]));
        $firstSentAt = DB::table('password_reset_tokens')->where('email', $user->gmail)->value('created_at');

        $this->post(route('password.email.resend'))
            ->assertRedirect(route('password.request', ['sent' => 1]));
        $this->assertSame(
            $firstSentAt,
            DB::table('password_reset_tokens')->where('email', $user->gmail)->value('created_at')
        );

        $this->travel(5)->minutes();
        $this->post(route('password.email.resend'))
            ->assertRedirect(route('password.request', ['sent' => 1]));
        $this->assertNotSame(
            $firstSentAt,
            DB::table('password_reset_tokens')->where('email', $user->gmail)->value('created_at')
        );
        $this->get(route('password.request', ['sent' => 1]))
            ->assertOk()
            ->assertSee('data-seconds="300"', false);
        Mail::assertSent(PasswordResetLinkMail::class, 2);
    }

    public function test_logout_invalidates_other_active_sessions_for_the_account(): void
    {
        $user = $this->verifiedUser('logout-all@example.test', [
            'remember_token' => Hash::make('remember-me'),
            'remember_token_expires_at' => now()->addDay(),
        ]);

        $this->withSession(['login_user' => ['id' => $user->id, 'auth_version' => 1]])
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertSame(2, (int) $user->fresh()->auth_version);
        $this->assertSame('signed_out_elsewhere', $user->fresh()->auth_notice);
        $this->assertNull($user->fresh()->remember_token);

        $this->withSession([
            '_token' => 'preserve-this-csrf-token',
            'login_user' => ['id' => $user->id, 'auth_version' => 1],
        ])
            ->getJson(route('session.status'))
            ->assertUnauthorized()
            ->assertJsonPath('session_invalid', true)
            ->assertJsonPath('reason', 'signed_out_elsewhere');
        // The stale session is destroyed, so its CSRF token can no longer submit anything.
        $this->assertNotSame('preserve-this-csrf-token', session()->token());
        $this->assertFalse(session()->has('login_user'));
    }

    public function test_password_reset_revokes_existing_sessions(): void
    {
        GlobalVariable::query()->create(['allow_forgot_password' => true]);
        $this->verifiedSmtpAccount();
        $user = $this->verifiedUser('reset-revokes@example.test');
        $token = str_repeat('x', 64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->gmail,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->withSession(['login_user' => ['id' => $user->id, 'auth_version' => 1]])
            ->post(route('password.reset'), [
                'email' => $user->gmail,
                'token' => $token,
                'password' => 'Changed-password-123!',
                'password_confirmation' => 'Changed-password-123!',
            ])
            ->assertRedirect('/login');

        $this->withSession(['login_user' => ['id' => $user->id, 'auth_version' => 1]])
            ->getJson(route('session.status'))
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'password_changed');

        $this->withSession(['login_user' => ['id' => $user->id, 'auth_version' => 1]])
            ->get(route('login.form'))
            ->assertOk()
            ->assertSee('Change of Password Detected, Please Login Again.')
            ->assertSee('name="_token"', false);
    }

    private function verifiedSmtpAccount(): SystemSmtpSetting
    {
        $settings = SystemSmtpSetting::query()->create([
            'email' => 'sender@example.test',
            'app_password' => 'abcdabcdabcdabcd',
        ]);
        $settings->forceFill([
            'verified_at' => now(),
            'verified_fingerprint' => SystemSmtpSetting::fingerprintFor('sender@example.test', 'abcdabcdabcdabcd'),
        ])->save();

        return $settings;
    }

    private function verifiedUser(string $email, array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'gmail' => $email,
            'password' => 'valid-password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
    }
}
