<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignedTaskMention extends Model
{
    protected $fillable = [
        'task_id',
        'comment_id',
        'user_id',
        'mentioned_by',
        'seen_at',
    ];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AssignedTask::class, 'task_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(AssignedTaskComment::class, 'comment_id');
    }

    public function mentionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_by');
    }
}
