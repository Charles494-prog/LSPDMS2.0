<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @mentions in tasks (title/details) and comments. One row per person mentioned; seen_at is set when
 * they open the Tasks page, and until then the "… mentioned you" reminder keeps showing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assigned_task_mentions')) {
            return;
        }

        Schema::create('assigned_task_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('assigned_tasks')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('assigned_task_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('login_user')->cascadeOnDelete();
            $table->foreignId('mentioned_by')->nullable()->constrained('login_user')->nullOnDelete();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assigned_task_mentions');
    }
};
