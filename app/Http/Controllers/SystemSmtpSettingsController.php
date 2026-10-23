<?php

namespace App\Http\Controllers;

use App\Models\SystemSmtpSetting;
use App\Services\SystemSmtpConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;

class SystemSmtpSettingsController extends Controller
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    /**
     * Save the sender account. It is NOT finalized: verification is reset and a
     * code is emailed to the Head Secretary through the new account. Email
     * delivery only counts as configured after the code is confirmed.
     * Only one account exists: saving replaces any previous one.
     */
    public function update(Request $request, SystemSmtpConfiguration $smtp): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'app_password' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $password = preg_replace('/\s+/', '', (string) ($validated['app_password'] ?? ''));

        $settings = SystemSmtpSetting::query()->first() ?? new SystemSmtpSetting;
        $hasPassword = filled($settings->getRawOriginal('app_password'));
        $emailChanged = ! $settings->exists || strtolower((string) $settings->email) !== $email;

        if ($password === '' && (! $hasPassword || $emailChanged)) {
            throw ValidationException::withMessages([
                'app_password' => $emailChanged && $hasPassword
                    ? 'Enter the Google App Password for the new Gmail address.'
                    : 'Enter the Google App Password to enable email delivery.',
            ]);
        }

        $recipient = $this->recipient($request);

        if (! $emailChanged && $password === '') {
            return response()->json([
                'message' => 'No changes to save.',
                'email_sent' => true,
                'state' => $smtp->verificationState($recipient),
            ]);
        }

        $settings->email = $email;
        if ($password !== '') {
            $settings->app_password = $password;
        }
        $settings->forceFill($this->clearedVerification())->save();

        if ($recipient === '') {
            return response()->json([
                'message' => 'SMTP account saved, but your account has no Gmail address to send the verification code to.',
                'email_sent' => false,
                'state' => $smtp->verificationState($recipient),
            ]);
        }

        $error = $this->issueCode($settings, $recipient, $smtp);

        return response()->json([
            'message' => $error === null
                ? "SMTP account saved. We sent a 6-digit code to {$recipient}. Enter it in the popup to confirm email delivery works."
                : "SMTP account saved, but it is not enabled yet. {$error}",
            'email_sent' => $error === null,
            'state' => $smtp->verificationState($recipient),
        ]);
    }

    /** Remove the saved Gmail sender completely (one account only). */
    public function destroy(Request $request, SystemSmtpConfiguration $smtp): JsonResponse
    {
        SystemSmtpSetting::query()->delete();

        return response()->json([
            'message' => 'Gmail sender removed.',
            'state' => $smtp->verificationState($this->recipient($request)),
        ]);
    }

    public function verificationStatus(Request $request, SystemSmtpConfiguration $smtp): JsonResponse
    {
        return response()->json(['state' => $smtp->verificationState($this->recipient($request))]);
    }

    public function sendVerification(Request $request, SystemSmtpConfiguration $smtp): JsonResponse
    {
        $settings = $smtp->databaseSettings();
        if ($settings === null) {
            throw ValidationException::withMessages(['email' => 'Save the sender account first.']);
        }
        if ($settings->isVerified()) {
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

        $settings = $smtp->databaseSettings();
        if ($settings === null) {
            throw ValidationException::withMessages(['code' => 'Save the sender account first.']);
        }
        if ($settings->isVerified()) {
            return response()->json([
                'message' => 'Email delivery is already verified.',
                'state' => $smtp->verificationState($this->recipient($request)),
            ]);
        }

        if (blank($settings->verification_code_hash)
            || $settings->verification_expires_at === null
            || $settings->verification_expires_at->isPast()) {
            $settings->forceFill($this->clearedCode())->save();
            throw ValidationException::withMessages(['code' => 'This code has expired. Send a new one.']);
        }

        if ((int) $settings->verification_attempts >= self::MAX_ATTEMPTS) {
            $settings->forceFill($this->clearedCode())->save();
            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Send a new code.']);
        }

        if (! hash_equals((string) $settings->verification_code_hash, $this->hashCode($validated['code']))) {
            $attempts = (int) $settings->verification_attempts + 1;
            $settings->forceFill(['verification_attempts' => $attempts])->save();
            $left = self::MAX_ATTEMPTS - $attempts;
            if ($left <= 0) {
                $settings->forceFill($this->clearedCode())->save();
            }
            throw ValidationException::withMessages([
                'code' => $left > 0
                    ? "Incorrect code. {$left} attempt".($left === 1 ? '' : 's').' left.'
                    : 'Too many incorrect attempts. Send a new code.',
            ]);
        }

        $settings->forceFill(array_merge($this->clearedCode(), [
            'verified_at' => now(),
            'verified_fingerprint' => $settings->fingerprint(),
        ]))->save();

        return response()->json([
            'message' => 'Email delivery verified. SMTP is now enabled.',
            'state' => $smtp->verificationState($this->recipient($request)),
        ]);
    }

    /**
     * Email a fresh code through the saved (possibly unverified) account.
     * Returns null on success or a user-facing error message on failure;
     * nothing is stored unless the email was actually sent.
     */
    private function issueCode(SystemSmtpSetting $settings, string $recipient, SystemSmtpConfiguration $smtp): ?string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = self::CODE_TTL_MINUTES;

        try {
            $smtp->apply(true);
            Mail::mailer('smtp')->send(
                [
                    'html' => 'emails.smtp-setup-verification',
                    'text' => new HtmlString(
                        "Your LSPDMS mailing setup verification code is {$code}.\n\n"
                        ."It expires in {$minutes} minutes. If you did not request this, you can ignore this message."
                    ),
                ],
                [
                    'code' => $code,
                    'minutes' => $minutes,
                    'senderEmail' => $settings->email,
                ],
                fn ($message) => $message->to($recipient)->subject('LSPDMS mailing setup verification code')
            );
        } catch (Throwable $exception) {
            Log::warning('SMTP verification email failed: '.$exception->getMessage());

            return 'The test email could not be sent. Check the Gmail address and Google App Password, then try again.';
        }

        $settings->forceFill([
            'verification_code_hash' => $this->hashCode($code),
            'verification_expires_at' => now()->addMinutes($minutes),
            'verification_attempts' => 0,
            'verification_sent_at' => now(),
            'verification_sent_to' => $recipient,
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