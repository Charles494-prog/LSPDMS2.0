<?php

namespace App\Services;

use App\Models\SystemSmtpSetting;
use App\Support\SmtpFailure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SystemSmtpConfiguration
{
    public const RESEND_COOLDOWN_SECONDS = 60;

    public const TIMEOUT_SECONDS = 10;

    /**
     * Email delivery is ready when the saved database account has been verified,
     * or when the .env SMTP settings are complete.
     */
    public function isConfigured(): bool
    {
        $settings = $this->databaseSettings();

        if ($settings !== null && $settings->isVerified()) {
            return true;
        }

        return $this->isEnvironmentConfigured();
    }

    /**
     * A sender account is saved but not confirmed (or its email/app password
     * changed since it was confirmed), or a replacement account is waiting
     * for its code.
     */
    public function needsVerification(): bool
    {
        $settings = $this->databaseSettings();

        return $settings !== null && (! $settings->isVerified() || $settings->hasPendingAccount());
    }

    public function isEnvironmentConfigured(): bool
    {
        return config('mail.default') === 'smtp'
            && config('mail.mailers.smtp.transport') === 'smtp'
            && filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'))
            && filled(config('mail.mailers.smtp.port'));
    }

    /**
     * The saved database account, only if it has both an email and an app password.
     */
    public function databaseSettings(): ?SystemSmtpSetting
    {
        if (! Schema::hasTable('system_smtp_settings')) {
            return null;
        }

        $settings = SystemSmtpSetting::query()->first();

        return $settings !== null && filled($settings->email) && filled($settings->app_password)
            ? $settings
            : null;
    }

    /**
     * The account a verification code is (or would be) sent through: the
     * pending replacement if there is one, otherwise the active account while
     * it is unverified. Null when the active account is verified and nothing
     * is pending.
     *
     * @return array{email: string, password: string, fingerprint: string, pending: bool}|null
     */
    public function verificationTarget(SystemSmtpSetting $settings): ?array
    {
        if ($settings->hasPendingAccount()) {
            return [
                'email' => (string) $settings->pending_email,
                'password' => (string) $settings->pending_app_password,
                'fingerprint' => (string) $settings->pendingFingerprint(),
                'pending' => true,
            ];
        }

        if (blank($settings->email) || blank($settings->app_password) || $settings->isVerified()) {
            return null;
        }

        return [
            'email' => (string) $settings->email,
            'password' => (string) $settings->app_password,
            'fingerprint' => $settings->fingerprint(),
            'pending' => false,
        ];
    }

    /** An unexpired code exists and was sent through the current target account. */
    public function hasOutstandingCode(SystemSmtpSetting $settings): bool
    {
        $target = $this->verificationTarget($settings);

        return $target !== null
            && filled($settings->verification_code_hash)
            && filled($settings->verification_target)
            && hash_equals((string) $settings->verification_target, $target['fingerprint'])
            && $settings->verification_expires_at !== null
            && $settings->verification_expires_at->isFuture();
    }

    /**
     * State handed to the dashboard's SMTP panel and popup.
     */
    public function verificationState(?string $recipient = null): array
    {
        $settings = $this->databaseSettings();
        if ($settings === null) {
            return [
                'saved' => false, 'email' => null, 'verified' => false, 'verified_at' => null, 'pending' => false,
                'pending_email' => null, 'sent_to' => null, 'expires_at' => null, 'resend_in' => 0, 'recipient' => $recipient,
                'failure_class' => null, 'failure_message' => null, 'failure_at' => null,
            ];
        }

        $verified = $settings->isVerified();
        $pending = $this->hasOutstandingCode($settings);
        $resendIn = $settings->verification_sent_at
            ? max(0, self::RESEND_COOLDOWN_SECONDS - (now()->timestamp - $settings->verification_sent_at->timestamp))
            : 0;
        $failureClass = in_array($settings->last_failure_class, [SmtpFailure::AUTH, SmtpFailure::CONNECTION, SmtpFailure::CONFIG], true)
            ? $settings->last_failure_class
            : null;

        return [
            'saved' => true,
            'email' => $settings->email,
            'verified' => $verified,
            'verified_at' => $verified ? $settings->verified_at?->toIso8601String() : null,
            'pending' => $pending,
            'pending_email' => $settings->hasPendingAccount() ? $settings->pending_email : null,
            'sent_to' => $pending ? $settings->verification_sent_to : null,
            'expires_at' => $pending ? $settings->verification_expires_at?->toIso8601String() : null,
            'resend_in' => $resendIn,
            'recipient' => $recipient,
            'failure_class' => $failureClass,
            'failure_message' => $failureClass !== null ? SmtpFailure::message($failureClass) : null,
            'failure_at' => $failureClass !== null ? $settings->last_failure_at?->toIso8601String() : null,
        ];
    }

    /**
     * Point Laravel's mailer at the saved Gmail account.
     * By default only a verified account is applied, so an unconfirmed account
     * is never used for OTP or password-recovery emails. Pass $force = true
     * to send the verification code itself through the unconfirmed account.
     */
    public function apply(bool $force = false): void
    {
        $settings = $this->databaseSettings();
        if ($settings === null || (! $force && ! $settings->isVerified())) {
            return;
        }

        $this->applyCredentials((string) $settings->email, (string) $settings->app_password);
    }

    /** Point the smtp mailer at explicit credentials (used for a pending account's code). */
    public function applyCredentials(string $email, string $password): void
    {
        Config::set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => 'smtp', // Symfony accepts smtp/smtps only; 'smtp' on 587 upgrades via STARTTLS
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => SystemSmtpSetting::HOST,
            'mail.mailers.smtp.port' => SystemSmtpSetting::PORT,
            'mail.mailers.smtp.username' => $email,
            'mail.mailers.smtp.password' => $password,
            // Sends happen inside the request, so a stalled Gmail connection must give up quickly.
            'mail.mailers.smtp.timeout' => (float) (config('mail.mailers.smtp.timeout') ?: self::TIMEOUT_SECONDS),
            'mail.from.address' => $email,
        ]);
        Mail::purge('smtp');
    }
}
