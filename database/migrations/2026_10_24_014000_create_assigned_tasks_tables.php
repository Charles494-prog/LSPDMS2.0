<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks the Head Secretary assigns to secretaries (shown as a feed in the chat window).
 * Members are the assigned secretaries; each one can be allowed to comment and/or mark it done.
 * A task is seen by its members only, or by every secretary (visibility = everyone).
 * A secretary's "done" waits for the Head Secretary's confirmation, which closes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assigned_tasks')) {
            Schema::create('assigned_tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('created_by')->nullable()->constrained('login_user')->nullOnDelete();
                $table->string('title', 200);
                $table->text('body')->nullable();
                $table->date('due_date')->nullable();
                // assigned: only the assigned secretaries see it; everyone: every secretary sees it
                $table->string('visibility', 12)->default('assigned');
                // when visible to everyone: may secretaries who are not assigned comment?
                $table->boolean('others_can_comment')->default(false);
                $table->foreignId('done_by')->nullable()->constrained('login_user')->nullOnDelete();
                $table->timestamp('done_at')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('login_user')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assigned_task_members')) {
            Schema::create('assigned_task_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('task_id')->constrained('assigned_tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('login_user')->cascadeOnDelete();
                $table->boolean('can_comment')->default(true);
                $table->boolean('can_complete')->default(true);
                $table->timestamps();
                $table->unique(['task_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('assigned_task_comments')) {
            Schema::create('assigned_task_comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('task_id')->constrained('assigned_tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('login_user')->nullOnDelete();
                // comment, or a status event: done, reopened, confirmed
                $table->string('kind', 20)->default('comment');
                $table->text('body')->nullable();
                $table->timestamps();
                $table->index(['task_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assigned_task_comments');
        Schema::dropIfExists('assigned_task_members');
        Schema::dropIfExists('assigned_tasks');
    }
};
