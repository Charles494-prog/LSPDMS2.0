<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingColumns = array_values(array_filter(
            ['filing_status', 'filing_number'],
            fn (string $column): bool => ! Schema::hasColumn('records', $column)
        ));

        if ($missingColumns === []) {
            return;
        }

        Schema::table('records', function (Blueprint $table) use ($missingColumns): void {
            foreach ($missingColumns as $column) {
                if ($column === 'filing_status') {
                    $table->string('filing_status', 50)->nullable();
                } else {
                    $table->string('filing_number', 100)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        $existingColumns = array_values(array_filter(
            ['filing_status', 'filing_number'],
            fn (string $column): bool => Schema::hasColumn('records', $column)
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table('records', function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }
};
