<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'login_user';

    public $timestamps = false;

    protected $fillable = [
        'gmail',
        'password',
        'tungkulin',
        'is_verified',
        'first_name',
        'last_name',
        'gender',
        'profile_picture',
        'dashboard_allow',
        'form_generator_allow',
        'Delistment_allow',
        'Inquire_Allow',
        'tarheta_status_allow',
        'filter_bnh_helper_allow',
        'chatbot_records_allow',
        'note_checker_allow',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'remember_token_expires_at' => 'datetime',
            'dashboard_allow' => 'boolean',
            'form_generator_allow' => 'boolean',
            'Delistment_allow' => 'boolean',
            'Inquire_Allow' => 'boolean',
            'tarheta_status_allow' => 'boolean',
            'filter_bnh_helper_allow' => 'boolean',
            'chatbot_records_allow' => 'boolean',
            'note_checker_allow' => 'boolean',
        ];
    }

    // Since your email column is "gmail", Laravel needs to know
    // where to send password reset emails.
    public function getEmailForPasswordReset(): string
    {
        return $this->gmail;
    }

    public function routeNotificationForMail(): string
    {
        return $this->gmail;
    }

    // "Dudu Bear"
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Puroks where this user is the secretary.
    public function secretaryPuroks(): HasMany
    {
        return $this->hasMany(Purok::class, 'secretary');
    }
}
