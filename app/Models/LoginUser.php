<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginUser extends Model
{
    protected $table = "login_user";
    public $timestamps = false;

    protected $fillable = [
        "gmail",
        "password",
        "first_name",
        "last_name",
        "phone",
        "is_head_secretary",
        "Delistment_allow",
        "tarheta_status_allow",
        "filing_status_allow",
        "chatbot_records_allow",
        "auth_version",
        "remember_token_version",
    ];

    protected function casts(): array
    {
        return [
            "is_head_secretary" => "boolean",
            "Delistment_allow" => "boolean",
            "tarheta_status_allow" => "boolean",
            "filing_status_allow" => "boolean",
            "chatbot_records_allow" => "boolean",
        ];
    }
}
