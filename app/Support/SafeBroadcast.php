<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Sends a live update over Reverb without letting a Reverb problem break or stall the request.
 * Broadcasts run inside the request, so after one failure further broadcasts are skipped for
 * PAUSE_SECONDS: a stopped or hung Reverb then costs one short wait and one log entry per
 * pause instead of one per chat message or typing ping. Pages still load fresh data on open.
 */
final class SafeBroadcast
{
    public const PAUSE_KEY = 'lspdms:reverb-unreachable';

    public const PAUSE_SECONDS = 30;

    public static function send(object $event): void
    {
        if (config('broadcasting.default') !== 'reverb' || self::paused()) {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $exception) {
            self::pause();
            report($exception);
        }
    }

    private static function paused(): bool
    {
        try {
            return Cache::has(self::PAUSE_KEY);
        } catch (Throwable) {
            return false;
        }
    }

    private static function pause(): void
    {
        try {
            Cache::put(self::PAUSE_KEY, true, self::PAUSE_SECONDS);
        } catch (Throwable) {
            // The cache store being down must not turn a skipped broadcast into a failed request.
        }
    }
}
