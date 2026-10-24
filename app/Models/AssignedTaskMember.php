<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignedTaskMember extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'can_comment',
        'can_complete',
        'done_at',
    ];

    protected function casts(): array
    {
        return [
            'can_comment' => 'boolean',
            'can_complete' => 'boolean',
            'done_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
