<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class R402GeneratedForm extends Model
{
    protected $table = 'r4_02_generated_forms';

    protected $fillable = [
        'purok_number',
        'kalagayan',
        'date_of_dalaw',
        'date_of_ulat',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_dalaw' => 'date',
            'date_of_ulat' => 'date',
        ];
    }

    public function formRecords(): HasMany
    {
        return $this->hasMany(R402GeneratedFormRecord::class, 'generated_form_id')
            ->orderBy('source_row_number');
    }
}
