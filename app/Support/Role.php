<?php

namespace App\Support;

/**
 * The single place that decides what a login_user.tungkulin value means.
 * Roles are compared case-insensitively with collapsed whitespace, and the
 * legacy "Secretary Head" spelling still counts as Head Secretary.
 */
final class Role
{
    public const HEAD_SECRETARY = 'Head Secretary';

    public const SECRETARY = 'Secretary';

    public static function normalize(?string $role): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $role) ?? ''));
    }

    public static function isHeadSecretary(?string $role): bool
    {
        return in_array(self::normalize($role), ['head secretary', 'secretary head'], true);
    }

    public static function isSecretary(?string $role): bool
    {
        return self::normalize($role) === 'secretary';
    }
}
