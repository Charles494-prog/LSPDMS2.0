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
            'verified_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'verification_sent_at' => 'datetime',
        ];
    }

    /**
     * A fingerprint of the saved sender account. Verification is only valid
     * while the email and app password are unchanged since the code was confirmed.
     */
    public function fingerprint(): string
    {
        return hash_hmac('sha256', strtolower((string) $this->email).'|'.(string) $this->app_password, (string) config('app.key'));
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null
            && filled($this->verified_fingerprint)
            && hash_equals((string) $this->verified_fingerprint, $this->fingerprint());
    }
}