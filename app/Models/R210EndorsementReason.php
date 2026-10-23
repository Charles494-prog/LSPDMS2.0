<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per R2-10 reason a record has had. The row flagged is_current always
 * holds the exact text stored in records.reason_of_r2_10_endorsement; earlier
 * rows keep the reasons that were replaced, with the record type (MS, UWP or
 * MK) the member had when that reason was given.
 */
class R210EndorsementReason extends Model
{
    public const SOURCE_DELISTMENT_ENDORSE = 'delistment_endorse';
    public const SOURCE_MONTHLY_MODIFY_MK = 'monthly_modify_mk';
    public const SOURCE_LEGACY_BACKFILL = 'legacy_backfill';
    public const SOURCE_UNTRACKED_CAPTURE = 'untracked_capture';

    public $timestamps = false;

    protected $table = 'r2_10_endorsement_reasons';

    protected $fillable = [
        'record_id',
        'record_type',
        'reason',
        'endorsed_by',
        'endorsed_at',
        'monthly_session_id',
        'session_movement_id',
        'source',
        'is_current',
        'is_backfilled',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'endorsed_at' => 'datetime',
            'created_at' => 'datetime',
            'is_current' => 'boolean',
            'is_backfilled' => 'boolean',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    public function monthlySession(): BelongsTo
    {
        return $this->belongsTo(MonthlyMovementSession::class, 'monthly_session_id');
    }

    /**
     * Make $reason the record's current R2-10 reason. The caller must hold a
     * row lock on $record inside a transaction and save the record afterwards
     * so the column change goes through the record audit log. Text already in
     * the column that no history row holds (for example an Inquire / Modify
     * edit) is kept as its own row first so it is never lost.
     */
    public static function recordCurrent(
        Record $record,
        string $recordType,
        string $reason,
        string $source,
        ?string $endorsedBy,
        ?CarbonInterface $endorsedAt,
        ?int $monthlySessionId = null,
        ?int $sessionMovementId = null
    ): self {
        $existing = trim((string) $record->getOriginal('reason_of_r2_10_endorsement'));
        if ($existing !== '') {
            $current = static::query()
                ->where('record_id', $record->id)
                ->where('is_current', true)
                ->orderByDesc('id')
                ->first();
            if (! $current || trim((string) $current->reason) !== $existing) {
                static::query()->create([
                    'record_id' => $record->id,
                    'record_type' => mb_strtoupper(trim((string) $record->getOriginal('status'))) ?: 'UNKNOWN',
                    'reason' => (string) $record->getOriginal('reason_of_r2_10_endorsement'),
                    'endorsed_by' => null,
                    'endorsed_at' => null,
                    'source' => self::SOURCE_UNTRACKED_CAPTURE,
                    'is_current' => false,
                    'is_backfilled' => true,
                    'created_at' => now(),
                ]);
            }
        }

        static::query()
            ->where('record_id', $record->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $entry = static::query()->create([
            'record_id' => $record->id,
            'record_type' => $recordType,
            'reason' => $reason,
            'endorsed_by' => $endorsedBy,
            'endorsed_at' => $endorsedAt,
            'monthly_session_id' => $monthlySessionId,
            'session_movement_id' => $sessionMovementId,
            'source' => $source,
            'is_current' => true,
            'is_backfilled' => false,
            'created_at' => now(),
        ]);
        $record->reason_of_r2_10_endorsement = $reason;

        return $entry;
    }

    /**
     * Remove the reason rows a monthly movement created and make the latest
     * remaining row current again. Runs inside the undo transaction.
     */
    public static function revertMovement(int $recordId, int $sessionMovementId): void
    {
        $deleted = static::query()
            ->where('record_id', $recordId)
            ->where('session_movement_id', $sessionMovementId)
            ->delete();
        if ($deleted === 0) {
            return;
        }

        $hasCurrent = static::query()
            ->where('record_id', $recordId)
            ->where('is_current', true)
            ->exists();
        if (! $hasCurrent) {
            $previous = static::query()
                ->where('record_id', $recordId)
                ->orderByDesc('id')
                ->first();
            $previous?->forceFill(['is_current' => true])->save();
        }
    }
}
