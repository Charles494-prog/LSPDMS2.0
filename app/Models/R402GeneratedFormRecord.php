<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class R402GeneratedFormRecord extends Model
{
    protected $table = 'r4_02_generated_form_records';

    protected $fillable = [
        'generated_form_id',
        'record_id',
        'source_row_number',
        'hakbang_code',
        'full_name',
        'registry',
        'gender',
        'purok_grupo',
        'kapisanan',
        'record_code',
        'date_of_baptism',
        'doctrinator',
    ];

    public function generatedForm(): BelongsTo
    {
        return $this->belongsTo(R402GeneratedForm::class, 'generated_form_id');
    }
}
