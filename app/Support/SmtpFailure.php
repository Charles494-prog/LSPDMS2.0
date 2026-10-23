<?php

namespace App\Support;

use Throwable;

/**
 * Turns a mail delivery exception into a short, safe failure class.
 * Raw exception text can contain the SMTP username, so it is never shown
 * to users and only the class, exception type and code are logged.
 */
final class SmtpFailure
{
    public const AUTH = 'auth';

    public const CONNECTION = 'connection';

    public const CONFIG = 'config';

    private const AUTH_CODES = [530, 534, 535];

    private const CONNECTION_CODES = [421, 450, 451, 452];

    public static function classify(Throwable $exception): string
    {
        $messages = [];
        $codes = [];
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $messages[] = mb_strtolower($current->getMessage());
            $codes[] = (int) $current->getCode();
        }
        $text = implode(' | ', $messages);

        if (array_intersect($codes, self::AUTH_CODES) !== []
            || self::containsAny($text, [
                'failed to authenticate',
                'authentication failed',
                'authentication required',
                'username and password not accepted',
                'application-specific password required',
                'invalid credentials',
                'code "535"',
                'code "534"',
                'code "530"',
                '5.7.8',
                '5.7.9',
            ])) {
            return self::AUTH;
        }

        if (array_intersect($codes, self::CONNECTION_CODES) !== []
            || self::containsAny($text, [
                'connection could not be established',
                'unable to connect',
                'could not connect',
                'connection refused',
                'connection reset',
                'connection timed out',
                'timed out',
                'getaddrinfo',
                'php_network_getaddresses',
                'network is unreachable',
                'no route to host',
                'name or service not known',
                'temporary failure in name resolution',
                'failed to open stream',
                'stream_socket_client',
                'unable to write bytes',
                'expected response code "220"',
            ])) {
            return self::CONNECTION;
        }

        // Anything else from the SMTP conversation (or a non-transport error
        // such as an invalid address or template problem) is a setup problem.
        return self::CONFIG;
    }

    /** User-facing text for a failure class. Never contains exception details. */
    public static function message(string $class): string
    {
        return match ($class) {
            self::AUTH => 'Gmail rejected the sign-in. Check the Gmail address and Google App Password (2-Step Verification must be on), then try again.',
            self::CONNECTION => 'Could not reach the Gmail SMTP server. Check the internet connection of the computer running LSPDMS, then try again.',
            default => 'The email could not be sent because of a mail setup problem. Check the sender settings, then try again.',
        };
    }

    /** Context that is safe to write to the log (no message text, no secrets). */
    public static function logContext(Throwable $exception, string $class, string $context): array
    {
        return [
            'context' => $context,
            'failure_class' => $class,
            'exception' => $exception::class,
            'code' => (int) $exception->getCode(),
        ];
    }

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
