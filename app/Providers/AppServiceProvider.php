<?php

namespace App\Providers;

use App\Broadcasting\ConversationChannel;
use App\Models\Record;
use App\Observers\RecordObserver;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /** Storage folders every page needs to write to (logs, sessions, compiled Blade views). */
    private const WRITABLE_STORAGE = ['logs', 'framework/sessions', 'framework/views'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Record::observe(RecordObserver::class);
        Broadcast::channel('conversation.{conversationId}', [ConversationChannel::class, 'join']);

        // /up (and the launcher's health step) should only say "up" when real pages can work.
        // Throwing here makes Laravel's health route report the error and answer 500.
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');

            foreach (self::WRITABLE_STORAGE as $folder) {
                if (! is_writable(storage_path($folder))) {
                    throw new RuntimeException("storage/{$folder} is not writable.");
                }
            }
        });
    }
}
