<?php

namespace App\Http\Middleware;

use App\Support\IdleTimeout;
use App\Support\SessionUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsLoggedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('login_user')) {
            $sessionUser = $request->session()->get('login_user');
            $user = DB::table('login_user')->where('id', $sessionUser['id'] ?? null)->first();
            if (! $user || (int) $user->is_verified !== 1) {
                $request->session()->forget('login_user');
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                Cookie::queue(Cookie::forget('remember_token'));

                if ($this->wantsJson($request)) {
                    return $this->invalidSessionResponse('account_disabled');
                }

                return redirect('/login')->withErrors([
                    'login' => 'Your account access has been disabled. Please contact the head secretary.',
                ]);
            }

            $storedAuthVersion = (int) ($sessionUser['auth_version'] ?? 0);
            $currentAuthVersion = (int) $user->auth_version;
            if ($storedAuthVersion !== 0 && $storedAuthVersion !== $currentAuthVersion
                || $storedAuthVersion === 0 && $currentAuthVersion > 1) {
                $notice = $user->auth_notice === 'password_changed'
                    ? 'password_changed'
                    : 'signed_out_elsewhere';
                // The whole session is stale: destroy it and rotate the CSRF token.
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                Cookie::queue(Cookie::forget('remember_token'));

                if ($this->wantsJson($request)) {
                    return $this->invalidSessionResponse($notice);
                }

                $message = $notice === 'password_changed'
                    ? 'Change of Password Detected, Please Login Again.'
                    : 'You have been logged out because your account was signed out elsewhere.';

                return redirect('/login')->with('status', $message);
            }

            $freshUser = SessionUser::fromRow($user);
            $request->session()->put('login_user', $freshUser);
            view()->share('authenticatedUser', $freshUser);

            return $next($request);
        }

        if ($this->attemptRememberLogin($request)) {
            $user = $request->session()->get('login_user', []);
            view()->share('authenticatedUser', $user);

            return $next($request);
        }

        if ($this->wantsJson($request)) {
            return $this->invalidSessionResponse('logged_out');
        }

        // Not an error: this is also where Back lands after a normal logout.
        return redirect('/login')->with('status', 'Please log in to continue.');
    }

    /** Background fetches (session poll, chat, widgets) get JSON instead of a login-page redirect. */
    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || IdleTimeout::isBackground($request);
    }

    private function invalidSessionResponse(string $reason): Response
    {
        return response()->json([
            'session_invalid' => true,
            'reason' => $reason,
        ], 401);
    }

    private function attemptRememberLogin(Request $request): bool
    {
        $cookie = $request->cookie('remember_token');

        if (! $cookie || ! str_contains($cookie, '|')) {
            return false;
        }

        [$userId, $token] = explode('|', $cookie, 2);

        $user = DB::table('login_user')->where('id', $userId)->first();

        if (! $user || (int) $user->is_verified !== 1 || ! $user->remember_token || ! $user->remember_token_expires_at) {
            return false;
        }

        if (now()->timestamp > strtotime($user->remember_token_expires_at)) {
            return false;
        }

        if (! Hash::check($token, $user->remember_token)) {
            return false;
        }

        $request->session()->regenerate();
        $request->session()->put('login_user', SessionUser::fromRow($user));
        $request->session()->put(IdleTimeout::SESSION_KEY, now()->timestamp);

        return true;
    }
}
