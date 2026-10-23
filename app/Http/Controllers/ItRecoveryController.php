<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureItRecoverySession;
use App\Models\ItRecoveryAccount;
use App\Models\ItRecoveryAuditLog;
use App\Services\SecurityPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Break-glass IT recovery surface. Password-only by design so it still works
 * when SMTP is down. It can only: switch off email-dependent security
 * features, reset a login_user password, re-enable a login_user account, and
 * read its own audit log. It never touches records, messages, backups,
 * imports, permissions or roles.
 */
class ItRecoveryController extends Controller
{
    private const THROTTLE_ATTEMPTS = 5;

    private const THROTTLE_SECONDS = 900;

    private const GENERIC_FAILURE = 'These credentials could not be verified.';

    public function showLogin(): View
    {
        return view('it-recovery.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = mb_strtolower(trim($credentials['email']));
        $auditEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        $throttleKey = 'it-recovery-login:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::THROTTLE_ATTEMPTS)) {
            ItRecoveryAuditLog::record('login_throttled', $auditEmail, $request);
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'email' => "Too many sign-in attempts. Please try again in {$minutes} minute(s).",
            ]);
        }

        $account = ItRecoveryAccount::query()->where('email', $email)->first();

        // Always run one bcrypt check so a missing account takes about as
        // long as a wrong password and cannot be detected by timing.
        $passwordMatches = Hash::check(
            $credentials['password'],
            $account?->password ?? $this->dummyHash()
        );

        $failure = match (true) {
            $account === null => 'unknown_account',
            ! $account->is_active => 'inactive',
            $account->isLocked() => 'locked',
            ! $passwordMatches => 'bad_password',
            default => null,
        };

        if ($failure !== null) {
            RateLimiter::hit($throttleKey, self::THROTTLE_SECONDS);
            if ($failure === 'bad_password') {
                $this->registerFailedAttempt($account, $request);
            }
            ItRecoveryAuditLog::record(
                $failure === 'locked' ? 'login_locked' : 'login_failed',
                $auditEmail,
                $request,
                ['reason' => $failure]
            );

            throw ValidationException::withMessages(['email' => self::GENERIC_FAILURE]);
        }

        RateLimiter::clear($throttleKey);
        if (Hash::needsRehash($account->password)) {
            $account->password = Hash::make($credentials['password']);
        }
        $account->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        // Start from a brand-new session so nothing from a previous visitor
        // (including any normal LSPDMS login) carries over.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(EnsureItRecoverySession::SESSION_KEY, [
            'account_id' => $account->id,
            'email' => $account->email,
            'password_changed_at' => $account->password_changed_at?->getTimestamp(),
            'logged_in_at' => now()->getTimestamp(),
            'last_activity_at' => now()->getTimestamp(),
        ]);
        ItRecoveryAuditLog::record('login_succeeded', $account->email, $request);

        return redirect()->route('it-recovery.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        ItRecoveryAuditLog::record('logout', $this->account($request)->email, $request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('it-recovery.login')->with('status', 'You have been signed out of IT recovery.');
    }

    public function dashboard(Request $request): View
    {
        $users = DB::table('login_user')
            ->select(['id', 'first_name', 'last_name', 'gmail', 'tungkulin', 'is_verified'])
            ->orderBy('id')
            ->get();

        return view('it-recovery.dashboard', [
            'account' => $this->account($request),
            'users' => $users,
        ]);
    }

    public function disableEmailFeatures(Request $request, SecurityPolicy $policy): Response
    {
        $account = $this->account($request);
        if ($denied = $this->reauthenticate($request, $account, 'disable_email_features')) {
            return $denied;
        }

        $changed = $policy->disableEmailDependentFeatures('it_recovery', 'it:'.$account->email);
        ItRecoveryAuditLog::record('action_disable_email_features', $account->email, $request, [
            'outcome' => $changed ? 'disabled' : 'already_off',
        ]);

        return redirect()->route('it-recovery.dashboard')->with('status', $changed
            ? 'Require OTP and Allow Forgot Password have been turned off.'
            : 'Require OTP and Allow Forgot Password were already off.');
    }

    public function resetPassword(Request $request): Response
    {
        $account = $this->account($request);
        $targetId = $this->targetId($request);
        if ($denied = $this->reauthenticate($request, $account, 'reset_password', $targetId)) {
            return $denied;
        }

        $target = DB::table('login_user')->where('id', $targetId)->first(['id', 'gmail', 'first_name', 'last_name']);
        if ($target === null) {
            ItRecoveryAuditLog::record('action_reset_password', $account->email, $request, [
                'target_user_id' => $targetId,
                'outcome' => 'target_not_found',
            ]);

            return redirect()->route('it-recovery.dashboard')->withErrors(['user_id' => 'That account no longer exists.']);
        }

        // Shown once on the result page; never stored in plain text, logged or audited.
        $temporaryPassword = Str::password(20, true, true, true, false);
        DB::table('login_user')->where('id', $target->id)->update([
            'password' => Hash::make($temporaryPassword),
            'auth_version' => DB::raw('auth_version + 1'),
            'auth_notice' => 'password_changed',
            'remember_token' => null,
            'remember_token_expires_at' => null,
        ]);
        ItRecoveryAuditLog::record('action_reset_password', $account->email, $request, [
            'target_user_id' => (int) $target->id,
            'outcome' => 'password_reset',
        ]);

        return response()
            ->view('it-recovery.password-reset', [
                'account' => $account,
                'target' => $target,
                'temporaryPassword' => $temporaryPassword,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function reenableAccount(Request $request): Response
    {
        $account = $this->account($request);
        $targetId = $this->targetId($request);
        if ($denied = $this->reauthenticate($request, $account, 'reenable_account', $targetId)) {
            return $denied;
        }

        $target = DB::table('login_user')->where('id', $targetId)->first(['id', 'is_verified']);
        $outcome = match (true) {
            $target === null => 'target_not_found',
            (int) $target->is_verified === 1 => 'already_enabled',
            default => 'enabled',
        };
        if ($outcome === 'enabled') {
            DB::table('login_user')->where('id', $target->id)->update(['is_verified' => 1]);
        }
        ItRecoveryAuditLog::record('action_reenable_account', $account->email, $request, [
            'target_user_id' => $targetId,
            'outcome' => $outcome,
        ]);

        if ($outcome === 'target_not_found') {
            return redirect()->route('it-recovery.dashboard')->withErrors(['user_id' => 'That account no longer exists.']);
        }

        return redirect()->route('it-recovery.dashboard')->with('status', $outcome === 'enabled'
            ? "Account #{$target->id} has been re-enabled."
            : "Account #{$target->id} was already enabled.");
    }

    public function audit(Request $request): View
    {
        $account = $this->account($request);
        $entries = ItRecoveryAuditLog::query()->orderByDesc('id')->simplePaginate(50);
        ItRecoveryAuditLog::record('audit_log_viewed', $account->email, $request, [
            'page' => $entries->currentPage(),
        ]);

        return view('it-recovery.audit', [
            'account' => $account,
            'entries' => $entries,
        ]);
    }

    private function account(Request $request): ItRecoveryAccount
    {
        $account = $request->attributes->get('it_recovery_account');
        abort_unless($account instanceof ItRecoveryAccount, 403);

        return $account;
    }

    private function targetId(Request $request): int
    {
        return (int) $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ])['user_id'];
    }

    /**
     * Every recovery action asks for the IT password again in the same form.
     * Returns a response when re-authentication failed, null when it passed.
     */
    private function reauthenticate(Request $request, ItRecoveryAccount $account, string $action, ?int $targetId = null): ?Response
    {
        $password = (string) $request->validate([
            'it_password' => ['required', 'string', 'max:255'],
        ])['it_password'];

        if (Hash::check($password, $account->password)) {
            if ($account->failed_attempts > 0) {
                $account->forceFill(['failed_attempts' => 0])->save();
            }

            return null;
        }

        $locked = $this->registerFailedAttempt($account, $request);
        ItRecoveryAuditLog::record('reauth_failed', $account->email, $request, array_filter([
            'action' => $action,
            'target_user_id' => $targetId,
        ], fn (mixed $value): bool => $value !== null));

        if ($locked) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('it-recovery.login')->withErrors(['email' => self::GENERIC_FAILURE]);
        }

        return redirect()->route('it-recovery.dashboard')
            ->withErrors(['it_password' => 'The IT recovery password is incorrect. The action was not performed.']);
    }

    /**
     * Count a wrong password against the account and lock it after too many
     * consecutive failures. Returns true when this attempt locked the account.
     */
    private function registerFailedAttempt(ItRecoveryAccount $account, Request $request): bool
    {
        $locked = DB::transaction(function () use ($account): bool {
            $fresh = ItRecoveryAccount::query()->lockForUpdate()->findOrFail($account->id);
            $attempts = $fresh->failed_attempts + 1;
            if ($attempts >= ItRecoveryAccount::MAX_FAILED_ATTEMPTS) {
                $fresh->forceFill([
                    'failed_attempts' => 0,
                    'locked_until' => now()->addMinutes(ItRecoveryAccount::LOCKOUT_MINUTES),
                ])->save();

                return true;
            }
            $fresh->forceFill(['failed_attempts' => $attempts])->save();

            return false;
        });

        if ($locked) {
            ItRecoveryAuditLog::record('account_locked', $account->email, $request, [
                'minutes' => ItRecoveryAccount::LOCKOUT_MINUTES,
            ]);
        }
        $account->refresh();

        return $locked;
    }

    private function dummyHash(): string
    {
        return Cache::rememberForever('it-recovery:dummy-hash', fn (): string => Hash::make(Str::random(40)));
    }
}
