<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            if (! Schema::hasColumn('records', 'filing_status')) {
                $table->string('filing_status', 50)->nullable();
            }

            if (! Schema::hasColumn('records', 'filing_number')) {
                $table->string('filing_number', 100)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['filing_status', 'filing_number'],
                fn (string $column): bool => Schema::hasColumn('records', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
