<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The records table previously had only its primary key. EXPLAIN on a copy of the
 * live data showed the registry duplicate check (which runs under a row lock on
 * every edit) and the name-ordered record lists as full scans with a filesort; with
 * (full_name, registry) the duplicate check becomes a covering index scan with no
 * filesort. Single-column indexes on registry, status, purok/grupo and monthly_code
 * were not added: every query filters those through UPPER/TRIM/LOWER, so MariaDB
 * cannot use such indexes, and status/purok have only a handful of distinct values.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $indexes = [
        'records_full_name_registry_index' => ['full_name', 'registry'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('records')) {
            return;
        }

        foreach ($this->indexes as $name => $columns) {
            if (! Schema::hasColumns('records', $columns) || Schema::hasIndex('records', $name)) {
                continue;
            }

            Schema::table('records', function (Blueprint $table) use ($columns, $name): void {
                $table->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('records')) {
            return;
        }

        foreach (array_keys($this->indexes) as $name) {
            if (! Schema::hasIndex('records', $name)) {
                continue;
            }

            Schema::table('records', function (Blueprint $table) use ($name): void {
                $table->dropIndex($name);
            });
        }
    }
};
