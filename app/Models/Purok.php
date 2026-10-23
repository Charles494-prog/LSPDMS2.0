<?php

namespace App\Models;

use App\Services\PurokSelection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purok extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purok_number',
        'purok_name',
        'secretary',
        'manggagawa',
        'ministerial_id',
        'grupo_start',
        'grupo_end',
        'full_purok',
    ];

    protected $casts = [
        'full_purok' => 'boolean',
    ];

    public function secretaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary');
    }

    /**
     * Puroks ordered for the R4-01 / R4-02 Purok filters, each carrying its filter token as `selection_token`:
     * "N:ID" when purok number N has more than one segment (e.g. Purok 2a / 2b), otherwise "N".
     *
     * @return Collection<int, self>
     */
    public static function selectionOptions(): Collection
    {
        $puroks = static::query()
            ->orderBy('purok_number')
            ->orderBy('grupo_start')
            ->orderBy('id')
            ->get(['id', 'purok_number', 'purok_name', 'grupo_start', 'grupo_end', 'full_purok']);
        $segmentCounts = $puroks->countBy('purok_number');

        return $puroks->each(function (self $purok) use ($segmentCounts): void {
            $purok->setAttribute('selection_token', PurokSelection::tokenFor(
                (int) $purok->purok_number,
                (int) $purok->id,
                (int) ($segmentCounts[$purok->purok_number] ?? 1)
            ));
        });
    }

    // Shows the range as "1 to 12"
    public function getSakopNaGrupoAttribute(): string
    {
        return "{$this->grupo_start} to {$this->grupo_end}";
    }
}
