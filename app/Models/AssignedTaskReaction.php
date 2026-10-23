<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignedTaskReaction extends Model
{
    /** Facebook's six reactions. */
    public const TYPES = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    protected $fillable = [
        'task_id',
        'comment_id',
        'user_id',
        'target_key',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
