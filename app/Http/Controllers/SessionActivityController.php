<?php

namespace App\Http\Controllers;

use App\Support\IdleTimeout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The browser side of idle sign-out (resources/js/idle-session.js): it reports real
 * activity, asks to be signed out when its countdown ends, and shows the notice.
 */
class SessionActivityController extends Controller
{
    /** Activity heartbeat: the browser says how many seconds ago the user last did something. */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idle_seconds' => ['required', 'integer', 'min:0'],
        ]);
        $idleSeconds = min((int) $validated['idle_seconds'], IdleTimeout::seconds());
        IdleTimeout::touch($request, now()->timestamp - $idleSeconds);

        return response()->json(['remaining' => IdleTimeout::remaining($request)]);
    }

    /** The countdown ended in the browser; the server's own clock decides. */
    public function idleLogout(Request $request): JsonResponse
    {
        $remaining = IdleTimeout::remaining($request);
        if (IdleTimeout::enabled() && $remaining <= IdleTimeout::CLIENT_GRACE_SECONDS) {
            IdleTimeout::signOut($request);

            return response()->json(['signed_out' => true, 'message' => IdleTimeout::notice()]);
        }

        // Something this browser did not see kept the session active: carry on.
        return response()->json(['signed_out' => false, 'remaining' => $remaining]);
    }

    /** Where every open tab goes after an idle sign-out: the sign-in page with the notice. */
    public function signedOut(Request $request): RedirectResponse
    {
        if ($request->session()->has('login_user')) {
            if (! IdleTimeout::enabled() || IdleTimeout::remaining($request) > IdleTimeout::CLIENT_GRACE_SECONDS) {
                return redirect('/home');
            }
            IdleTimeout::signOut($request);
        }

        return redirect('/login?idle=1');
    }
}
