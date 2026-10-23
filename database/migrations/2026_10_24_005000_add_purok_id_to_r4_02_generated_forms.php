<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remember which Purok segment (e.g. Purok 2a vs 2b, which share purok_number 2) an R4-02 was saved for.
     * Existing rows keep purok_id NULL and are still grouped by purok_number.
     */
    public function up(): void
    {
        if (! Schema::hasTable('r4_02_generated_forms') || Schema::hasColumn('r4_02_generated_forms', 'purok_id')) {
            return;
        }

        Schema::table('r4_02_generated_forms', function (Blueprint $table): void {
            $table->unsignedBigInteger('purok_id')->nullable()->after('purok_number');
            $table->foreign('purok_id', 'r4_02_generated_forms_purok_id_foreign')
                ->references('id')
                ->on('puroks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('r4_02_generated_forms') || ! Schema::hasColumn('r4_02_generated_forms', 'purok_id')) {
            return;
        }

        Schema::table('r4_02_generated_forms', function (Blueprint $table): void {
            $table->dropForeign('r4_02_generated_forms_purok_id_foreign');
            $table->dropColumn('purok_id');
        });
    }
};
