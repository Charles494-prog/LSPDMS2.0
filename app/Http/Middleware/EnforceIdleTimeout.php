<?php

namespace App\Http\Middleware;

use App\Support\IdleTimeout;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a session out after the idle limit (config session.idle_timeout) and otherwise
 * records real activity. Runs after login.session on every signed-in route.
 */
class EnforceIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! IdleTimeout::enabled() || ! $request->session()->has('login_user')) {
            return $next($request);
        }

        $now = now()->timestamp;
        $lastActivity = IdleTimeout::lastActivity($request);
        if ($lastActivity === 0) {
            // A session signed in before idle sign-out existed starts counting now.
            IdleTimeout::touch($request);
            $lastActivity = $now;
        }

        // Logout still runs in full (it also signs the account out on other devices).
        if ($now - $lastActivity >= IdleTimeout::seconds() && ! $request->routeIs('logout')) {
            IdleTimeout::signOut($request);

            if ($request->expectsJson() || IdleTimeout::isBackground($request)) {
                return response()->json([
                    'session_invalid' => true,
                    'reason' => 'idle',
                    'message' => IdleTimeout::notice(),
                ], 401);
            }

            return redirect('/login?idle=1');
        }

        if (! IdleTimeout::isBackground($request)) {
            IdleTimeout::touch($request);
        }

        return $next($request);
    }
}
