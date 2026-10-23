<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Signed-in pages and JSON must not be kept by the browser: after logout the
 * back button (or a restored tab) would otherwise show private data again.
 * File downloads and responses that set their own caching are left alone.
 */
class PreventSensitiveCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
            || str_starts_with(strtolower((string) $response->headers->get('Content-Disposition', '')), 'attachment')
            || $this->hasOwnCachePolicy($response)) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function hasOwnCachePolicy(Response $response): bool
    {
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control', ''));

        // Symfony's default for a fresh response is "no-cache, private".
        return $cacheControl !== '' && $cacheControl !== 'no-cache, private';
    }
}
