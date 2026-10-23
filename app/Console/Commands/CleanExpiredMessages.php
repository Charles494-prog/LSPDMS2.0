<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\FileStore;
use Illuminate\Console\Command;

class CleanExpiredMessages extends Command
{
    protected $signature = 'messages:cleanup-expired';

    protected $description = 'Delete expired messages and their private attachments';

    public function handle(FileStore $files): int
    {
        Message::query()->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($messages) use ($files): void {
            foreach ($messages as $message) {
                if ($message->attachment_path) {
                    // Removes the disk file and its stored_files copy.
                    $files->delete('local', $message->attachment_path);
                }

                $message->delete();
            }
        });

        Conversation::query()->whereDoesntHave('messages')->delete();

        $this->info('Expired messages and unused conversations have been removed.');

        return self::SUCCESS;
    }
}
