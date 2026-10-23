<?php

namespace Tests\Feature;

use App\Mail\SmtpSetupVerificationMail;
use App\Models\GlobalVariable;
use App\Models\SystemSmtpSetting;
use App\Models\User;
use App\Services\SecurityPolicy;
use App\Support\SmtpFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SmtpVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_account_is_verified_only_with_the_emailed_code(): void
    {
        Mail::fake();
        $head = $this->headSecretary();

        $this->asHead($head)->putJson(route('superadmin.smtp-settings.update'), [
            'email' => 'sender@example.test',
            'app_password' => 'abcd abcd abcd abcd',
        ])->assertOk()->assertJsonPath('email_sent', true);

        $code = $this->sentCode();
        $this->asHead($head)->postJson(route('superadmin.smtp-settings.verification.verify'), ['code' => $this->otherCode($code)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Incorrect code. 4 attempts left.');
        $this->assertFalse(SystemSmtpSetting::query()->firstOrFail()->isVerified());

        $this->asHead($head)->postJson(route('superadmin.smtp-settings.verification.verify'), ['code' => $code])
            ->assertOk()
            ->assertJsonPath('state.verified', true);
        $setting = SystemSmtpSetting::query()->firstOrFail();
        $this->assertTrue($setting->isVerified());
        $this->assertNull($setting->verification_code_hash);
        $this->assertNotSame($code, (string) $setting->verified_fingerprint);
    }

    public function test_code_is_locked_after_five_wrong_attempts_and_expires_after_ten_minutes(): void
    {
        Mail::fake();
        $head = $this->headSecretary();
        $this->saveSender($head);
        $code = $this->sentCode();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->asHead($head)->postJson(route('superadmin.smtp-settings.verification.verify'), ['code' => $this->otherCode($code)])
                ->assertUnprocessable();
        }
        // The real code no longer works once the attempts are used up.
        $this->asHead($head)->postJson(route('superadmin.smtp-settings.verification.verify'), ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'This code has expired. Send a new one.');

        $this->travel(61)->seconds();
        $this->asHead($head)->postJson(route('superadmin.smtp-settings.verification.send'))->assertOk();
        $fresh = $this->sentCode();
        $this->travel(11)->minutes();
        $this->asHead($head)->postJson(route('superadmin.smtp-settings.verification.verify'), ['code' => $fresh])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'This code has expired. Send a new one.');
    }

    public function test_resend_has_a_cooldown(): void
    {
        Mail::fake();
        $head = $this->headSecretary();
        $this->saveSender($head);

        $this->asHead($head)->postJson(route('superadmin.smtp-settings.verification.send'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        Mail::assertSent(SmtpSetupVerificationMail::class, 1);
    }

    public function test_replacing_a_verified_sender_keeps_it_active_until_the_new_one_is_confirmed(): void
    {
        Mail::fake();
        $head = $this->headSecretary();
        $this->verifiedSender('old@example.test', 'oldpasswordoldpw');
        GlobalVariable::query()->create(['require_otp' => true, 'allow_forgot_password' => true]);

        $this->asHead($head)->putJson(route('superadmin.smtp-settings.update'), [
            'email' => 'new@example.test',
            'app_password' => 'newpasswordnewpw',
        ])->assertOk()->assertJsonPath('state.pending_email', 'new@example.test');

        $setting = SystemSmtpSetting::query()->firstOrFail();
        $this->assertSame('old@example.test', $setting->email);
        $this->assertTrue($setting->isVerified());
        $this->assertTrue(app(SecurityPolicy::class)->emailFeaturesAvailable());
        $this->assertTrue(GlobalVariable::query()->firstOrFail()->require_otp);
        Mail::assertSent(SmtpSetupVerificationMail::class, fn (SmtpSetupVerificationMail $mail): bool => $mail->senderEmail === 'new@example.test');

        $this->asHead($head)->postJson(route('superadmin.smtp-settings.verification.verify'), ['code' => $this->sentCode()])
            ->assertOk();
        $setting->refresh();
        $this->assertSame('new@example.test', $setting->email);
        $this->assertSame('newpasswordnewpw', $setting->app_password);
        $this->assertNull($setting->pending_email);
        $this->assertTrue($setting->isVerified());
    }

    public function test_changing_the_account_or_transport_invalidates_verification(): void
    {
        $setting = $this->verifiedSender('sender@example.test', 'abcdabcdabcdabcd');
        $this->assertTrue($setting->isVerified());

        $setting->app_password = 'changedchangedch';
        $this->assertFalse($setting->isVerified());

        $setting->app_password = 'abcdabcdabcdabcd';
        $setting->verified_fingerprint = hash_hmac('sha256', 'v2|smtp.other.test|465|ssl|sender@example.test|abcdabcdabcdabcd', (string) config('app.key'));
        $this->assertFalse($setting->isVerified());
    }

    public function test_legacy_fingerprint_stays_verified_and_is_upgraded(): void
    {
        $setting = SystemSmtpSetting::query()->create(['email' => 'legacy@example.test', 'app_password' => 'legacylegacylegy']);
        $legacy = hash_hmac('sha256', 'legacy@example.test|legacylegacylegy', (string) config('app.key'));
        $setting->forceFill(['verified_at' => now(), 'verified_fingerprint' => $legacy])->save();

        $this->assertTrue($setting->fresh()->isVerified());
        $this->assertSame(
            SystemSmtpSetting::fingerprintFor('legacy@example.test', 'legacylegacylegy'),
            SystemSmtpSetting::query()->firstOrFail()->verified_fingerprint
        );
    }

    public function test_security_features_cannot_be_enabled_without_a_verified_sender_but_can_always_be_disabled(): void
    {
        $head = $this->headSecretary();
        GlobalVariable::query()->create(['require_otp' => false, 'allow_forgot_password' => false]);

        foreach ([['require_otp' => 1, 'allow_forgot_password' => 0], ['require_otp' => 0, 'allow_forgot_password' => 1]] as $payload) {
            $this->asHead($head)->putJson(route('superadmin.security-settings.update'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('security');
        }
        $this->assertFalse(GlobalVariable::query()->firstOrFail()->require_otp);

        $this->asHead($head)->putJson(route('superadmin.security-settings.update'), ['require_otp' => 0, 'allow_forgot_password' => 0])
            ->assertOk();

        $this->verifiedSender('sender@example.test', 'abcdabcdabcdabcd');
        $this->asHead($head)->putJson(route('superadmin.security-settings.update'), ['require_otp' => 1, 'allow_forgot_password' => 1])
            ->assertOk()
            ->assertJsonPath('data.require_otp', true);
    }

    public function test_enabling_otp_revokes_remember_tokens(): void
    {
        $head = $this->headSecretary(['remember_token' => 'hash', 'remember_token_expires_at' => now()->addDay()]);
        GlobalVariable::query()->create(['require_otp' => false, 'allow_forgot_password' => false]);
        $this->verifiedSender('sender@example.test', 'abcdabcdabcdabcd');

        $this->asHead($head)->putJson(route('superadmin.security-settings.update'), ['require_otp' => 1, 'allow_forgot_password' => 0])
            ->assertOk();

        $this->assertNull($head->fresh()->remember_token);
    }

    public function test_secretary_cannot_change_security_or_smtp_settings(): void
    {
        $secretary = User::query()->create([
            'gmail' => 'secretary@example.test', 'password' => 'secret-password', 'tungkulin' => 'Secretary', 'is_verified' => true,
        ]);
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->putJson(route('superadmin.security-settings.update'), ['require_otp' => 0, 'allow_forgot_password' => 0])
            ->assertForbidden();
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->putJson(route('superadmin.smtp-settings.update'), ['email' => 'x@example.test', 'app_password' => 'abcdabcdabcdabcd'])
            ->assertForbidden();
    }

    public function test_removing_the_sender_turns_the_email_features_off(): void
    {
        $head = $this->headSecretary();
        $this->verifiedSender('sender@example.test', 'abcdabcdabcdabcd');
        GlobalVariable::query()->create(['require_otp' => true, 'allow_forgot_password' => true]);

        $this->asHead($head)->deleteJson(route('superadmin.smtp-settings.destroy'))->assertOk();

        $settings = GlobalVariable::query()->firstOrFail();
        $this->assertFalse($settings->require_otp);
        $this->assertFalse($settings->allow_forgot_password);
        $this->assertDatabaseHas('system_maintenance_audits', ['action' => 'email_features_off:smtp_removed']);
    }

    public function test_auth_delivery_failure_unverifies_the_sender_and_disables_the_features(): void
    {
        $this->verifiedSender('sender@example.test', 'abcdabcdabcdabcd');
        GlobalVariable::query()->create(['require_otp' => true, 'allow_forgot_password' => true]);

        $class = app(SecurityPolicy::class)->reportDeliveryFailure(
            new TransportException('Failed to authenticate on SMTP server with username "sender@example.test"', 535),
            'login_otp'
        );

        $this->assertSame(SmtpFailure::AUTH, $class);
        $setting = SystemSmtpSetting::query()->firstOrFail();
        $this->assertFalse($setting->isVerified());
        $this->assertSame('auth', $setting->last_failure_class);
        $this->assertFalse(GlobalVariable::query()->firstOrFail()->require_otp);
    }

    public function test_failure_classes_are_safe_and_distinct(): void
    {
        $this->assertSame(SmtpFailure::CONNECTION, SmtpFailure::classify(new TransportException('Connection could not be established with host "smtp.gmail.com:587"')));
        $this->assertSame(SmtpFailure::CONFIG, SmtpFailure::classify(new RuntimeException('Unable to open path "logo.png"')));
        foreach ([SmtpFailure::AUTH, SmtpFailure::CONNECTION, SmtpFailure::CONFIG] as $class) {
            $this->assertStringNotContainsString('@', SmtpFailure::message($class));
        }
    }

    public function test_setup_send_failure_reports_its_class_without_secrets(): void
    {
        $head = $this->headSecretary();
        Mail::shouldReceive('mailer')->andThrow(new TransportException('Failed to authenticate on SMTP server with username "sender@example.test"', 535));
        Mail::shouldReceive('purge');

        $this->asHead($head)->putJson(route('superadmin.smtp-settings.update'), [
            'email' => 'sender@example.test',
            'app_password' => 'abcdabcdabcdabcd',
        ])
            ->assertOk()
            ->assertJsonPath('email_sent', false)
            ->assertJsonPath('state.failure_class', 'auth')
            ->assertDontSee('abcdabcdabcdabcd');
        $this->assertSame(0, DB::table('system_smtp_settings')->whereNotNull('verification_code_hash')->count());
    }

    private function headSecretary(array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
    }

    private function asHead(User $head): static
    {
        return $this->withSession(['login_user' => ['id' => $head->id, 'gmail' => $head->gmail]]);
    }

    private function saveSender(User $head): void
    {
        $this->asHead($head)->putJson(route('superadmin.smtp-settings.update'), [
            'email' => 'sender@example.test',
            'app_password' => 'abcdabcdabcdabcd',
        ])->assertOk();
    }

    private function verifiedSender(string $email, string $password): SystemSmtpSetting
    {
        $setting = SystemSmtpSetting::query()->create(['email' => $email, 'app_password' => $password]);
        $setting->forceFill([
            'verified_at' => now(),
            'verified_fingerprint' => SystemSmtpSetting::fingerprintFor($email, $password),
        ])->save();

        return $setting;
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(SmtpSetupVerificationMail::class, function (SmtpSetupVerificationMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    private function otherCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }
}
