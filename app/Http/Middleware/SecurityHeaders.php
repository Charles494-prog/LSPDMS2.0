<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening for every web response. Other sites may not frame these
 * pages (clickjacking), browsers may not guess content types, and the PHP
 * version banner is dropped. The CSP holds only directives that need no
 * per-host list, so the Vite dev server, Google Fonts and the Reverb websocket
 * keep working. A header a route already set (IT recovery's DENY and
 * no-referrer) is kept.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; object-src 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }
        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
