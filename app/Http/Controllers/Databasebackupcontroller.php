<?php

namespace App\Http\Controllers;

use DirectoryIterator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Database backups: list, download, rename, add a manual backup, and
 * automatically delete backups older than one year.
 *
 * Each backup has a source:
 *   import - the safety backup made right before a database import
 *            (recognised by the "before-import-" file name prefix)
 *   auto   - the monthly automatic backup (any other file not added by hand)
 *   manual - an export a Head Secretary added from the Database backups page
 *
 * A small index file (.backup-index.json) in the same folder remembers the
 * source of manual backups and of any backup that has been renamed.
 */
class DatabaseBackupController extends Controller
{
    private const EXTENSIONS = ['sql', 'gz', 'zip'];

    private const RETENTION_DAYS = 365;

    /** Tables a manually added backup must contain. */
    private const REQUIRED_TABLES = ['login_user', 'messages'];

    private const INDEX_FILE = '.backup-index.json';

    public static function backupDirectory(): string
    {
        return storage_path('app/private/database-backups'); // same folder the import writes its safety backup to
    }

    /**
     * Name for a new backup: 2026-10-23_15-42-10_LSPDMS.sql
     * Call this from your import code too so automatic backups use the same pattern.
     */
    public static function generateName(): string
    {
        $directory = self::backupDirectory();
        $base = now('Asia/Manila')->format('Y-m-d_H-i-s').'_LSPDMS';
        $name = $base.'.sql';
        for ($i = 2; file_exists($directory.DIRECTORY_SEPARATOR.$name); $i++) {
            $name = $base.'_'.$i.'.sql';
        }

        return $name;
    }

    private function ensureDirectory(): string
    {
        $directory = self::backupDirectory();
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        return $directory;
    }

    /** @return array{files: array<string, array{source: string, created_at: string}>} */
    private function readIndex(): array
    {
        $path = self::backupDirectory().DIRECTORY_SEPARATOR.self::INDEX_FILE;
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return ['files' => is_array($decoded['files'] ?? null) ? $decoded['files'] : []];
    }

    private function writeIndex(array $index): void
    {
        $path = $this->ensureDirectory().DIRECTORY_SEPARATOR.self::INDEX_FILE;
        file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function classify(string $name, ?array $entry): string
    {
        $source = $entry['source'] ?? null;
        if (in_array($source, ['import', 'auto', 'manual'], true)) {
            return $source;
        }

        return str_starts_with($name, 'before-import-') ? 'import' : 'auto';
    }

    /** @return array<int, array{name: string, size: int, created_at: string, source: string}> newest first */
    private function files(): array
    {
        $directory = self::backupDirectory();
        if (! is_dir($directory)) {
            return [];
        }

        $index = $this->readIndex()['files'];
        $items = [];
        foreach (new DirectoryIterator($directory) as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }
            if (! in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                continue;
            }
            $entry = $index[$file->getFilename()] ?? null;
            $items[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => $entry['created_at'] ?? date(DATE_ATOM, $file->getMTime()),
                'source' => $this->classify($file->getFilename(), $entry),
            ];
        }

        usort($items, fn ($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        return $items;
    }

    /**
     * Deletes backups older than the retention period. The newest backup is
     * never deleted, so there is always at least one copy.
     */
    private function prune(): int
    {
        $files = $this->files();
        $cutoff = now()->subDays(self::RETENTION_DAYS)->getTimestamp();
        $index = $this->readIndex();
        $deleted = 0;

        foreach (array_slice($files, 1) as $file) {
            if (strtotime($file['created_at']) >= $cutoff) {
                continue;
            }
            $path = self::backupDirectory().DIRECTORY_SEPARATOR.$file['name'];
            if (is_file($path) && @unlink($path)) {
                unset($index['files'][$file['name']]);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->writeIndex($index);
        }

        return $deleted;
    }

    /** @return array<int, string> lower-case table names found in an SQL export */
    private function tablesIn(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $tables = [];
        $pattern = '/^\s*(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO)\s+(?:[`"]?[A-Za-z0-9_$]+[`"]?\.)?[`"]?([A-Za-z0-9_$]+)[`"]?/i';
        while (($line = fgets($handle, 65536)) !== false) {
            if (preg_match($pattern, $line, $match)) {
                $tables[strtolower($match[1])] = true;
            }
        }
        fclose($handle);

        return array_keys($tables);
    }

    public function index(): JsonResponse
    {
        $pruned = $this->prune();

        return response()->json([
            'data' => $this->files(),
            'pruned' => $pruned,
            'retention_days' => self::RETENTION_DAYS,
        ])->header('Cache-Control', 'no-store');
    }

    public function download(string $file): BinaryFileResponse
    {
        // Only files that appear in the listing can be downloaded (blocks path traversal).
        $match = collect($this->files())->firstWhere('name', $file);
        abort_if($match === null, 404);

        return response()->download(
            self::backupDirectory().DIRECTORY_SEPARATOR.$match['name'],
            $match['name'],
            ['Cache-Control' => 'no-store']
        );
    }

    /** Saves an SQL export the user made themselves as a "manual" backup. It is NOT imported. */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:102400']]);
        $upload = $request->file('file');

        if (strtolower($upload->getClientOriginalExtension()) !== 'sql') {
            throw ValidationException::withMessages(['file' => 'Choose an .sql export file.']);
        }

        $tables = $this->tablesIn($upload->getRealPath());
        if ($tables === []) {
            throw ValidationException::withMessages(['file' => 'This file does not look like a database export: no tables were found.']);
        }

        $missing = array_values(array_diff(self::REQUIRED_TABLES, $tables));
        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => 'This export cannot be saved as a backup because it is missing: '.implode(', ', $missing).'. Export again without excluding login_user or messages.']);
        }

        $directory = $this->ensureDirectory();
        $name = self::generateName();
        $upload->move($directory, $name);
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        touch($path);

        $created = date(DATE_ATOM, filemtime($path));
        $index = $this->readIndex();
        $index['files'][$name] = ['source' => 'manual', 'created_at' => $created];
        $this->writeIndex($index);

        return response()->json([
            'message' => 'Manual backup saved.',
            'data' => ['name' => $name, 'size' => filesize($path), 'created_at' => $created, 'source' => 'manual'],
        ], 201);
    }

    /** Renames a backup. Only the base name changes; the extension is kept. */
    public function rename(Request $request, string $file): JsonResponse
    {
        $match = collect($this->files())->firstWhere('name', $file);
        abort_if($match === null, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', function ($attribute, $value, $fail) {
                if (str_contains($value, '..')) {
                    $fail('The name cannot contain "..".');
                }
            }],
        ], [
            'name.regex' => 'Use only letters, numbers, dots, dashes and underscores (no spaces).',
        ]);

        $extension = pathinfo($match['name'], PATHINFO_EXTENSION);
        $newName = $validated['name'].'.'.$extension;
        $directory = self::backupDirectory();

        if (strcasecmp($newName, $match['name']) !== 0) {
            foreach ($this->files() as $existing) {
                if (strcasecmp($existing['name'], $newName) === 0) {
                    throw ValidationException::withMessages(['name' => 'A backup with this name already exists.']);
                }
            }
            if (! rename($directory.DIRECTORY_SEPARATOR.$match['name'], $directory.DIRECTORY_SEPARATOR.$newName)) {
                throw ValidationException::withMessages(['name' => 'The backup could not be renamed.']);
            }
        }

        $index = $this->readIndex();
        unset($index['files'][$match['name']]);
        $index['files'][$newName] = ['source' => $match['source'], 'created_at' => $match['created_at']];
        $this->writeIndex($index);

        return response()->json([
            'message' => 'Backup renamed.',
            'data' => ['name' => $newName, 'size' => $match['size'], 'created_at' => $match['created_at'], 'source' => $match['source']],
        ]);
    }
}