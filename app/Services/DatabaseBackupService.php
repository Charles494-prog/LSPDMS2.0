<?php

namespace App\Services;

use App\Exceptions\DatabaseBackupFailed;
use App\Exceptions\DatabaseMaintenanceInProgress;
use App\Support\Role;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DatabaseBackupService
{
    /**
     * Never written to an export or safety backup, and never replaced by an
     * import (their INSERT statements in older files are skipped): migration
     * state, secrets, session/cache/queue data, and the protected IT recovery
     * tables.
     */
    public const NEVER_EXPORTED_TABLES = [
        // Must always describe this machine's schema. An import only replaces
        // rows (no table definitions run), so restoring an older migrations
        // list would make "php artisan migrate" re-run applied migrations.
        'migrations',
        'system_smtp_settings',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'it_recovery_accounts',
        'it_recovery_audit_logs',
    ];

    /** Columns exported as NULL: they let a browser sign in without a password. */
    public const REDACTED_COLUMNS = [
        'login_user' => ['remember_token', 'remember_token_expires_at'],
    ];

    /** Columns never written to an XLSX export. */
    public const XLSX_HIDDEN_COLUMNS = [
        'login_user' => ['password', 'remember_token', 'remember_token_expires_at'],
    ];

    /**
     * Database file copies belong to the table that references them, so an
     * export without (for example) messages carries no chat images, and an
     * import only replaces the file copies of the tables it contains.
     *
     * They are also the only places an import may put a file. The public
     * disk is web-served (/storage), so it only takes image extensions;
     * null means any extension (private local disk).
     */
    public const FILE_OWNERS = [
        'login_user' => ['disk' => 'public', 'prefix' => 'profile-pictures/', 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'avif']],
        'messages' => ['disk' => 'local', 'prefix' => 'messages/', 'extensions' => null],
        'r4_01_snapshots' => ['disk' => 'local', 'prefix' => 'monthly-session-snapshots/', 'extensions' => ['docx', 'html']],
    ];

    private const FILE_TABLES = ['stored_files', 'stored_file_chunks'];

    private const LOCK_NAME = 'lspdms:database-maintenance';

    private const LOCK_SECONDS = 900;

    /** Extra free space kept on top of the size checks (bytes). */
    private const SPACE_MARGIN = 52428800;

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
                || in_array($name, self::NEVER_EXPORTED_TABLES, true))
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
        $grammar = DB::connection()->getQueryGrammar();
        foreach ($tables as $table) {
            $createStatement = $this->createTableStatement($table, $driver);
            fwrite($stream, $this->singleLine($createStatement).";\n");
            $exportColumns = $this->exportColumns($table);
            $redacted = self::REDACTED_COLUMNS[$table] ?? [];
            $wrappedColumns = array_map(fn (string $column): string => $grammar->wrap($column), $exportColumns);
            $prefix = 'INSERT INTO '.$grammar->wrapTable($table).' ('.implode(', ', $wrappedColumns).') VALUES (';

            $query = DB::table($table)->select($exportColumns);
            if (in_array($table, self::FILE_TABLES, true)) {
                $this->limitToExportedOwners($query, $tables);
            }
            foreach ($query->orderBy($exportColumns[0])->cursor() as $row) {
                $row = (array) $row;
                if (in_array($table, self::FILE_TABLES, true)
                    && ! self::isAllowedFileLocation($row['disk'] ?? null, $row['path'] ?? null)) {
                    // The import would refuse the whole file over this row.
                    continue;
                }
                $values = [];
                foreach ($exportColumns as $column) {
                    $value = in_array($column, $redacted, true) ? null : ($row[$column] ?? null);
                    $values[] = $this->sqlValue($value, $driver);
                }

                fwrite($stream, $prefix.implode(', ', $values).");\n");
            }
            fwrite($stream, "\n");
        }
    }

    public function exportColumns(string $table): array
    {
        $hidden = ['head_secretary_slot'];
        if (in_array($table, self::FILE_TABLES, true)) {
            // Re-keyed by (disk, path) on import, so row ids never collide.
            $hidden[] = 'id';
        }

        return array_values(array_diff(Schema::getColumnListing($table), $hidden));
    }

    /** Columns an XLSX export may show for $table (never passwords or tokens). */
    public function xlsxColumns(string $table): array
    {
        return array_values(array_diff($this->exportColumns($table), self::XLSX_HIDDEN_COLUMNS[$table] ?? []));
    }

    /**
     * Write a backup into the private backup folder and prove it can be read
     * back by the import parser with the same row counts as the database.
     */
    public function createSafetyBackup(?string $name = null, string $source = 'import'): string
    {
        $directory = $this->backupDirectory();
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new DatabaseBackupFailed('Unable to create the private database backup directory.');
        }

        $name ??= 'before-import-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        // 'x' never overwrites; a name still held by Windows after a delete gets a short suffix.
        $stream = @fopen($path, 'xb');
        for ($attempt = 0; $stream === false && $attempt < 3; $attempt++) {
            $name = pathinfo($name, PATHINFO_FILENAME).'-'.bin2hex(random_bytes(2)).'.sql';
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            $stream = @fopen($path, 'xb');
        }
        if ($stream === false) {
            throw new DatabaseBackupFailed('Unable to create the backup file in the private backups folder.');
        }

        try {
            $this->writeSql($stream);
        } catch (\Throwable $exception) {
            fclose($stream);
            @unlink($path);
            throw $exception;
        }
        fclose($stream);

        try {
            $this->assertBackupUsable($path);
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception instanceof DatabaseBackupFailed
                ? $exception
                : new DatabaseBackupFailed('The safety backup could not be verified, so nothing was imported.');
        }
        $this->recordBackup($name, $source);

        return $path;
    }

    /**
     * A backup is usable when the import parser accepts it and every table
     * it holds has exactly as many rows as the live database.
     */
    public function assertBackupUsable(string $path): void
    {
        try {
            $inspection = $this->inspectImport($path);
        } catch (ValidationException) {
            throw new DatabaseBackupFailed('The safety backup could not be read back, so nothing was imported.');
        }

        foreach ($inspection['row_counts'] as $table => $count) {
            $query = DB::table($table);
            if (in_array($table, self::FILE_TABLES, true)) {
                $this->limitToExportedOwners($query, $inspection['tables']);
                $expected = $query->get(['disk', 'path'])
                    ->filter(fn (object $row): bool => self::isAllowedFileLocation($row->disk, $row->path))
                    ->count();
            } else {
                $expected = $query->count();
            }
            if ($expected !== $count) {
                throw new DatabaseBackupFailed("The safety backup is incomplete for table {$table}, so nothing was imported.");
            }
        }
    }

    public function inspectImport(UploadedFile|string $file): array
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw ValidationException::withMessages([
                'sql_file' => "SQL import is not supported for the configured {$driver} database.",
            ]);
        }

        $stream = @fopen($this->filePath($file), 'rb');
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
        $rowCounts = [];
        $ignored = [];

        try {
            while (($line = fgets($stream)) !== false) {
                $statement = trim($line);
                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }

                if (preg_match('/^CREATE TABLE\s+(?:`([^`]+)`|"([^"]+)"|([A-Za-z0-9_]+))\s+/i', $statement, $matches)) {
                    $table = $this->matchedIdentifier($matches);
                    $this->assertValidTableName($table);
                    if (in_array($table, self::NEVER_EXPORTED_TABLES, true)) {
                        $ignored[$table] = true;

                        continue;
                    }
                    if (isset($createStatements[$table])) {
                        throw ValidationException::withMessages(['sql_file' => "The SQL backup contains duplicate table definitions for {$table}."]);
                    }
                    if (! str_ends_with($statement, ';')) {
                        throw ValidationException::withMessages(['sql_file' => 'The SQL backup contains an incomplete table definition.']);
                    }
                    $createStatements[$table] = $statement;
                    $tables[$table] = true;
                    $rowCounts[$table] = 0;

                    continue;
                }

                $table = $this->insertTable($statement, $driver);
                if ($table !== null) {
                    $this->assertValidTableName($table);
                    if (in_array($table, self::NEVER_EXPORTED_TABLES, true)) {
                        $ignored[$table] = true;

                        continue;
                    }
                    $this->assertAllowedFileRow($table, $statement);
                    $tables[$table] = true;
                    $rowCounts[$table] = ($rowCounts[$table] ?? 0) + 1;

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
            'row_counts' => $rowCounts,
            'ignored_tables' => array_keys($ignored),
            'omitted_tables' => array_values(array_diff($this->tableNames(), $tableNames)),
        ];
    }

    /**
     * Replace the tables in $file with its rows.
     *
     * Order: lock -> free-space check -> verified safety backup -> one
     * transaction that deletes and re-inserts the rows and checks that a
     * verified Head Secretary still exists -> audit row -> restore file copies
     * to disk. Only rows change (no table definitions run), so a failure rolls
     * the data back; the safety backup is the fallback either way.
     *
     * @return array{backup_path: string, tables: array<int, string>, ignored_tables: array<int, string>, inserted_rows: int, restored_files: int}
     */
    public function import(UploadedFile|string $file, ?string $actor = null): array
    {
        return $this->withMaintenanceLock(function () use ($file, $actor): array {
            $path = $this->filePath($file);
            $inspection = $this->inspectImport($path);
            $this->assertEnoughSpace((int) @filesize($path));
            $backupPath = $this->createSafetyBackup();
            $driver = DB::connection()->getDriverName();
            $inserted = 0;

            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } else {
                // foreign_keys is ignored inside an open transaction; deferring works there too.
                DB::statement('PRAGMA foreign_keys = OFF');
                DB::statement('PRAGMA defer_foreign_keys = ON');
            }

            try {
                DB::transaction(function () use ($inspection, $path, $driver, &$inserted): void {
                    foreach ($inspection['tables'] as $table) {
                        $query = DB::table($table);
                        if (in_array($table, self::FILE_TABLES, true)) {
                            $this->limitToExportedOwners($query, $inspection['tables']);
                        }
                        $query->delete();
                    }

                    $this->forEachInsert($path, $driver, static function (string $statement) use (&$inserted): void {
                        DB::unprepared($statement);
                        $inserted++;
                    });

                    if (! $this->hasVerifiedHeadSecretary()) {
                        throw ValidationException::withMessages([
                            'sql_file' => 'This backup has no approved Head Secretary account, so importing it would lock everyone out. Nothing was changed.',
                        ]);
                    }
                });
            } finally {
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } else {
                    DB::statement('PRAGMA defer_foreign_keys = OFF');
                    DB::statement('PRAGMA foreign_keys = ON');
                }
            }

            // Written after the commit: the import may have replaced this table.
            $this->audit('database_import', $inserted, $actor);
            $restored = 0;
            try {
                $restored = app(FileStore::class)->rehydrateMissing();
            } catch (\Throwable) {
                // Files are restored on first use anyway (FileStore::get).
            }

            return [
                'backup_path' => $backupPath,
                'tables' => $inspection['tables'],
                'ignored_tables' => $inspection['ignored_tables'],
                'inserted_rows' => $inserted,
                'restored_files' => $restored,
            ];
        });
    }

    /**
     * Run $callback while no other import or backup runs.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withMaintenanceLock(callable $callback): mixed
    {
        /** @var Lock $lock */
        $lock = Cache::lock(self::LOCK_NAME, self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw new DatabaseMaintenanceInProgress;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    public function audit(string $action, int $affected, ?string $actor): void
    {
        DB::table('system_maintenance_audits')->insert([
            'action' => mb_substr($action, 0, 50),
            'affected_count' => max(0, $affected),
            'changed_by' => $actor,
            'changed_at' => now(),
        ]);
    }

    /**
     * Private folder for backups; tests use a temp folder so they never touch real backups.
     * The test folder is per process so test runs started at the same time never share or empty it.
     */
    public function backupDirectory(): string
    {
        return app()->runningUnitTests()
            ? rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'lspdms-database-backups-tests-'.getmypid()
            : storage_path('app/private/database-backups');
    }

    /** @return array{files: array<string, array<string, mixed>>} */
    public function readIndex(): array
    {
        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.'.backup-index.json';
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return ['files' => is_array($decoded['files'] ?? null) ? $decoded['files'] : []];
    }

    public function writeIndex(array $index): void
    {
        $directory = $this->backupDirectory();
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        file_put_contents(
            $directory.DIRECTORY_SEPARATOR.'.backup-index.json',
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * Remember a backup's source, size and SHA-256 so later downloads can be
     * checked for damage or tampering.
     */
    public function recordBackup(string $name, string $source): void
    {
        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$name;
        $index = $this->readIndex();
        $index['files'][$name] = [
            'source' => $source,
            'created_at' => date(DATE_ATOM, (int) filemtime($path)),
            'size' => (int) filesize($path),
            'sha256' => (string) hash_file('sha256', $path),
        ];
        $this->writeIndex($index);
    }

    /**
     * true = matches the recorded SHA-256, false = changed since it was
     * recorded, null = no hash recorded (older backups).
     */
    public function backupIntact(string $name): ?bool
    {
        $entry = $this->readIndex()['files'][$name] ?? null;
        $expected = is_array($entry) ? ($entry['sha256'] ?? null) : null;
        if (! is_string($expected) || $expected === '') {
            return null;
        }

        $actual = @hash_file('sha256', $this->backupDirectory().DIRECTORY_SEPARATOR.$name);

        return is_string($actual) && hash_equals($expected, $actual);
    }

    /** Bytes free on the storage volume (a seam for tests). */
    public function freeDiskBytes(): ?float
    {
        $free = @disk_free_space(storage_path());

        return $free === false ? null : (float) $free;
    }

    private function assertEnoughSpace(int $uploadBytes): void
    {
        $free = $this->freeDiskBytes();
        $needed = 3 * max(0, $uploadBytes) + self::SPACE_MARGIN;
        if ($free !== null && $free < $needed) {
            throw ValidationException::withMessages([
                'sql_file' => 'There is not enough free disk space for the safety backup and the import. Free up space and try again.',
            ]);
        }
    }

    private function hasVerifiedHeadSecretary(): bool
    {
        return DB::table('login_user')
            ->where('is_verified', true)
            ->pluck('tungkulin')
            ->contains(fn (?string $role): bool => Role::isHeadSecretary($role));
    }

    /**
     * Whether a file copy at ($disk, $path) belongs to a FILE_OWNERS location:
     * same disk, owner prefix, allowed extension, and a plain relative path
     * (no "..", backslash, dot-file, control character or drive/stream colon).
     * A segment with a script extension anywhere in it ("shell.php.png") is
     * refused too, since some servers run those whatever the last extension.
     */
    public static function isAllowedFileLocation(?string $disk, ?string $path): bool
    {
        if ($disk === null || $path === null || $path === ''
            || str_contains($path, '\\') || str_contains($path, ':')
            || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')
                || preg_match('/\.(php\d*|phtml|phar|phps|pht|shtml|htaccess|htpasswd|cgi|pl|py|sh|asp|aspx|jsp)(\.|$)/i', $segment)) {
                return false;
            }
        }

        foreach (self::FILE_OWNERS as $location) {
            if ($disk !== $location['disk'] || ! str_starts_with($path, $location['prefix'])) {
                continue;
            }

            return $location['extensions'] === null
                || in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $location['extensions'], true);
        }

        return false;
    }

    /**
     * Reject a stored_files / stored_file_chunks INSERT whose (disk, path) is
     * not a FILE_OWNERS location, so a crafted backup cannot make the
     * post-import restore write files anywhere else (e.g. a .php file under
     * the web-served /storage folder).
     */
    private function assertAllowedFileRow(string $table, string $statement): void
    {
        if (! in_array($table, self::FILE_TABLES, true)) {
            return;
        }

        $row = $this->insertedValues($statement);
        if ($row === null || ! self::isAllowedFileLocation($row['disk'] ?? null, $row['path'] ?? null)) {
            throw ValidationException::withMessages([
                'sql_file' => 'The SQL backup contains a stored file outside the folders LSPDMS uses. Nothing was imported.',
            ]);
        }
    }

    /**
     * Column => decoded value of an INSERT that insertTable() already
     * accepted (hex literals or NULL only), or null when it cannot be read.
     * A repeated column is unreadable: SQLite keeps the first value while a
     * column => value map would keep the last, so the check could be fooled.
     *
     * @return array<string, ?string>|null
     */
    private function insertedValues(string $statement): ?array
    {
        if (! preg_match('/^INSERT INTO\s+\S+\s+\((.*?)\)\s+VALUES\s+\((.*)\);$/is', $statement, $parts)) {
            return null;
        }

        preg_match_all('/[A-Za-z0-9_]+/', $parts[1], $columns);
        preg_match_all("/NULL|X'([a-f0-9]*)'/i", $parts[2], $values, PREG_SET_ORDER);
        if (count($columns[0]) !== count($values)) {
            return null;
        }

        $names = array_map('strtolower', $columns[0]);
        if (count(array_unique($names)) !== count($names)) {
            return null;
        }

        $row = [];
        foreach ($names as $index => $column) {
            $hex = $values[$index][1] ?? null;
            if ($hex !== null && strlen($hex) % 2 !== 0) {
                return null;
            }
            $row[$column] = $hex === null ? null : (string) hex2bin($hex);
        }

        return $row;
    }

    /** Keep only file copies whose owning table is part of $tables. */
    private function limitToExportedOwners($query, array $tables): void
    {
        $query->where(function ($query) use ($tables): void {
            $owned = false;
            foreach (self::FILE_OWNERS as $owner => $location) {
                if (! in_array($owner, $tables, true)) {
                    continue;
                }
                $owned = true;
                $query->orWhere(fn ($query) => $query
                    ->where('disk', $location['disk'])
                    ->where('path', 'like', $location['prefix'].'%'));
            }
            if (! $owned) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    private function filePath(UploadedFile|string $file): string
    {
        return $file instanceof UploadedFile ? (string) $file->getRealPath() : $file;
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

    private function forEachInsert(string $path, string $driver, callable $callback): void
    {
        $stream = @fopen($path, 'rb');
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
                    $table = $this->insertTable($statement, $driver);
                    if ($table === null) {
                        throw ValidationException::withMessages([
                            'sql_file' => 'The SQL backup contains an invalid insert statement.',
                        ]);
                    }
                    if (in_array($table, self::NEVER_EXPORTED_TABLES, true)) {
                        continue;
                    }
                    // Checked again here: the file is re-read after inspectImport().
                    $this->assertAllowedFileRow($table, $statement);
                    $callback($statement);
                }
            }
        } finally {
            fclose($stream);
        }
    }

    private function assertValidTableName(string $table): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw ValidationException::withMessages([
                'sql_file' => 'The SQL backup contains an unsupported table name.',
            ]);
        }
    }
}
