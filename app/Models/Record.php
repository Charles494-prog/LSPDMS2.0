<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Record extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'full_name',
        'registry',
        'gender',
        'purok',
        'grupo',
        'kapisanan',
        'date_of_baptism',
        'evangelist',
        'date_of_entry',
        'status',
        'tarheta_status',
        'filing_status',
        'filing_number',
        'monthly_code',
        'date_of_birth',
        'is_married',
        'is_valid1',
        'is_valid2',
        'r2_10_endorsement',
        'reason_of_r2_10_endorsement',
        'log_section',
    ];

    protected function casts(): array
    {
        return [
            'date_of_baptism' => 'date',
            'date_of_entry' => 'date',
            'date_of_birth' => 'date',
            'is_married' => 'boolean',
            'is_valid1' => 'boolean',
            'is_valid2' => 'boolean',
        ];
    }

    public function delistmentHistories(): HasMany
    {
        return $this->hasMany(DelistmentHistory::class);
    }

    public function monthlyRecordMovementHistories(): HasMany
    {
        return $this->hasMany(MonthlyRecordMovementHistory::class);
    }

    public function r210EndorsementReasons(): HasMany
    {
        return $this->hasMany(R210EndorsementReason::class);
    }
}
