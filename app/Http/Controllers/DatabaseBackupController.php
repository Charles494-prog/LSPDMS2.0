<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use DirectoryIterator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Database backups: list, download, rename and add a manual backup. Backups
 * are never deleted automatically (no retention policy has been approved).
 *
 * Each backup has a source:
 *   import - the safety backup made right before a database import
 *            (recognised by the "before-import-" file name prefix)
 *   auto   - the monthly automatic backup (lspdms:backup:create), or any
 *            other file not added by hand
 *   manual - an export a Head Secretary added from the Database backups page
 *
 * A small index file (.backup-index.json) in the same folder remembers the
 * source, size and SHA-256 of each backup the application wrote or accepted.
 */
class DatabaseBackupController extends Controller
{
    private const EXTENSIONS = ['sql', 'gz', 'zip'];

    /** Tables a manually added backup must contain. */
    private const REQUIRED_TABLES = ['login_user', 'messages'];

    public function __construct(private readonly DatabaseBackupService $backups) {}

    public static function backupDirectory(): string
    {
        return app(DatabaseBackupService::class)->backupDirectory();
    }

    /** Name for a new backup: 2026-10-23_15-42-10_LSPDMS.sql */
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

    private function classify(string $name, ?array $entry): string
    {
        $source = $entry['source'] ?? null;
        if (in_array($source, ['import', 'auto', 'manual'], true)) {
            return $source;
        }

        return str_starts_with($name, 'before-import-') ? 'import' : 'auto';
    }

    /** @return array<int, array{name: string, size: int, created_at: string, source: string, verified: ?bool}> newest first */
    private function files(bool $withIntegrity = false): array
    {
        $directory = self::backupDirectory();
        if (! is_dir($directory)) {
            return [];
        }

        $index = $this->backups->readIndex()['files'];
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
                'verified' => $withIntegrity ? $this->backups->backupIntact($file->getFilename()) : null,
            ];
        }

        usort($items, fn ($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        return $items;
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->files(true),
            'pruned' => 0,
            'retention_days' => null,
        ])->header('Cache-Control', 'no-store');
    }

    public function download(Request $request, string $file): BinaryFileResponse|JsonResponse
    {
        // Only files that appear in the listing can be downloaded (blocks path traversal).
        $match = collect($this->files())->firstWhere('name', $file);
        abort_if($match === null, 404);

        if ($this->backups->backupIntact($match['name']) === false) {
            return response()->json([
                'message' => 'This backup changed after it was saved (its SHA-256 no longer matches), so it was not downloaded.',
            ], 409);
        }
        $this->backups->audit('backup_download', 1, $this->actor($request));

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

        // The same parser the import uses, so a stored backup is known to be importable.
        try {
            $tables = $this->backups->inspectImport($upload->getRealPath())['tables'];
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'file' => 'This file is not a valid LSPDMS SQL export: '.collect($exception->errors())->flatten()->first(),
            ]);
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

        $this->backups->recordBackup($name, 'manual');
        $this->backups->audit('backup_store', 1, $this->actor($request));
        $entry = $this->backups->readIndex()['files'][$name];

        return response()->json([
            'message' => 'Manual backup saved.',
            'data' => ['name' => $name, 'size' => filesize($path), 'created_at' => $entry['created_at'], 'source' => 'manual', 'verified' => true],
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

        $index = $this->backups->readIndex();
        $previous = $index['files'][$match['name']] ?? [];
        unset($index['files'][$match['name']]);
        // Keep the recorded size and SHA-256: renaming does not change the contents.
        $index['files'][$newName] = array_merge($previous, ['source' => $match['source'], 'created_at' => $match['created_at']]);
        $this->backups->writeIndex($index);
        $this->backups->audit('backup_rename', 1, $this->actor($request));

        return response()->json([
            'message' => 'Backup renamed.',
            'data' => ['name' => $newName, 'size' => $match['size'], 'created_at' => $match['created_at'], 'source' => $match['source']],
        ]);
    }

    private function actor(Request $request): ?string
    {
        $gmail = trim((string) $request->session()->get('login_user.gmail', ''));

        return $gmail !== '' ? $gmail : null;
    }
}
