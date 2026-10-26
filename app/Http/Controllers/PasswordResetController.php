<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetLinkMail;
use App\Models\GlobalVariable;
use App\Services\SecurityPolicy;
use App\Services\SystemSmtpConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        // Same response for every email (known, unknown, or a failed send), so
        // the page never reveals which addresses have accounts.
        RateLimiter::hit($this->rateLimitKey($email), self::RESEND_COOLDOWN_SECONDS);
        $this->sendResetLink($request, $email);

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

        RateLimiter::hit($this->rateLimitKey($email), self::RESEND_COOLDOWN_SECONDS);
        $this->sendResetLink($request, $email);

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

    /**
     * Email a reset link when $email belongs to an approved account. Failures
     * are logged and switch the email features off; the caller shows the same
     * page either way.
     */
    private function sendResetLink(Request $request, string $email): void
    {
        $user = DB::table('login_user')
            ->whereRaw('LOWER(TRIM(gmail)) = ?', [$email])
            ->where('is_verified', 1)
            ->first();

        if (! $user) {
            return;
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );
        URL::forceRootUrl($this->trustedRoot($request));
        try {
            $url = URL::temporarySignedRoute(
                'password.reset.form',
                now()->addMinutes((int) config('auth.passwords.users.expire', 60)),
                ['token' => $token, 'email' => $email]
            );
        } finally {
            URL::forceRootUrl(null);
        }

        try {
            app(SystemSmtpConfiguration::class)->apply();
            Mail::to($user->gmail)->send(new PasswordResetLinkMail($url, (int) config('auth.passwords.users.expire', 60)));
        } catch (\Throwable $exception) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            app(SecurityPolicy::class)->reportDeliveryFailure($exception, 'password_reset');
        }
    }

    /**
     * Scheme and host for the emailed link. The Host header comes from the
     * client, so a forged one would otherwise mail a genuine reset link that
     * opens someone else's site. It is kept only when it names this server
     * (APP_URL's host, loopback, or this PC's own name or addresses, so LAN
     * users get a link that opens for them) on the port the server listens on;
     * anything else falls back to APP_URL.
     */
    private function trustedRoot(Request $request): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $host = trim(strtolower($request->getHost()), '[]');
        $machine = strtolower((string) gethostname());
        $trustedHosts = array_merge(
            [trim(strtolower((string) parse_url($appUrl, PHP_URL_HOST)), '[]'), 'localhost', '127.0.0.1', '::1', $machine],
            $machine === '' ? [] : (gethostbynamel($machine) ?: []),
        );
        $samePort = (string) $request->getPort() === (string) $request->server('SERVER_PORT');

        return $host !== '' && $samePort && in_array($host, array_filter($trustedHosts), true)
            ? $request->getSchemeAndHttpHost()
            : $appUrl;
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
        $enabled = (bool) (GlobalVariable::query()->first()?->allow_forgot_password ?? false);
        // Without a verified sender the feature is switched off instead of failing silently.
        if ($enabled && app(SecurityPolicy::class)->enforceEmailAvailability()) {
            $enabled = false;
        }
        abort_unless($enabled, 404);
    }
}
