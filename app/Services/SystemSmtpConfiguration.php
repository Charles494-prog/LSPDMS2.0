<?php

namespace App\Services;

use App\Models\SystemSmtpSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SystemSmtpConfiguration
{
    public const RESEND_COOLDOWN_SECONDS = 60;

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
     * changed since it was confirmed).
     */
    public function needsVerification(): bool
    {
        $settings = $this->databaseSettings();

        return $settings !== null && ! $settings->isVerified();
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
     * State handed to the dashboard's SMTP panel and popup.
     */
    public function verificationState(?string $recipient = null): array
    {
        $settings = $this->databaseSettings();
        if ($settings === null) {
            return [
                'saved' => false, 'email' => null, 'verified' => false, 'verified_at' => null, 'pending' => false,
                'sent_to' => null, 'expires_at' => null, 'resend_in' => 0, 'recipient' => $recipient,
            ];
        }

        $verified = $settings->isVerified();
        $pending = ! $verified
            && filled($settings->verification_code_hash)
            && $settings->verification_expires_at !== null
            && $settings->verification_expires_at->isFuture();
        $resendIn = $settings->verification_sent_at
            ? max(0, self::RESEND_COOLDOWN_SECONDS - (now()->timestamp - $settings->verification_sent_at->timestamp))
            : 0;

        return [
            'saved' => true,
            'email' => $settings->email,
            'verified' => $verified,
            'verified_at' => $verified ? $settings->verified_at?->toIso8601String() : null,
            'pending' => $pending,
            'sent_to' => $pending ? $settings->verification_sent_to : null,
            'expires_at' => $pending ? $settings->verification_expires_at?->toIso8601String() : null,
            'resend_in' => $resendIn,
            'recipient' => $recipient,
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

        Config::set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => 'smtp', // Symfony accepts smtp/smtps only; 'smtp' on 587 upgrades via STARTTLS
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => $settings->email,
            'mail.mailers.smtp.password' => $settings->app_password,
            'mail.from.address' => $settings->email,
        ]);
        Mail::purge('smtp');
    }
}