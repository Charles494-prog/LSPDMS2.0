<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of R2-10 reasons per record. records.reason_of_r2_10_endorsement
 * keeps only the current reason; earlier reasons stay here with the record
 * type the member had when each reason was given. Rows follow their record
 * when an inactive record is deleted, like delistment_histories (the deleted
 * record's last values remain in record_audit_logs).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('r2_10_endorsement_reasons')) {
            return;
        }

        Schema::create('r2_10_endorsement_reasons', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('record_id');
            $table->string('record_type', 10);
            $table->longText('reason');
            $table->string('endorsed_by', 255)->nullable();
            $table->timestamp('endorsed_at')->nullable();
            $table->foreignId('monthly_session_id')
                ->nullable()
                ->constrained('monthly_movement_sessions')
                ->nullOnDelete();
            // Movements are deleted when a session finishes, so no foreign key.
            $table->unsignedBigInteger('session_movement_id')->nullable();
            $table->string('source', 30);
            $table->boolean('is_current')->default(false);
            $table->boolean('is_backfilled')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->foreign('record_id')->references('id')->on('records')->cascadeOnDelete();
            $table->index(['record_id', 'is_current']);
            $table->unique(['record_id', 'session_movement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('r2_10_endorsement_reasons');
    }
};
