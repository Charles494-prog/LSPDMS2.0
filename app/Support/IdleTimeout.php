<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Idle sign-out: a signed-in session ends after config('session.idle_timeout') minutes
 * without real activity. Page loads, saves and the browser's activity heartbeat count;
 * background requests (polls, live-update reloads, socket authorization) never do.
 */
final class IdleTimeout
{
    /** Unix time of the last real activity, kept in the session. */
    public const SESSION_KEY = 'login_last_activity';

    /** Every background fetch sends this header with the value 1. */
    public const BACKGROUND_HEADER = 'X-LSPDMS-Background';

    /**
     * Routes that only ever run in the background, so they never count as activity even when
     * a caller forgets the header (or an older compiled script is still cached). The heartbeat
     * and idle sign-out routes record activity themselves.
     */
    public const BACKGROUND_ROUTES = [
        'session.status',
        'session.heartbeat',
        'session.idle-logout',
        'messages.unread-count',
        'messages.delivered',
        'broadcasting.auth',
        'assigned-tasks.unseen',
        'forms.monthly.session.status',
    ];

    /** Seconds of slack for timers and request latency when the browser reports the limit reached. */
    public const CLIENT_GRACE_SECONDS = 30;

    /** The idle limit in seconds; 0 turns idle sign-out off. */
    public static function seconds(): int
    {
        return max(0, (int) config('session.idle_timeout', 120)) * 60;
    }

    public static function enabled(): bool
    {
        return self::seconds() > 0;
    }

    public static function isBackground(Request $request): bool
    {
        return $request->headers->get(self::BACKGROUND_HEADER) === '1'
            || $request->routeIs(...self::BACKGROUND_ROUTES);
    }

    public static function lastActivity(Request $request): int
    {
        return (int) $request->session()->get(self::SESSION_KEY, 0);
    }

    /** Records real activity at $at (now by default); never moves the time backwards. */
    public static function touch(Request $request, ?int $at = null): void
    {
        $at = min($at ?? now()->timestamp, now()->timestamp);
        if ($at > self::lastActivity($request)) {
            $request->session()->put(self::SESSION_KEY, $at);
        }
    }

    /** Seconds left before the idle limit (never below 0); a session not tracked yet has all of it. */
    public static function remaining(Request $request): int
    {
        $lastActivity = self::lastActivity($request);

        return $lastActivity === 0
            ? self::seconds()
            : max(0, $lastActivity + self::seconds() - now()->timestamp);
    }

    /** "You were signed out after 2 hours of inactivity." (follows the configured limit). */
    public static function notice(): string
    {
        $minutes = intdiv(self::seconds(), 60);
        $duration = $minutes % 60 === 0
            ? (($hours = intdiv($minutes, 60)) === 1 ? '1 hour' : "{$hours} hours")
            : ($minutes === 1 ? '1 minute' : "{$minutes} minutes");

        return "You were signed out after {$duration} of inactivity.";
    }

    /**
     * Ends this browser's session only. Unlike the Logout button it does not sign the account
     * out on other devices: being idle on one PC must not interrupt work on another. This
     * browser's remember-me token is revoked too, so it cannot sign straight back in.
     */
    public static function signOut(Request $request): void
    {
        $userId = (int) ($request->session()->get('login_user')['id'] ?? 0);
        $cookie = (string) $request->cookie('remember_token');
        if ($userId > 0 && str_contains($cookie, '|')) {
            [$cookieUserId, $token] = explode('|', $cookie, 2);
            $storedToken = (int) $cookieUserId === $userId
                ? DB::table('login_user')->where('id', $userId)->value('remember_token')
                : null;
            if ($storedToken && Hash::check($token, $storedToken)) {
                DB::table('login_user')->where('id', $userId)->update([
                    'remember_token' => null,
                    'remember_token_expires_at' => null,
                ]);
            }
        }
        Cookie::queue(Cookie::forget('remember_token'));

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
