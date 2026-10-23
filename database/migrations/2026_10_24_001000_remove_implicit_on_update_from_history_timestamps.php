<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MariaDB (explicit_defaults_for_timestamp = OFF) silently gave the first
 * TIMESTAMP column of these tables "ON UPDATE current_timestamp()", so any
 * later UPDATE of the row (finishing a session, unlinking activities) rewrote
 * the original time. Redefine each column with the same type, nullability and
 * default but without the auto-update. Values are not touched. SQLite has no
 * such attribute, so nothing runs there.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'delistment_histories' => 'delisted_at',
        'monthly_movement_sessions' => 'started_at',
        'monthly_record_movement_histories' => 'changed_at',
        'monthly_session_activities' => 'changed_at',
        'monthly_session_movements' => 'changed_at',
        'permanent_history' => 'changed_at',
        'record_audit_logs' => 'created_at',
        'system_maintenance_audits' => 'changed_at',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::COLUMNS as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $definition = DB::selectOne(
                'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );
            if (! $definition || stripos((string) $definition->EXTRA, 'on update') === false) {
                continue;
            }

            $nullable = strtoupper((string) $definition->IS_NULLABLE) === 'YES';
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s %s%s',
                $table,
                $column,
                $definition->COLUMN_TYPE,
                $nullable ? 'NULL' : 'NOT NULL',
                $this->defaultClause($definition->COLUMN_DEFAULT, $nullable)
            ));
        }
    }

    public function down(): void
    {
        // Intentionally left as is: restoring "ON UPDATE current_timestamp()"
        // would bring back the silent overwriting of recorded times.
    }

    private function defaultClause(?string $default, bool $nullable): string
    {
        if ($default === null) {
            return $nullable ? ' DEFAULT NULL' : '';
        }
        if (strtoupper($default) === 'NULL') {
            return ' DEFAULT NULL';
        }
        if (preg_match('/^current_timestamp(\(\d*\))?$/i', $default) === 1) {
            return ' DEFAULT CURRENT_TIMESTAMP';
        }
        // MariaDB reports literal defaults already quoted; MySQL does not.
        if (str_starts_with($default, "'")) {
            return ' DEFAULT '.$default;
        }

        return ' DEFAULT '.DB::getPdo()->quote($default);
    }
};
