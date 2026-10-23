<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class R402GeneratedForm extends Model
{
    protected $table = 'r4_02_generated_forms';

    protected $fillable = [
        'purok_number',
        'purok_id',
        'kalagayan',
        'date_of_dalaw',
        'date_of_ulat',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purok_id' => 'integer',
            'date_of_dalaw' => 'date',
            'date_of_ulat' => 'date',
        ];
    }

    /**
     * The Purok segment (e.g. Purok 2a) the form was generated for; null on forms saved before segments were tracked.
     */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class, 'purok_id');
    }

    public function formRecords(): HasMany
    {
        return $this->hasMany(R402GeneratedFormRecord::class, 'generated_form_id')
            ->orderBy('source_row_number');
    }
}
