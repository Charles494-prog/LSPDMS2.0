<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: a database restored from an older SQL export can already have
        // these tables while its migrations table does not list this migration.
        if (Schema::hasTable('r4_02_generated_forms')) {
            return;
        }

        Schema::create('r4_02_generated_forms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('purok_number')->index();
            $table->string('kalagayan', 3)->index();
            $table->date('date_of_dalaw')->nullable();
            $table->date('date_of_ulat')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('r4_02_generated_form_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('generated_form_id')
                ->constrained('r4_02_generated_forms')
                ->cascadeOnDelete();
            $table->unsignedInteger('record_id')->nullable()->index();
            $table->unsignedInteger('source_row_number');
            $table->string('hakbang_code', 30);
            $table->string('full_name', 150)->nullable();
            $table->string('registry', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('purok_grupo', 100)->nullable();
            $table->string('kapisanan', 50)->nullable();
            $table->string('record_code', 50)->nullable();
            $table->string('date_of_baptism', 30)->nullable();
            $table->string('doctrinator', 150)->nullable();
            $table->timestamps();
            $table->index(['generated_form_id', 'source_row_number'], 'r402_form_row_number_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('r4_02_generated_form_records');
        Schema::dropIfExists('r4_02_generated_forms');
    }
};
