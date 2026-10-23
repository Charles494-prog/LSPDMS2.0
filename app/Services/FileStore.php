<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps uploaded files in two places: the filesystem disk (fast reads) and the
 * stored_files / stored_file_chunks tables (so SQL exports, backups and
 * imports carry the file contents). Reads fall back to the database copy and
 * rewrite the disk file when it is missing, for example after a restore.
 */
class FileStore
{
    /**
     * Raw bytes per chunk. Base64 makes it 256KB, and the SQL exporter
     * hex-encodes that to 512KB, which keeps one INSERT under 1MB.
     */
    public const CHUNK_BYTES = 196608;

    private ?bool $tablesExist = null;

    public function put(string $disk, string $path, string $contents, ?string $mime = null): void
    {
        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('Unable to write the file to storage.');
        }

        $this->persist($disk, $path, $contents, $mime);
    }

    /**
     * Store an upload under $directory with a random name and return its path.
     */
    public function putUploadedFile(string $disk, string $directory, UploadedFile $file): string
    {
        $path = $file->store($directory, $disk);
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to write the uploaded file to storage.');
        }

        try {
            $this->persist($disk, $path, (string) file_get_contents($file->getRealPath()), $file->getMimeType());
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        return $path;
    }

    /**
     * Save the database copy of a file. Replaces any previous copy for the path.
     */
    public function persist(string $disk, string $path, string $contents, ?string $mime = null): void
    {
        $chunks = $contents === '' ? [''] : str_split($contents, self::CHUNK_BYTES);

        DB::transaction(function () use ($disk, $path, $contents, $mime, $chunks): void {
            $this->deleteRows($disk, $path);
            DB::table('stored_files')->insert([
                'disk' => $disk,
                'path' => $path,
                'mime' => $mime,
                'size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'chunk_count' => count($chunks),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($chunks as $index => $chunk) {
                DB::table('stored_file_chunks')->insert([
                    'disk' => $disk,
                    'path' => $path,
                    'chunk_index' => $index,
                    'data' => base64_encode($chunk),
                ]);
            }
        });
    }

    /**
     * File contents, or null when neither the disk nor the database has them.
     * A missing or damaged disk file is rewritten from the database copy.
     */
    public function get(string $disk, string $path): ?string
    {
        $record = $this->record($disk, $path);
        $storage = Storage::disk($disk);
        if ($storage->exists($path)) {
            $contents = (string) $storage->get($path);
            if ($record === null || hash_equals($record->sha256, hash('sha256', $contents))) {
                return $contents;
            }
        }

        if ($record === null) {
            return null;
        }

        $contents = $this->databaseContents($record);
        if ($contents === null) {
            return null;
        }
        $storage->put($path, $contents);

        return $contents;
    }

    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path) || $this->record($disk, $path) !== null;
    }

    public function isPersisted(string $disk, string $path): bool
    {
        return $this->record($disk, $path) !== null;
    }

    public function mime(string $disk, string $path): ?string
    {
        return $this->record($disk, $path)?->mime;
    }

    public function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
        if ($this->tablesExist()) {
            DB::transaction(fn () => $this->deleteRows($disk, $path));
        }
    }

    /**
     * Write every database copy that is missing (or damaged) on disk back to
     * its disk, for example after an import. Returns how many were restored.
     */
    public function rehydrateMissing(): int
    {
        if (! $this->tablesExist()) {
            return 0;
        }

        $restored = 0;
        foreach (DB::table('stored_files')->orderBy('id')->get() as $record) {
            $storage = Storage::disk($record->disk);
            if ($storage->exists($record->path)
                && hash_equals($record->sha256, hash('sha256', (string) $storage->get($record->path)))) {
                continue;
            }
            $contents = $this->databaseContents($record);
            if ($contents !== null && $storage->put($record->path, $contents)) {
                $restored++;
            }
        }

        return $restored;
    }

    private function deleteRows(string $disk, string $path): void
    {
        DB::table('stored_file_chunks')->where('disk', $disk)->where('path', $path)->delete();
        DB::table('stored_files')->where('disk', $disk)->where('path', $path)->delete();
    }

    /**
     * An inline or attachment response for the file, or null when it is missing.
     */
    public function response(string $disk, string $path, string $name, ?string $mime = null, string $disposition = 'inline'): ?Response
    {
        $contents = $this->get($disk, $path);
        if ($contents === null) {
            return null;
        }

        $safeName = str_replace(['"', '\\', "\r", "\n"], '', $name) ?: 'file';

        return response($contents, 200, [
            'Content-Type' => $mime ?: ($this->mime($disk, $path) ?: 'application/octet-stream'),
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => $disposition.'; filename="'.$safeName.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Copy an existing disk file into the database if it is not there yet.
     * Returns 'stored', 'already_stored' or 'missing'.
     */
    public function ingest(string $disk, string $path, ?string $mime = null): string
    {
        if ($this->record($disk, $path) !== null) {
            return 'already_stored';
        }
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return 'missing';
        }

        $this->persist($disk, $path, (string) $storage->get($path), $mime ?? $storage->mimeType($path) ?: null);

        return 'stored';
    }

    private function record(string $disk, string $path): ?object
    {
        if (! $this->tablesExist()) {
            return null;
        }

        return DB::table('stored_files')->where('disk', $disk)->where('path', $path)->first();
    }

    private function databaseContents(object $record): ?string
    {
        $chunks = DB::table('stored_file_chunks')
            ->where('disk', $record->disk)
            ->where('path', $record->path)
            ->orderBy('chunk_index')
            ->pluck('data');
        if ($chunks->count() !== (int) $record->chunk_count) {
            return null;
        }

        $contents = '';
        foreach ($chunks as $chunk) {
            $decoded = base64_decode((string) $chunk, true);
            if ($decoded === false) {
                return null;
            }
            $contents .= $decoded;
        }

        return hash_equals($record->sha256, hash('sha256', $contents)) ? $contents : null;
    }

    private function tablesExist(): bool
    {
        return $this->tablesExist ??= Schema::hasTable('stored_files');
    }
}
