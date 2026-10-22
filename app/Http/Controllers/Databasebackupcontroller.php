<?php

namespace App\Http\Controllers;

use DirectoryIterator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Lists and downloads the safety backups created before a database import.
 *
 * IMPORTANT: directory() must point at the folder where your existing import
 * code writes the safety backup (the file name returned as `backup_file` by the
 * import endpoint). Find it with:
 *     grep -rn "backup_file" app/
 * then copy that path here. Nothing else in this class needs to change.
 */
class DatabaseBackupController extends Controller
{
    private const EXTENSIONS = ['sql', 'gz', 'zip'];

    private function directory(): string
    {
        return storage_path('app/private/backups'); // <-- CONFIRM against your import code
    }

    /** @return array<int, array{name: string, size: int, created_at: string}> */
    private function files(): array
    {
        $directory = $this->directory();
        if (! is_dir($directory)) {
            return [];
        }

        $items = [];
        foreach (new DirectoryIterator($directory) as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }
            if (! in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                continue;
            }
            $items[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date(DATE_ATOM, $file->getMTime()),
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $items;
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->files()])
            ->header('Cache-Control', 'no-store');
    }

    public function download(string $file): BinaryFileResponse
    {
        // Only files that appear in the listing can be downloaded (blocks path traversal).
        $match = collect($this->files())->firstWhere('name', $file);
        abort_if($match === null, 404);

        return response()->download(
            $this->directory().DIRECTORY_SEPARATOR.$match['name'],
            $match['name'],
            ['Cache-Control' => 'no-store']
        );
    }
}

/*
 * ROUTES — add inside the SAME route group/middleware as the existing
 * superadmin.database.* routes (Head Secretary / superadmin access only):
 *
 * use App\Http\Controllers\DatabaseBackupController;
 *
 * Route::get('/superadmin/database/backups', [DatabaseBackupController::class, 'index'])
 *     ->name('superadmin.database.backups');
 * Route::get('/superadmin/database/backups/{file}', [DatabaseBackupController::class, 'download'])
 *     ->where('file', '[A-Za-z0-9._-]+')
 *     ->name('superadmin.database.backups.download');
 */