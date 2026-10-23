<?php

namespace App\Http\Middleware;

use App\Support\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->session()->get('login_user', []);
        if (Role::isHeadSecretary($user['tungkulin'] ?? '')) {
            return $next($request);
        }

        if (! in_array($permission, [
            'dashboard_allow',
            'form_generator_allow',
            'Delistment_allow',
            'Inquire_Allow',
            'tarheta_status_allow',
            'filter_bnh_helper_allow',
            'note_checker_allow',
        ], true) || ! ($user[$permission] ?? false)) {
            abort(403, 'Your account does not have permission to access this feature.');
        }

        return $next($request);
    }
}
