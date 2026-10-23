<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each person last opened each task on the Tasks page. A task someone can see but has never
 * opened is "new" for them, which drives the "There are new tasks for you!" pop-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assigned_task_reads')) {
            return;
        }

        Schema::create('assigned_task_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('assigned_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('login_user')->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->unique(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assigned_task_reads');
    }
};
