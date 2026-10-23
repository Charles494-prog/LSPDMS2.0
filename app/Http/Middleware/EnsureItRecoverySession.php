<?php

namespace App\Http\Middleware;

use App\Models\ItRecoveryAccount;
use App\Models\ItRecoveryAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the IT recovery surface. The IT session lives under its own session
 * key ('it_recovery') and is never combined with a normal LSPDMS login: a
 * browser holding a 'login_user' session is refused outright, so the Head
 * Secretary (or anyone signed in to the app) cannot reach these pages.
 *
 * Use as 'it-recovery.session' for signed-in pages and
 * 'it-recovery.session:guest' for the login form.
 */
class EnsureItRecoverySession
{
    public const SESSION_KEY = 'it_recovery';

    public const IDLE_MINUTES = 15;

    public const ABSOLUTE_MINUTES = 120;

    public function handle(Request $request, Closure $next, string $mode = 'auth'): Response
    {
        $allowedIps = (array) config('it_recovery.allowed_ips', []);
        abort_if($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true), 404);

        // Rendered with the standalone IT layout rather than the app's 403
        // page, which depends on a fully loaded LSPDMS session.
        if ($request->session()->has('login_user')) {
            return $this->secureHeaders(response()->view('it-recovery.denied', [], 403));
        }

        if ($mode === 'guest') {
            if ($this->activeAccount($request) !== null) {
                return redirect()->route('it-recovery.dashboard');
            }

            return $this->secureHeaders($next($request));
        }

        $session = $request->session()->get(self::SESSION_KEY);
        if (! is_array($session)) {
            return redirect()->route('it-recovery.login');
        }

        $now = now()->getTimestamp();
        $email = (string) ($session['email'] ?? '');
        $timeout = null;
        if ($now - (int) ($session['logged_in_at'] ?? 0) > self::ABSOLUTE_MINUTES * 60) {
            $timeout = 'absolute';
        } elseif ($now - (int) ($session['last_activity_at'] ?? 0) > self::IDLE_MINUTES * 60) {
            $timeout = 'idle';
        }

        if ($timeout !== null) {
            ItRecoveryAuditLog::record('session_timeout', $email, $request, ['reason' => $timeout]);

            return $this->endSession($request, 'Your IT recovery session expired. Please sign in again.');
        }

        $account = $this->activeAccount($request);
        if ($account === null) {
            ItRecoveryAuditLog::record('session_revoked', $email, $request, ['reason' => 'account_unavailable']);

            return $this->endSession($request, 'Your IT recovery session is no longer valid. Please sign in again.');
        }

        $session['last_activity_at'] = $now;
        $request->session()->put(self::SESSION_KEY, $session);
        $request->attributes->set('it_recovery_account', $account);

        return $this->secureHeaders($next($request));
    }

    /**
     * The account behind the current IT session, or null when it was removed,
     * deactivated, locked, or its password was rotated after this sign-in.
     */
    private function activeAccount(Request $request): ?ItRecoveryAccount
    {
        $session = $request->session()->get(self::SESSION_KEY);
        if (! is_array($session) || ! isset($session['account_id'])) {
            return null;
        }

        $account = ItRecoveryAccount::query()->find((int) $session['account_id']);
        if ($account === null || ! $account->is_active || $account->isLocked()) {
            return null;
        }

        $changedAt = $account->password_changed_at?->getTimestamp();
        if ($changedAt !== ($session['password_changed_at'] ?? null)) {
            return null;
        }

        return $account;
    }

    private function endSession(Request $request, string $message): Response
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('it-recovery.login')->with('status', $message);
    }

    private function secureHeaders(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
