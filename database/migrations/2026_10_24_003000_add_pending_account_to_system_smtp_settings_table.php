<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new Gmail sender is kept as PENDING until its code is confirmed, so the
 * active verified account keeps working meanwhile. Also records the class of
 * the last delivery failure ('auth' | 'connection' | 'config').
 */
return new class extends Migration
{
    private const COLUMNS = [
        'pending_email',
        'pending_app_password',
        'pending_saved_at',
        'verification_target',
        'last_failure_class',
        'last_failure_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('system_smtp_settings')) {
            return;
        }

        Schema::table('system_smtp_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_smtp_settings', 'pending_email')) {
                $table->string('pending_email')->nullable();
            }
            if (! Schema::hasColumn('system_smtp_settings', 'pending_app_password')) {
                $table->text('pending_app_password')->nullable();
            }
            if (! Schema::hasColumn('system_smtp_settings', 'pending_saved_at')) {
                $table->timestamp('pending_saved_at')->nullable();
            }
            // Fingerprint of the account the outstanding code was sent through.
            if (! Schema::hasColumn('system_smtp_settings', 'verification_target')) {
                $table->string('verification_target', 64)->nullable();
            }
            if (! Schema::hasColumn('system_smtp_settings', 'last_failure_class')) {
                $table->string('last_failure_class', 20)->nullable();
            }
            if (! Schema::hasColumn('system_smtp_settings', 'last_failure_at')) {
                $table->timestamp('last_failure_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_smtp_settings')) {
            return;
        }

        $existing = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('system_smtp_settings', $column)
        ));
        if ($existing !== []) {
            Schema::table('system_smtp_settings', function (Blueprint $table) use ($existing): void {
                $table->dropColumn($existing);
            });
        }
    }
};
