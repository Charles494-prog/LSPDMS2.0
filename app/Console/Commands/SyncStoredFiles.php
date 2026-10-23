<?php

namespace App\Console\Commands;

use App\Services\FileStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Copies every file the database refers to (profile pictures, chat images,
 * monthly snapshot documents and their previews) into stored_files, so SQL
 * exports carry them. Safe to run repeatedly: files already stored are
 * skipped. Missing files are only reported; no path is ever cleared.
 */
class SyncStoredFiles extends Command
{
    protected $signature = 'lspdms:files:sync
        {--dry-run : Report what would happen without writing anything}
        {--rehydrate : Also write database copies back to disk for files missing on disk}';

    protected $description = 'Store referenced upload files in the database and report missing ones';

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function handle(FileStore $files): int
    {
        if (! Schema::hasTable('stored_files')) {
            $this->error('The stored_files table does not exist. Run "php artisan migrate" first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $counts = ['stored' => 0, 'already_stored' => 0, 'missing' => 0, 'rehydrated' => 0];
        $missing = [];
        $seen = [];

        foreach ($this->references() as $reference) {
            $key = $reference['disk'].'|'.$reference['path'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $onDisk = Storage::disk($reference['disk'])->exists($reference['path']);
            if ($dryRun) {
                $result = $files->isPersisted($reference['disk'], $reference['path'])
                    ? 'already_stored'
                    : ($onDisk ? 'stored' : 'missing');
            } else {
                $result = $files->ingest($reference['disk'], $reference['path'], $reference['mime']);
            }
            $counts[$result]++;

            if ($result === 'missing') {
                $missing[] = $reference['label'];
            } elseif ($result === 'already_stored' && ! $onDisk && $this->option('rehydrate')) {
                // get() rewrites the disk file from the verified database copy.
                if ($dryRun || $files->get($reference['disk'], $reference['path']) !== null) {
                    $counts['rehydrated']++;
                }
            }
        }

        $this->info(($dryRun ? '[dry run] ' : '').'Referenced files checked: '.count($seen));
        $this->line(($dryRun ? 'Would store' : 'Stored').': '.$counts['stored']);
        $this->line('Already stored: '.$counts['already_stored']);
        $this->line('Missing (not on disk and not in the database): '.$counts['missing']);
        if ($this->option('rehydrate')) {
            $this->line(($dryRun ? 'Would restore to disk' : 'Restored to disk').': '.$counts['rehydrated']);
        }
        foreach ($missing as $label) {
            $this->warn('  missing: '.$label);
        }

        return self::SUCCESS;
    }

    /**
     * @return iterable<int, array{disk: string, path: string, mime: ?string, label: string}>
     */
    private function references(): iterable
    {
        if (Schema::hasTable('login_user') && Schema::hasColumn('login_user', 'profile_picture')) {
            foreach (DB::table('login_user')->whereNotNull('profile_picture')->where('profile_picture', '!=', '')
                ->orderBy('id')->get(['id', 'profile_picture']) as $user) {
                yield [
                    'disk' => 'public',
                    'path' => (string) $user->profile_picture,
                    'mime' => null,
                    'label' => 'login_user #'.$user->id.' profile picture',
                ];
            }
        }

        if (Schema::hasTable('messages')) {
            foreach (DB::table('messages')->whereNotNull('attachment_path')->where('attachment_path', '!=', '')
                ->orderBy('id')->get(['id', 'attachment_path', 'attachment_mime']) as $message) {
                yield [
                    'disk' => 'local',
                    'path' => (string) $message->attachment_path,
                    'mime' => $message->attachment_mime ?: null,
                    'label' => 'messages #'.$message->id.' attachment',
                ];
            }
        }

        if (Schema::hasTable('r4_01_snapshots')) {
            foreach (DB::table('r4_01_snapshots')->whereNotNull('file_path')->where('file_path', '!=', '')
                ->orderBy('id')->get(['id', 'file_path', 'format']) as $snapshot) {
                $path = (string) $snapshot->file_path;
                yield [
                    'disk' => 'local',
                    'path' => $path,
                    'mime' => $snapshot->format === 'docx' ? self::DOCX_MIME : null,
                    'label' => 'r4_01_snapshots #'.$snapshot->id.' document',
                ];
                $previewPath = preg_replace('/\.docx$/i', '.html', $path);
                if (is_string($previewPath) && $previewPath !== $path) {
                    yield [
                        'disk' => 'local',
                        'path' => $previewPath,
                        'mime' => 'text/html; charset=UTF-8',
                        'label' => 'r4_01_snapshots #'.$snapshot->id.' preview',
                    ];
                }
            }
        }
    }
}
