<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Break-glass IT recovery credential. Lives outside login_user so the normal
 * application can neither list nor edit it.
 */
class ItRecoveryAccount extends Model
{
    public const MAX_FAILED_ATTEMPTS = 10;

    public const LOCKOUT_MINUTES = 30;

    protected $table = 'it_recovery_accounts';

    protected $fillable = [
        'email',
        'password',
        'is_active',
        'failed_attempts',
        'locked_until',
        'last_login_at',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'failed_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
