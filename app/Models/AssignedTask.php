<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignedTask extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'body',
        'due_date',
        'visibility',
        'others_can_comment',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'others_can_comment' => 'boolean',
            'done_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(AssignedTaskMember::class, 'task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AssignedTaskComment::class, 'task_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(AssignedTaskReaction::class, 'task_id');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(AssignedTaskMention::class, 'task_id');
    }
}
