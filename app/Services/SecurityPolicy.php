<?php

namespace App\Services;

use App\Models\SystemSmtpSetting;
use App\Support\SmtpFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Email-dependent security features (Require OTP, Allow Forgot Password).
 * They may only be on while a verified SMTP account can deliver email, and
 * they are switched off automatically when delivery is no longer possible,
 * so nobody is locked out: login falls back to password only.
 */
class SecurityPolicy
{
    public function __construct(private readonly SystemSmtpConfiguration $smtp) {}

    /**
     * A verified database SMTP account exists, so OTP and reset emails can be sent.
     */
    public function emailFeaturesAvailable(): bool
    {
        $settings = $this->smtp->databaseSettings();

        return $settings !== null && $settings->isVerified();
    }

    /**
     * Turn the features off when SMTP is missing or no longer verified (for
     * example its address or app password changed). Returns true if anything
     * was switched off.
     */
    public function enforceEmailAvailability(?string $actor = null): bool
    {
        if ($this->emailFeaturesAvailable()) {
            return false;
        }

        $reason = $this->smtp->databaseSettings() === null ? 'smtp_not_configured' : 'smtp_unverified';

        return $this->disableEmailDependentFeatures($reason, $actor);
    }

    /**
     * Turn off Require OTP and Allow Forgot Password. Returns true when at
     * least one of them was on. $reason is a short code such as 'smtp_removed'.
     */
    public function disableEmailDependentFeatures(string $reason, ?string $actor = null): bool
    {
        if (! Schema::hasTable('global_variables')) {
            return false;
        }

        return DB::transaction(function () use ($reason, $actor): bool {
            $settings = DB::table('global_variables')->lockForUpdate()->first();
            if ($settings === null || (! $settings->require_otp && ! $settings->allow_forgot_password)) {
                return false;
            }

            $changed = (int) (bool) $settings->require_otp + (int) (bool) $settings->allow_forgot_password;
            DB::table('global_variables')->where('id', $settings->id)->update([
                'require_otp' => false,
                'allow_forgot_password' => false,
            ]);
            DB::table('system_maintenance_audits')->insert([
                'action' => mb_substr('email_features_off:'.$reason, 0, 50),
                'affected_count' => $changed,
                'changed_by' => $actor ?? 'system',
                'changed_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * A real OTP or password-reset email failed. Logs a safe summary, records
     * the failure class on the SMTP account, and switches the email features
     * off (an 'auth' failure also clears the account's verification, because
     * Gmail no longer accepts its app password). Returns the failure class.
     */
    public function reportDeliveryFailure(Throwable $exception, string $context, ?string $actor = null): string
    {
        $class = SmtpFailure::classify($exception);
        Log::warning('LSPDMS email delivery failed.', SmtpFailure::logContext($exception, $class, $context));

        $settings = $this->smtp->databaseSettings();
        if ($settings !== null) {
            $changes = ['last_failure_class' => $class, 'last_failure_at' => now()];
            if ($class === SmtpFailure::AUTH) {
                $changes['verified_at'] = null;
                $changes['verified_fingerprint'] = null;
            }
            SystemSmtpSetting::query()->whereKey($settings->getKey())->update($changes);
        }

        $this->disableEmailDependentFeatures('delivery_failed_'.$class, $actor);

        return $class;
    }
}
