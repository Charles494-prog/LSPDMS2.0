<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * One way to compare a typed password with login_user.password.
 *
 * check() only accepts real password hashes. checkForLogin() additionally
 * accepts a legacy plaintext value ONCE and immediately replaces it with a
 * framework hash, so re-authentication prompts never accept plaintext.
 */
final class PasswordVerifier
{
    public static function isHashed(?string $stored): bool
    {
        return $stored !== null && $stored !== '' && Hash::isHashed($stored);
    }

    /** Hash-only verification (profile password change, role transfer, import re-auth). */
    public static function check(string $plain, ?string $stored): bool
    {
        if (! self::isHashed($stored)) {
            return false;
        }

        try {
            return Hash::check($plain, $stored);
        } catch (RuntimeException) {
            // A valid hash from another algorithm than the configured driver.
            return password_verify($plain, (string) $stored);
        }
    }

    /**
     * Login only: a legacy plaintext password is accepted when it matches
     * exactly and is rehashed right away. auth_version is NOT bumped, so the
     * user's other sessions stay signed in.
     */
    public static function checkForLogin(string $plain, object $user): bool
    {
        $stored = isset($user->password) ? (string) $user->password : '';
        if ($stored === '') {
            return false;
        }

        if (self::isHashed($stored)) {
            return self::check($plain, $stored);
        }

        if (! hash_equals($stored, $plain)) {
            return false;
        }

        // Only replace the value we compared against (a concurrent change wins).
        DB::table('login_user')
            ->where('id', $user->id)
            ->where('password', $stored)
            ->update(['password' => Hash::make($plain)]);

        return true;
    }
}
