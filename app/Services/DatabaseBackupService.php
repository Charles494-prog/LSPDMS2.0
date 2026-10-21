<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DatabaseBackupService
{
    private const EXCLUDED_TABLES = ['system_smtp_settings'];

    public function tableNames(): array
    {
        $connection = DB::connection();

        return collect(Schema::getTables())
            ->filter(function (array|object $table) use ($connection): bool {
                if ($connection->getDriverName() !== 'mysql') {
                    return true;
                }

                $schema = is_array($table) ? ($table['schema'] ?? null) : ($table->schema ?? null);

                return is_string($schema)
                    && strcasecmp($schema, $connection->getDatabaseName()) === 0;
            })
            ->map(fn (array|object $table): string => is_array($table) ? $table['name'] : $table->name)
            ->reject(fn (string $name): bool => str_starts_with($name, 'sqlite_')
                || in_array($name, self::EXCLUDED_TABLES, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function writeSql($stream, array $excludedTables = []): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException("SQL export is not supported for the configured {$driver} database.");
        }

        fwrite($stream, "-- LSPDMS-SQL-EXPORT-V1\n-- ENGINE: {$driver}\n\n");
        $tables = array_values(array_diff($this->tableNames(), $excludedTables));
        foreach ($tables as $table) {
            $createStatement = $this->createTableStatement($table, $driver);
            fwrite($stream, $this->singleLine($createStatement).";\n");
            $exportColumns = $this->exportColumns($table);

            foreach (DB::table($table)->select($exportColumns)->cursor() as $row) {
                $row = (array) $row;
                $values = [];
                foreach ($exportColumns as $column) {
                    $value = $row[$column] ?? null;
                    $values[] = $this->sqlValue($value, $driver);
                }

                $wrappedColumns = array_map(
                    fn (string $column): string => DB::connection()->getQueryGrammar()->wrap($column),
                    $exportColumns
                );
                fwrite(
                    $stream,
                    'INSERT INTO '.DB::connection()->getQueryGrammar()->wrapTable($table)
                    .' ('.implode(', ', $wrappedColumns).') VALUES ('.implode(', ', $values).");\n"
                );
            }
            fwrite($stream, "\n");
        }
    }

    public function exportColumns(string $table): array
    {
        return array_values(array_diff(
            Schema::getColumnListing($table),
            ['head_secretary_slot']
        ));
    }

    public function createSafetyBackup(): string
    {
        $directory = storage_path('app/private/database-backups');
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the private database backup directory.');
        }

        $path = $directory.DIRECTORY_SEPARATOR.'before-import-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
        $stream = fopen($path, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Unable to create a safety backup before importing.');
        }

        try {
            $this->writeSql($stream);
        } catch (\Throwable $exception) {
            fclose($stream);
            @unlink($path);
            throw $exception;
        }

        fclose($stream);

        return $path;
    }

    public function inspectImport(UploadedFile $file): array
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw ValidationException::withMessages([
                'sql_file' => "SQL import is not supported for the configured {$driver} database.",
            ]);
        }

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages(['sql_file' => 'The SQL file could not be read.']);
        }

        $header = fgets($stream);
        $engineLine = fgets($stream);
        if (trim((string) $header) !== '-- LSPDMS-SQL-EXPORT-V1'
            || trim((string) $engineLine) !== "-- ENGINE: {$driver}") {
            fclose($stream);
            throw ValidationException::withMessages([
                'sql_file' => 'Choose an LSPDMS SQL backup made for the same database engine as this installation.',
            ]);
        }

        $createStatements = [];
        $tables = [];

        try {
            while (($line = fgets($stream)) !== false) {
                $statement = trim($line);
                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }

                if (preg_match('/^CREATE TABLE\s+(?:`([^`]+)`|"([^"]+)"|([A-Za-z0-9_]+))\s+/i', $statement, $matches)) {
                    $table = $this->matchedIdentifier($matches);
                    $this->assertImportableTable($table);
                    if (isset($createStatements[$table])) {
                        throw ValidationException::withMessages(['sql_file' => "The SQL backup contains duplicate table definitions for {$table}."]);
                    }
                    if (! str_ends_with($statement, ';')) {
                        throw ValidationException::withMessages(['sql_file' => 'The SQL backup contains an incomplete table definition.']);
                    }
                    $createStatements[$table] = $statement;
                    $tables[$table] = true;

                    continue;
                }

                $table = $this->insertTable($statement, $driver);
                if ($table !== null) {
                    $this->assertImportableTable($table);
                    $tables[$table] = true;

                    continue;
                }

                throw ValidationException::withMessages([
                    'sql_file' => 'The SQL backup contains a statement that LSPDMS will not import.',
                ]);
            }
        } finally {
            fclose($stream);
        }

        if ($tables === [] || array_diff(array_keys($tables), array_keys($createStatements)) !== []) {
            throw ValidationException::withMessages([
                'sql_file' => 'The SQL backup is empty or is missing a table definition.',
            ]);
        }

        $tableNames = array_keys($tables);
        $unknownTables = array_values(array_diff($tableNames, $this->tableNames()));
        if ($unknownTables !== []) {
            throw ValidationException::withMessages([
                'sql_file' => 'Run the application migrations on this PC before importing. Missing tables: '.implode(', ', $unknownTables).'.',
            ]);
        }

        return [
            'tables' => $tableNames,
            'omitted_tables' => array_values(array_diff($this->tableNames(), $tableNames)),
        ];
    }

    public function import(UploadedFile $file): string
    {
        $inspection = $this->inspectImport($file);
        $backupPath = $this->createSafetyBackup();
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } else {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            DB::transaction(function () use ($inspection, $file, $driver): void {
                foreach ($inspection['tables'] as $table) {
                    DB::table($table)->delete();
                }

                $this->forEachInsert($file, $driver, static function (string $statement): void {
                    DB::unprepared($statement);
                });
            });
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } else {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }

        return $backupPath;
    }

    private function createTableStatement(string $table, string $driver): string
    {
        $grammar = DB::connection()->getQueryGrammar();

        if ($driver === 'mysql') {
            $row = DB::selectOne('SHOW CREATE TABLE '.$grammar->wrapTable($table));
            $values = array_values((array) $row);

            return $values[1];
        }

        $row = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table]
        );

        if (! $row?->sql) {
            throw new RuntimeException("Unable to read the schema for table {$table}.");
        }

        return $row->sql;
    }

    private function sqlValue(mixed $value, string $driver): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $hex = bin2hex((string) $value);

        return $driver === 'mysql'
            ? "CONVERT(X'{$hex}' USING utf8mb4)"
            : "CAST(X'{$hex}' AS TEXT)";
    }

    private function singleLine(string $statement): string
    {
        $result = '';
        $quote = null;
        $length = strlen($statement);

        for ($index = 0; $index < $length; $index++) {
            $character = $statement[$index];
            if ($quote !== null) {
                $result .= $character;
                if ($character === '\\' && $index + 1 < $length) {
                    $result .= $statement[++$index];
                } elseif ($character === $quote) {
                    if ($quote === '`' && ($statement[$index + 1] ?? null) === '`') {
                        $result .= $statement[++$index];
                    } elseif (($statement[$index + 1] ?? null) === $quote && $quote !== '`') {
                        $result .= $statement[++$index];
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }

            if (in_array($character, ["'", '"', '`'], true)) {
                $quote = $character;
                $result .= $character;
            } elseif (ctype_space($character)) {
                if ($result !== '' && ! str_ends_with($result, ' ')) {
                    $result .= ' ';
                }
            } else {
                $result .= $character;
            }
        }

        return trim($result);
    }

    private function matchedIdentifier(array $matches): string
    {
        return $matches[1] ?: ($matches[2] ?: $matches[3]);
    }

    private function insertTable(string $statement, string $driver): ?string
    {
        $identifierPattern = '(?:`([A-Za-z0-9_]+)`|"([A-Za-z0-9_]+)"|([A-Za-z0-9_]+))';
        $valuePattern = $driver === 'mysql'
            ? "(?:NULL|CONVERT\\(X'[a-f0-9]*' USING utf8mb4\\))"
            : "(?:NULL|CAST\\(X'[a-f0-9]*' AS TEXT\\))";
        $columnPattern = $identifierPattern.'(?:\s*,\s*'.$identifierPattern.')*';
        $insertPattern = '/^INSERT INTO\s+'.$identifierPattern
            .'\s+\('.$columnPattern.'\)\s+VALUES\s+\('
            .$valuePattern.'(?:\s*,\s*'.$valuePattern.')*\);$/i';

        if (! preg_match($insertPattern, $statement, $matches)) {
            return null;
        }

        return $this->matchedIdentifier($matches);
    }

    private function forEachInsert(UploadedFile $file, string $driver, callable $callback): void
    {
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('The SQL upload could not be read during import.');
        }

        fgets($stream);
        fgets($stream);
        try {
            while (($line = fgets($stream)) !== false) {
                $statement = trim($line);
                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }

                if (str_starts_with(strtoupper($statement), 'INSERT INTO ')) {
                    if ($this->insertTable($statement, $driver) === null) {
                        throw ValidationException::withMessages([
                            'sql_file' => 'The SQL backup contains an invalid insert statement.',
                        ]);
                    }
                    $callback($statement);
                }
            }
        } finally {
            fclose($stream);
        }
    }

    private function assertImportableTable(string $table): void
    {
        if (in_array($table, self::EXCLUDED_TABLES, true) || ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw ValidationException::withMessages([
                'sql_file' => 'The SQL backup contains an unsupported table name.',
            ]);
        }
    }
}
