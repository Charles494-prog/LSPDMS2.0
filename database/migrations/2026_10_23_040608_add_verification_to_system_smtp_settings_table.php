<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: a database restored from an older SQL export can already have
        // these columns while its migrations table does not list this migration.
        if (Schema::hasColumn('system_smtp_settings', 'verified_at')) {
            return;
        }

        Schema::table('system_smtp_settings', function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_fingerprint', 64)->nullable();
            $table->string('verification_code_hash', 64)->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->timestamp('verification_sent_at')->nullable();
            $table->string('verification_sent_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('system_smtp_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'verified_at',
                'verified_fingerprint',
                'verification_code_hash',
                'verification_expires_at',
                'verification_attempts',
                'verification_sent_at',
                'verification_sent_to',
            ]);
        });
    }
};