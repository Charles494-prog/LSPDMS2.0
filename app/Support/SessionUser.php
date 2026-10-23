<?php

namespace App\Support;

/**
 * The array kept in the session under 'login_user', built from a login_user
 * row. One definition shared by login, remember-me and the per-request refresh.
 */
final class SessionUser
{
    public static function fromRow(object $user): array
    {
        return [
            'id' => $user->id,
            'auth_version' => (int) $user->auth_version,
            'gmail' => $user->gmail,
            'tungkulin' => $user->tungkulin,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'gender' => $user->gender,
            'profile_picture' => $user->profile_picture,
            'dark_mode' => (bool) $user->dark_mode,
            'dashboard_allow' => (bool) $user->dashboard_allow,
            'form_generator_allow' => (bool) $user->form_generator_allow,
            'Delistment_allow' => (bool) $user->Delistment_allow,
            'Inquire_Allow' => (bool) $user->Inquire_Allow,
            'tarheta_status_allow' => (bool) $user->tarheta_status_allow,
            'filter_bnh_helper_allow' => (bool) $user->filter_bnh_helper_allow,
            'chatbot_records_allow' => (bool) ($user->chatbot_records_allow ?? false),
            'is_verified' => (bool) $user->is_verified,
        ];
    }
}
