<?php

namespace App\Http\Controllers;

use App\Mail\LoginVerificationCodeMail;
use App\Models\GlobalVariable;
use App\Services\SecurityPolicy;
use App\Services\SystemSmtpConfiguration;
use App\Support\PasswordVerifier;
use App\Support\SessionUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /** Seconds before another OTP email can be requested for the same login. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_CODE_ATTEMPTS = 5;

    public const MAX_CODE_RESENDS = 5;

    private const CODE_LIFETIME_MINUTES = 5;

    /** Attempts per minute for one email address from one IP address. */
    private const ACCOUNT_ATTEMPTS_PER_MINUTE = 5;

    /** Attempts per minute from one IP address, all accounts together (shared office NAT). */
    private const IP_ATTEMPTS_PER_MINUTE = 30;

    /** Consecutive failures => lockout seconds, highest threshold first. */
    private const PROGRESSIVE_LOCKOUTS = [15 => 900, 10 => 300, 5 => 60];

    public function showLogin()
    {
        $globalVariables = GlobalVariable::query()->first();

        return view('welcome', [
            'allowForgotPassword' => (bool) ($globalVariables?->allow_forgot_password ?? false),
        ]);
    }

    public function login(Request $request, SecurityPolicy $securityPolicy)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return $this->failedLogin($request, 'Invalid username or password.');
        }
        $credentials = $validator->validated();

        $email = strtolower(trim($credentials['email']));
        if (($wait = $this->loginLockoutSeconds($request, $email)) > 0) {
            return $this->failedLogin($request, "Too many sign-in attempts. Please wait {$wait} seconds and try again.");
        }
        $this->hitLoginLimiters($request, $email);

        $user = DB::table('login_user')
            ->whereRaw('LOWER(TRIM(gmail)) = ?', [$email])
            ->first();

        if (! $user || ! PasswordVerifier::checkForLogin($credentials['password'], $user)) {
            $this->recordLoginFailure($request, $email);

            return $this->failedLogin($request, 'Invalid username or password.');
        }

        if ((int) $user->is_verified !== 1) {
            return $this->failedLogin($request, 'Account has not been granted access by the head secretary.');
        }

        $this->clearLoginFailures($request, $email);

        $globalVariables = GlobalVariable::query()->first();
        if (! (bool) ($globalVariables?->require_otp ?? false)) {
            return $this->completeLogin($request, (int) $user->id, $request->boolean('remember'));
        }

        // OTP is on but email can no longer be delivered: switch the email
        // features off and let the correct password through, so nobody is locked out.
        if ($securityPolicy->enforceEmailAvailability('system')) {
            return $this->completeLogin($request, (int) $user->id, $request->boolean('remember'), true);
        }

        try {
            $code = $this->sendVerificationCode($user->gmail);
        } catch (\Throwable $exception) {
            $securityPolicy->reportDeliveryFailure($exception, 'login_otp');

            return $this->completeLogin($request, (int) $user->id, $request->boolean('remember'), true);
        }

        $request->session()->put('pending_login_user', [
            'id' => $user->id,
            'gmail' => $user->gmail,
            'tungkulin' => $user->tungkulin,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES)->timestamp,
            'sent_at' => now()->timestamp,
            'attempts' => 0,
            'resends' => 0,
            'remember' => $request->boolean('remember'),
        ]);

        return redirect('/verify-code')->with('status', 'A verification code was sent to your Gmail.');
    }

    public function showVerificationForm(Request $request)
    {
        if (! $request->session()->has('pending_login_user')) {
            return $request->session()->has('login_user')
                ? redirect('/home')
                : redirect('/login');
        }

        $pendingUser = $request->session()->get('pending_login_user');

        return view('verify-code', [
            'expiresAt' => $pendingUser['expires_at'],
            'resendAvailableAt' => ($pendingUser['sent_at'] ?? 0) + self::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public function verifyCode(Request $request)
    {
        $pendingUser = $request->session()->get('pending_login_user');

        if (! $pendingUser) {
            return redirect('/login')->withErrors(['login' => 'Please log in again.']);
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if ($pendingUser['expires_at'] < now()->timestamp) {
            $request->session()->forget('pending_login_user');

            return redirect('/login?expired=1');
        }

        if (! Hash::check($request->code, $pendingUser['code'])) {
            $pendingUser['attempts'] = (int) ($pendingUser['attempts'] ?? 0) + 1;
            if ($pendingUser['attempts'] >= self::MAX_CODE_ATTEMPTS) {
                $request->session()->forget('pending_login_user');

                return redirect('/login')->withErrors([
                    'login' => 'Too many incorrect verification codes. Please sign in again.',
                ]);
            }
            $request->session()->put('pending_login_user', $pendingUser);
            $remaining = self::MAX_CODE_ATTEMPTS - $pendingUser['attempts'];

            return back()->withErrors([
                'code' => "Invalid or expired verification code. {$remaining} attempt(s) left.",
            ]);
        }

        $freshUser = DB::table('login_user')->where('id', $pendingUser['id'])->first();
        if (! $freshUser || (int) $freshUser->is_verified !== 1) {
            $request->session()->forget('pending_login_user');

            return redirect('/login')->withErrors(['login' => 'Your account access has been disabled. Please contact the head secretary.']);
        }

        return $this->completeLogin($request, (int) $freshUser->id, ! empty($pendingUser['remember']));
    }

    private function completeLogin(Request $request, int $userId, bool $remember, bool $passwordOnlyFallback = false)
    {
        $request->session()->regenerate();
        $freshUser = DB::table('login_user')->where('id', $userId)->first();
        if (! $freshUser || (int) $freshUser->is_verified !== 1) {
            $request->session()->forget('pending_login_user');

            return redirect('/login')->withErrors(['login' => 'Your account access has been disabled. Please contact the head secretary.']);
        }

        $request->session()->put('login_user', SessionUser::fromRow($freshUser));

        $request->session()->forget('pending_login_user');

        if ($remember) {
            $this->rememberUser($userId);
        } else {
            Cookie::queue(Cookie::forget('remember_token'));
        }

        return redirect('/home')->with('status', $passwordOnlyFallback
            ? 'Email verification is temporarily unavailable, so you were signed in with your password only.'
            : 'Login successful.');
    }

    private function rememberUser(int $userId): void
    {
        $token = Str::random(64);

        DB::table('login_user')->where('id', $userId)->update([
            'remember_token' => Hash::make($token),
            'remember_token_expires_at' => now()->addDays(30),
        ]);

        Cookie::queue(Cookie::make(
            'remember_token',
            $userId.'|'.$token,
            60 * 24 * 30,
            null,
            null,
            config('session.secure'),
            true,
            false,
            'Lax'
        ));
    }

    public function resendCode(Request $request, SecurityPolicy $securityPolicy)
    {
        $pendingUser = $request->session()->get('pending_login_user');

        if (! $pendingUser) {
            return redirect('/login')->withErrors(['login' => 'Please log in again.']);
        }

        if (($pendingUser['sent_at'] ?? 0) + self::RESEND_COOLDOWN_SECONDS > now()->timestamp) {
            return back()->withErrors(['code' => 'Please wait before requesting another code.']);
        }

        if ((int) ($pendingUser['resends'] ?? 0) >= self::MAX_CODE_RESENDS) {
            $request->session()->forget('pending_login_user');

            return redirect('/login')->withErrors(['login' => 'Too many verification codes were requested. Please sign in again.']);
        }

        $freshUser = DB::table('login_user')->where('id', $pendingUser['id'])->first();
        if (! $freshUser || (int) $freshUser->is_verified !== 1) {
            $request->session()->forget('pending_login_user');

            return redirect('/login')->withErrors(['login' => 'Your account access has been disabled. Please contact the head secretary.']);
        }

        $globalVariables = GlobalVariable::query()->first();
        if (! (bool) ($globalVariables?->require_otp ?? false)) {
            // OTP was switched off after the code was sent: the password was already checked.
            return $this->completeLogin($request, (int) $freshUser->id, ! empty($pendingUser['remember']), true);
        }

        try {
            $code = $this->sendVerificationCode($pendingUser['gmail']);
        } catch (\Throwable $exception) {
            $securityPolicy->reportDeliveryFailure($exception, 'login_otp_resend');

            return $this->completeLogin($request, (int) $freshUser->id, ! empty($pendingUser['remember']), true);
        }

        $pendingUser['code'] = Hash::make($code);
        $pendingUser['expires_at'] = now()->addMinutes(self::CODE_LIFETIME_MINUTES)->timestamp;
        $pendingUser['sent_at'] = now()->timestamp;
        $pendingUser['resends'] = (int) ($pendingUser['resends'] ?? 0) + 1;
        $request->session()->put('pending_login_user', $pendingUser);

        return back()->with('status', 'A new verification code was sent.');
    }

    private function sendVerificationCode(string $email): string
    {
        $code = (string) random_int(100000, 999999);

        app(SystemSmtpConfiguration::class)->apply();
        Mail::to($email)->send(new LoginVerificationCodeMail($code));

        return $code;
    }

    public function logout(Request $request)
    {
        $user = $request->session()->get('login_user');

        if ($user) {
            DB::table('login_user')->where('id', $user['id'])->update([
                'auth_version' => DB::raw('auth_version + 1'),
                'auth_notice' => 'signed_out_elsewhere',
                'remember_token' => null,
                'remember_token_expires_at' => null,
            ]);
        }

        // Destroy the old session (file included) and rotate the CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Cookie::queue(Cookie::forget('remember_token'));

        return redirect('/login')->with('status', 'You have been logged out.');
    }

    private function failedLogin(Request $request, string $message)
    {
        return redirect()
            ->route('login.form')
            ->withInput($request->only('email'))
            ->withErrors(['login' => $message]);
    }

    private function accountKey(Request $request, string $email): string
    {
        return 'login:account:'.sha1($email.'|'.$request->ip());
    }

    private function failureKey(Request $request, string $email): string
    {
        return 'login:failures:'.sha1($email.'|'.$request->ip());
    }

    /** Seconds the caller must still wait, 0 when a new attempt is allowed. */
    private function loginLockoutSeconds(Request $request, string $email): int
    {
        $ipKey = 'login:ip:'.$request->ip();
        $accountKey = $this->accountKey($request, $email);
        $lockedUntil = (int) Cache::get($this->failureKey($request, $email).':until', 0);

        return max(
            RateLimiter::tooManyAttempts($ipKey, self::IP_ATTEMPTS_PER_MINUTE) ? RateLimiter::availableIn($ipKey) : 0,
            RateLimiter::tooManyAttempts($accountKey, self::ACCOUNT_ATTEMPTS_PER_MINUTE) ? RateLimiter::availableIn($accountKey) : 0,
            $lockedUntil - now()->timestamp,
            0
        );
    }

    private function hitLoginLimiters(Request $request, string $email): void
    {
        RateLimiter::hit('login:ip:'.$request->ip(), 60);
        RateLimiter::hit($this->accountKey($request, $email), 60);
    }

    /** Consecutive failures lock this email + IP for longer and longer. */
    private function recordLoginFailure(Request $request, string $email): void
    {
        $key = $this->failureKey($request, $email);
        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, now()->addHour());

        foreach (self::PROGRESSIVE_LOCKOUTS as $threshold => $seconds) {
            if ($failures >= $threshold) {
                if ($failures % 5 === 0) {
                    Cache::put($key.':until', now()->timestamp + $seconds, now()->addSeconds($seconds));
                }
                break;
            }
        }
    }

    private function clearLoginFailures(Request $request, string $email): void
    {
        Cache::forget($this->failureKey($request, $email));
        Cache::forget($this->failureKey($request, $email).':until');
        RateLimiter::clear($this->accountKey($request, $email));
    }
}
