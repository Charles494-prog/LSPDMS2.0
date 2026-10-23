<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protected IT recovery (break-glass) account. Kept in its own tables, away
 * from login_user, so nothing in the normal application (user lists,
 * permission editors, SQL export/import) can see or change it. Accounts are
 * provisioned only with `php artisan lspdms:it-recovery:provision`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('it_recovery_accounts')) {
            Schema::create('it_recovery_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('failed_attempts')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamp('password_changed_at')->nullable();
                $table->timestamps();
            });
        }

        // Append-only: the application never updates or deletes these rows.
        if (! Schema::hasTable('it_recovery_audit_logs')) {
            Schema::create('it_recovery_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('event', 50);
                $table->string('account_email')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->json('details')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('it_recovery_audit_logs');
        Schema::dropIfExists('it_recovery_accounts');
    }
};
