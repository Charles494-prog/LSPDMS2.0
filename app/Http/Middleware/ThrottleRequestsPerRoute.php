<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Laravel's numeric "throttle:N,M" keys its counter by the authenticated user, or else by IP only.
 * LSPDMS signs people in with its own session (login_user), so Laravel never sees a user and every
 * throttled route shared ONE counter per IP: background polling (tasks, read receipts) used up the
 * LSPBot limit, and everyone on the same PC (127.0.0.1) shared it too.
 *
 * This keys each limit by the route and the signed-in LSPDMS user (or the IP before sign-in),
 * so "throttle:30,1" on a route means 30 per minute for that person on that route.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        if (! $route) {
            return parent::resolveRequestSignature($request);
        }

        $userId = $request->hasSession() ? $request->session()->get('login_user.id') : null;
        $who = $userId ? 'user:'.$userId : 'ip:'.$request->ip();
        $routeKey = $route->getName() ?: implode(',', $route->methods()).' '.$route->uri();

        return sha1($route->getDomain().'|'.$routeKey.'|'.$who);
    }
}
