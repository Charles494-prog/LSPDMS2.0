<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;

/**
 * Append-only audit trail of the IT recovery surface. Entries are only ever
 * inserted through record(); updates and deletes are refused. Details must
 * never contain passwords, hashes or other secrets.
 */
class ItRecoveryAuditLog extends Model
{
    protected $table = 'it_recovery_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'event',
        'account_email',
        'ip',
        'user_agent',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('IT recovery audit entries are append-only.');
        });
        static::deleting(function (): void {
            throw new LogicException('IT recovery audit entries are append-only.');
        });
    }

    public static function record(string $event, ?string $accountEmail, ?Request $request = null, array $details = []): self
    {
        return static::query()->create([
            'event' => mb_substr($event, 0, 50),
            'account_email' => $accountEmail === null ? null : mb_substr($accountEmail, 0, 255),
            'ip' => $request === null ? null : mb_substr((string) $request->ip(), 0, 45),
            'user_agent' => $request === null
                ? 'artisan'
                : mb_substr((string) $request->userAgent(), 0, 255),
            'details' => $details === [] ? null : $details,
            'created_at' => now(),
        ]);
    }
}
