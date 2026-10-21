<?php

namespace App\Http\Middleware;

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
                $request->session()->forget('login_user');
                $request->session()->forget('pending_login_user');
                Cookie::queue(Cookie::forget('remember_token'));

                if ($request->routeIs('session.status') || $request->expectsJson()) {
                    return response()->json([
                        'session_invalid' => true,
                        'reason' => $notice,
                    ], 401);
                }

                $message = $notice === 'password_changed'
                    ? 'Change of Password Detected, Please Login Again.'
                    : 'You have been logged out because your account was signed out elsewhere.';

                return redirect('/login')->with('status', $message);
            }

            $freshUser = [
                'id' => $user->id,
                'auth_version' => $currentAuthVersion,
                'gmail' => $user->gmail,
                'tungkulin' => $user->tungkulin,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'gender' => $user->gender,
                'profile_picture' => $user->profile_picture,
                'dark_mode' => (bool) $user->dark_mode,
                'dashboard_allow' => (bool) $user->dashboard_allow,
                'form_generator_allow' => (bool) $user->form_generator_allow,
                'Delistment_allow' => (bool) $user->Delistment_allow,
                'Inquire_Allow' => (bool) $user->Inquire_Allow,
                'tarheta_status_allow' => (bool) $user->tarheta_status_allow,
                'filter_bnh_helper_allow' => (bool) $user->filter_bnh_helper_allow,
                'chatbot_records_allow' => (bool) ($user->chatbot_records_allow ?? false),
                'is_verified' => (bool) $user->is_verified,
            ];
            $request->session()->put('login_user', $freshUser);
            view()->share('authenticatedUser', $freshUser);

            return $next($request);
        }

        if ($this->attemptRememberLogin($request)) {
            $user = $request->session()->get('login_user', []);
            view()->share('authenticatedUser', $user);

            return $next($request);
        }

        return redirect('/login')->withErrors(['login' => 'Please log in to continue.']);
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
        $request->session()->put('login_user', [
            'id' => $user->id,
            'auth_version' => (int) $user->auth_version,
            'gmail' => $user->gmail,
            'tungkulin' => $user->tungkulin,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'gender' => $user->gender,
            'profile_picture' => $user->profile_picture,
            'dark_mode' => (bool) $user->dark_mode,
            'dashboard_allow' => (bool) $user->dashboard_allow,
            'form_generator_allow' => (bool) $user->form_generator_allow,
            'Delistment_allow' => (bool) $user->Delistment_allow,
            'Inquire_Allow' => (bool) $user->Inquire_Allow,
            'tarheta_status_allow' => (bool) $user->tarheta_status_allow,
            'filter_bnh_helper_allow' => (bool) $user->filter_bnh_helper_allow,
            'chatbot_records_allow' => (bool) ($user->chatbot_records_allow ?? false),
            'is_verified' => (bool) $user->is_verified,
        ]);

        return true;
    }
}
