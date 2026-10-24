<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each assigned secretary marks their own part done (done_at per member). The task goes to the Head
 * Secretary for confirmation once every member who may mark it done has done so.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('assigned_task_members', 'done_at')) {
            return;
        }

        Schema::table('assigned_task_members', function (Blueprint $table): void {
            $table->timestamp('done_at')->nullable()->after('can_complete');
        });

        // Tasks already marked done before this change: the person who did it keeps their mark.
        DB::table('assigned_tasks')->whereNotNull('done_by')->whereNotNull('done_at')->orderBy('id')
            ->each(function ($task): void {
                DB::table('assigned_task_members')
                    ->where('task_id', $task->id)->where('user_id', $task->done_by)
                    ->update(['done_at' => $task->done_at]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('assigned_task_members', 'done_at')) {
            Schema::table('assigned_task_members', function (Blueprint $table): void {
                $table->dropColumn('done_at');
            });
        }
    }
};
