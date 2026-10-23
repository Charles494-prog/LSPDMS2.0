<?php

namespace App\Http\Controllers;

use App\Mail\SmtpSetupVerificationMail;
use App\Models\SystemSmtpSetting;
use App\Services\SecurityPolicy;
use App\Services\SystemSmtpConfiguration;
use App\Support\SmtpFailure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class SystemSmtpSettingsController extends Controller
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    /**
     * Save a sender account and email a one-time code through it.
     *
     * While a verified account exists, the new one is kept as PENDING and the
     * verified account keeps delivering OTP and reset emails until the code is
     * confirmed. Without a verified account the new one is saved directly
     * (unverified), and the email-dependent features stay off until it is.
     */
    public function update(Request $request, SystemSmtpConfiguration $smtp, SecurityPolicy $securityPolicy): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'app_password' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $password = preg_replace('/\s+/', '', (string) ($validated['app_password'] ?? ''));
        $recipient = $this->recipient($request);

        $settings = SystemSmtpSetting::query()->first() ?? new SystemSmtpSetting;
        $hasActivePassword = filled($settings->getRawOriginal('app_password'));
        $activeVerified = $settings->exists && $hasActivePassword && $settings->isVerified();
        $sameAsActive = $settings->exists && strtolower((string) $settings->email) === $email;

        if ($password === '' && (! $hasActivePassword || ! $sameAsActive)) {
            throw ValidationException::withMessages([
                'app_password' => ! $sameAsActive && $hasActivePassword
                    ? 'Enter the Google App Password for the new Gmail address.'
                    : 'Enter the Google App Password to enable email delivery.',
            ]);
        }

        if ($sameAsActive && ($password === '' || $password === (string) $settings->app_password)
            && ($activeVerified || $password === '')) {
            return response()->json([
                'message' => 'No changes to save.',
                'email_sent' => false,
                'state' => $smtp->verificationState($recipient),
            ]);
        }

        if ($activeVerified) {
            // Keep the working account active; the new one waits for its code.
            $settings->forceFill([
                'pending_email' => $email,
                'pending_app_password' => $password !== '' ? $password : (string) $settings->app_password,
                'pending_saved_at' => now(),
            ]);
        } else {
            $settings->email = $email;
            if ($password !== '') {
                $settings->app_password = $password;
            }
            $settings->forceFill(array_merge($this->clearedVerification(), [
                'pending_email' => null,
                'pending_app_password' => null,
                'pending_saved_at' => null,
            ]));
        }
        $settings->forceFill(array_merge($this->clearedCode(), ['last_failure_class' => null, 'last_failure_at' => null]))->save();

        if (! $activeVerified) {
            $securityPolicy->enforceEmailAvailability($recipient ?: null);
        }

        if ($recipient === '') {
            return response()->json([
                'message' => 'SMTP account saved, but your account has no Gmail address to send the verification code to.',
                'email_sent' => false,
                'state' => $smtp->verificationState($recipient),
            ]);
        }

        $error = $this->issueCode($settings, $recipient, $smtp);
        $keptNote = $activeVerified
            ? ' The current verified account keeps sending emails until the new one is confirmed.'
            : '';

        return response()->json([
            'message' => $error === null
                ? "SMTP account saved. We sent a 6-digit code to {$recipient}. Enter it in the popup to confirm email delivery works.".$keptNote
                : "SMTP account saved, but it is not enabled yet. {$error}".$keptNote,
            'email_sent' => $error === null,
            'state' => $smtp->verificationState($recipient),
        ]);
    }

    /**
     * Remove the saved Gmail sender completely (one account only). OTP and
     * forgot password are switched off with it, so nobody is locked out.
     */
    public function destroy(Request $request, SystemSmtpConfiguration $smtp, SecurityPolicy $securityPolicy): JsonResponse
    {
        SystemSmtpSetting::query()->delete();
        $disabled = $securityPolicy->disableEmailDependentFeatures('smtp_removed', $this->recipient($request) ?: null);

        return response()->json([
            'message' => 'Gmail sender removed.'.($disabled
                ? ' Require OTP and Allow Forgot Password were turned off because email can no longer be sent.'
                : ''),
            'state' => $smtp->verificationState($this->recipient($request)),
        ]);
    }

    public function verificationStatus(Request $request, SystemSmtpConfiguration $smtp): JsonResponse
    {
        return response()->json(['state' => $smtp->verificationState($this->recipient($request))]);
    }

    public function sendVerification(Request $request, SystemSmtpConfiguration $smtp): JsonResponse
    {
        $settings = SystemSmtpSetting::query()->first();
        if ($settings === null || blank($settings->email)) {
            throw ValidationException::withMessages(['email' => 'Save the sender account first.']);
        }
        if ($smtp->verificationTarget($settings) === null) {
            throw ValidationException::withMessages(['code' => 'Email delivery is already verified.']);
        }

        $recipient = $this->recipient($request);
        if ($recipient === '') {
            throw ValidationException::withMessages(['email' => 'Your account has no Gmail address to send the code to.']);
        }

        $wait = $settings->verification_sent_at
            ? SystemSmtpConfiguration::RESEND_COOLDOWN_SECONDS - (now()->timestamp - $settings->verification_sent_at->timestamp)
            : 0;
        if ($wait > 0) {
            throw ValidationException::withMessages(['code' => "Please wait {$wait} seconds before requesting another code."]);
        }

        $error = $this->issueCode($settings, $recipient, $smtp);
        if ($error !== null) {
            throw ValidationException::withMessages(['email' => $error]);
        }

        return response()->json([
            'message' => "We sent a 6-digit code to {$recipient}.",
            'state' => $smtp->verificationState($recipient),
        ]);
    }

    public function verify(Request $request, SystemSmtpConfiguration $smtp): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        $outcome = DB::transaction(function () use ($validated, $smtp): array {
            // Locked so concurrent attempts cannot exceed the attempt limit.
            $settings = SystemSmtpSetting::query()->lockForUpdate()->first();
            if ($settings === null || blank($settings->email)) {
                return ['error' => 'Save the sender account first.'];
            }

            $target = $smtp->verificationTarget($settings);
            if ($target === null) {
                return ['already' => true];
            }

            if (! $smtp->hasOutstandingCode($settings)) {
                $settings->forceFill($this->clearedCode())->save();

                return ['error' => 'This code has expired. Send a new one.'];
            }

            if ((int) $settings->verification_attempts >= self::MAX_ATTEMPTS) {
                $settings->forceFill($this->clearedCode())->save();

                return ['error' => 'Too many incorrect attempts. Send a new code.'];
            }

            if (! hash_equals((string) $settings->verification_code_hash, $this->hashCode($validated['code']))) {
                $attempts = (int) $settings->verification_attempts + 1;
                $left = self::MAX_ATTEMPTS - $attempts;
                $settings->forceFill($left > 0 ? ['verification_attempts' => $attempts] : $this->clearedCode())->save();

                return ['error' => $left > 0
                    ? "Incorrect code. {$left} attempt".($left === 1 ? '' : 's').' left.'
                    : 'Too many incorrect attempts. Send a new code.'];
            }

            if ($target['pending']) {
                // Promote the confirmed replacement to the active account.
                $settings->email = $target['email'];
                $settings->app_password = $target['password'];
            }
            $settings->forceFill(array_merge($this->clearedCode(), [
                'pending_email' => null,
                'pending_app_password' => null,
                'pending_saved_at' => null,
                'last_failure_class' => null,
                'last_failure_at' => null,
                'verified_at' => now(),
                'verified_fingerprint' => SystemSmtpSetting::fingerprintFor($target['email'], $target['password']),
            ]))->save();

            return ['verified' => true];
        });

        if (isset($outcome['error'])) {
            throw ValidationException::withMessages(['code' => $outcome['error']]);
        }

        return response()->json([
            'message' => isset($outcome['already'])
                ? 'Email delivery is already verified.'
                : 'Email delivery verified. SMTP is now enabled.',
            'state' => $smtp->verificationState($this->recipient($request)),
        ]);
    }

    /**
     * Email a fresh code through the account being verified (the pending
     * replacement, or the unverified active account). Returns null on success
     * or a safe user-facing error; nothing is stored unless the email was sent.
     */
    private function issueCode(SystemSmtpSetting $settings, string $recipient, SystemSmtpConfiguration $smtp): ?string
    {
        $target = $smtp->verificationTarget($settings);
        if ($target === null) {
            return 'Email delivery is already verified.';
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = self::CODE_TTL_MINUTES;

        try {
            $smtp->applyCredentials($target['email'], $target['password']);
            Mail::mailer('smtp')->to($recipient)->send(new SmtpSetupVerificationMail($code, $minutes, $target['email']));
        } catch (Throwable $exception) {
            $class = SmtpFailure::classify($exception);
            Log::warning('SMTP verification email failed.', SmtpFailure::logContext($exception, $class, 'smtp_setup'));
            $settings->forceFill(['last_failure_class' => $class, 'last_failure_at' => now()])->save();

            return SmtpFailure::message($class);
        } finally {
            // Restore the verified account (if any) for the rest of this request.
            $smtp->apply();
        }

        $settings->forceFill([
            'verification_code_hash' => $this->hashCode($code),
            'verification_expires_at' => now()->addMinutes($minutes),
            'verification_attempts' => 0,
            'verification_sent_at' => now(),
            'verification_sent_to' => $recipient,
            'verification_target' => $target['fingerprint'],
            'last_failure_class' => null,
            'last_failure_at' => null,
        ])->save();

        return null;
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function recipient(Request $request): string
    {
        return trim((string) $request->session()->get('login_user.gmail', ''));
    }

    private function clearedCode(): array
    {
        return [
            'verification_code_hash' => null,
            'verification_expires_at' => null,
            'verification_attempts' => 0,
            'verification_sent_at' => null,
            'verification_sent_to' => null,
            'verification_target' => null,
        ];
    }

    private function clearedVerification(): array
    {
        return array_merge($this->clearedCode(), [
            'verified_at' => null,
            'verified_fingerprint' => null,
        ]);
    }
}
