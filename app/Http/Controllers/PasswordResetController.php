<?php

namespace App\Http\Controllers;

use App\Models\GlobalVariable;
use App\Services\SystemSmtpConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    private const RESEND_COOLDOWN_SECONDS = 300;

    public function requestForm(Request $request): View
    {
        $this->ensureEnabled();

        $isSentScreen = $request->boolean('sent')
            && is_string($request->session()->get('password_reset_flow_email'));
        if (! $isSentScreen) {
            $request->session()->forget('password_reset_flow_email');
        }
        $email = $isSentScreen ? $request->session()->get('password_reset_flow_email') : null;

        return view('password-request', [
            'isSentScreen' => $isSentScreen,
            'resendAvailableIn' => $email === null ? 0 : RateLimiter::availableIn($this->rateLimitKey($email)),
        ]);
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $request->session()->put('password_reset_flow_email', $email);
        if (RateLimiter::tooManyAttempts($this->rateLimitKey($email), 1)) {
            return redirect()->route('password.request', ['sent' => 1]);
        }

        $error = $this->sendResetLink($email);
        if ($error !== null) {
            return redirect()->route('password.request', ['sent' => 1])->withErrors(['email' => $error]);
        }
        RateLimiter::hit($this->rateLimitKey($email), self::RESEND_COOLDOWN_SECONDS);

        return redirect()->route('password.request', ['sent' => 1]);
    }

    public function resendLink(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $email = $request->session()->get('password_reset_flow_email');
        if (! is_string($email) || $email === '') {
            return redirect()->route('password.request');
        }

        if (RateLimiter::tooManyAttempts($this->rateLimitKey($email), 1)) {
            return redirect()->route('password.request', ['sent' => 1]);
        }

        $error = $this->sendResetLink($email);
        if ($error !== null) {
            return redirect()->route('password.request', ['sent' => 1])->withErrors(['email' => $error]);
        }
        RateLimiter::hit($this->rateLimitKey($email), self::RESEND_COOLDOWN_SECONDS);

        return redirect()->route('password.request', ['sent' => 1]);
    }

    public function resetForm(Request $request, string $token): View|RedirectResponse
    {
        $this->ensureEnabled();
        $email = mb_strtolower(trim((string) $request->query('email')));
        if (! $request->hasValidSignature() || ! $this->validToken($email, $token)) {
            return redirect()->route('password.reset.expired');
        }

        return view('password-reset', compact('email', 'token'));
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $reset = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $reset || ! Hash::check($validated['token'], $reset->token) || ! $this->isUnexpired($reset->created_at)) {
            return redirect()->route('password.reset.expired');
        }

        DB::transaction(function () use ($email, $validated): void {
            $user = DB::table('login_user')
                ->whereRaw('LOWER(TRIM(gmail)) = ?', [$email])
                ->where('is_verified', 1)
                ->lockForUpdate()
                ->first();
            abort_unless($user, 403, 'This password reset link is invalid or expired.');

            DB::table('login_user')->where('id', $user->id)->update([
                'password' => Hash::make($validated['password']),
                'auth_version' => DB::raw('auth_version + 1'),
                'auth_notice' => 'password_changed',
                'remember_token' => null,
                'remember_token_expires_at' => null,
            ]);
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });
        $request->session()->forget('password_reset_flow_email');

        return redirect('/login')->with('status', 'Change of Password Detected, Please Login Again.');
    }

    public function expiredLink(): View
    {
        $this->ensureEnabled();

        return view('password-link-expired');
    }

    private function sendResetLink(string $email): ?string
    {
        $user = DB::table('login_user')
            ->whereRaw('LOWER(TRIM(gmail)) = ?', [$email])
            ->where('is_verified', 1)
            ->first();

        if (! $user) {
            return null;
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );
        $url = URL::temporarySignedRoute(
            'password.reset.form',
            now()->addMinutes((int) config('auth.passwords.users.expire', 60)),
            ['token' => $token, 'email' => $email]
        );

        try {
            app(SystemSmtpConfiguration::class)->apply();
            Mail::html(
                view('emails.password-reset', [
                    'url' => $url,
                    'expiresIn' => (int) config('auth.passwords.users.expire', 60),
                ])->render(),
                fn ($message) => $message->to($user->gmail)->subject('Reset your LSPDMS password')
            );
        } catch (\Throwable $exception) {
            Log::error('LSPDMS password reset email failed.', [
                'mailer' => config('mail.default'),
                'message' => $exception->getMessage(),
            ]);
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return 'Unable to send a password reset email right now. Please try again later.';
        }

        return null;
    }

    private function rateLimitKey(string $email): string
    {
        return 'password-reset-email:'.hash('sha256', $email);
    }

    private function validToken(string $email, string $token): bool
    {
        if ($email === '') {
            return false;
        }

        $reset = DB::table('password_reset_tokens')->where('email', $email)->first();

        return $reset !== null
            && Hash::check($token, $reset->token)
            && $this->isUnexpired($reset->created_at);
    }

    private function isUnexpired(?string $createdAt): bool
    {
        return $createdAt !== null
            && now()->subMinutes((int) config('auth.passwords.users.expire', 60))->lt($createdAt);
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) (GlobalVariable::query()->first()?->allow_forgot_password ?? false), 404);
    }
}
