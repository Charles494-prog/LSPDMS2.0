<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the people who can see a task that it changed (posted, commented, reacted, done…), so their
 * Tasks page and the "new tasks" reminder update straight away instead of waiting for the next poll.
 * Sent on each recipient's private user channel; it carries only what they can already see.
 */
class TaskFeedUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param list<int> $recipientIds */
    public function __construct(
        public array $recipientIds,
        public int $taskId,
        public string $kind,
        public string $title,
        public ?string $actorName,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(fn (int $id): PrivateChannel => new PrivateChannel('user.'.$id), $this->recipientIds);
    }

    public function broadcastAs(): string
    {
        return 'task.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->taskId,
            'kind' => $this->kind,
            'title' => $this->title,
            'actor' => $this->actorName,
        ];
    }
}
