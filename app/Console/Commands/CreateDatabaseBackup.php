<?php

namespace App\Console\Commands;

use App\Exceptions\DatabaseBackupFailed;
use App\Exceptions\DatabaseMaintenanceInProgress;
use App\Http\Controllers\DatabaseBackupController;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Writes an LSPDMS-format backup into the private backups folder, verifies it
 * can be read back, records its SHA-256 and writes an audit row. Scheduled
 * monthly in routes/console.php (needs "php artisan schedule:run" every
 * minute from Windows Task Scheduler, or "php artisan schedule:work").
 */
class CreateDatabaseBackup extends Command
{
    protected $signature = 'lspdms:backup:create';

    protected $description = 'Create a verified LSPDMS database backup in the private backups folder';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $path = $backups->withMaintenanceLock(
                fn (): string => $backups->createSafetyBackup(DatabaseBackupController::generateName(), 'auto')
            );
        } catch (DatabaseMaintenanceInProgress|DatabaseBackupFailed $exception) {
            // The scheduler discards console output, so failures go to laravel.log too.
            Log::error('LSPDMS automatic backup failed.', ['reason' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $backups->audit('backup_create_auto', 1, 'system');
        $this->info('Backup created: '.basename($path));

        return self::SUCCESS;
    }
}
