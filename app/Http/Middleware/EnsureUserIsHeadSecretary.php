<?php

namespace App\Http\Middleware;

use App\Support\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsHeadSecretary
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            Role::isHeadSecretary((string) $request->session()->get('login_user.tungkulin', '')),
            403,
            'Only the Head Secretary can access this portal.'
        );

        return $next($request);
    }
}
