<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facebook-style replies (one level under a comment) and reactions (like, love, haha, wow, sad, angry)
 * on tasks and on comments. target_key is "t:{task id}" or "c:{comment id}", so one person has at most
 * one reaction per task or comment (a unique key that also works when comment_id is null).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assigned_task_comments', 'parent_id')) {
            Schema::table('assigned_task_comments', function (Blueprint $table): void {
                $table->foreignId('parent_id')->nullable()->after('task_id')->constrained('assigned_task_comments')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('assigned_task_reactions')) {
            Schema::create('assigned_task_reactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('task_id')->constrained('assigned_tasks')->cascadeOnDelete();
                $table->foreignId('comment_id')->nullable()->constrained('assigned_task_comments')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('login_user')->cascadeOnDelete();
                $table->string('target_key', 30);
                $table->string('type', 10);
                $table->timestamps();
                $table->unique(['user_id', 'target_key']);
                $table->index('task_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assigned_task_reactions');
        if (Schema::hasColumn('assigned_task_comments', 'parent_id')) {
            Schema::table('assigned_task_comments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('parent_id');
            });
        }
    }
};
