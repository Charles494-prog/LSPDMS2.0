<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSmtpSetting extends Model
{
    protected $fillable = [
        'email',
        'app_password',
    ];

    protected function casts(): array
    {
        return [
            'app_password' => 'encrypted',
        ];
    }
}
