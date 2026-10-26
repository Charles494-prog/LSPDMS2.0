<?php

namespace Tests;

use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\ExportTempFiles;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static bool $processCleanupRegistered = false;

    protected function setUp(): void
    {
        // Storage::fake() appends "_test_<token>" to its root only when a parallel-testing token exists.
        // A per-process token keeps test runs started at the same time out of each other's fake disks.
        $_SERVER['TEST_TOKEN'] ??= 'p'.getmypid();

        parent::setUp();

        $this->registerProcessCleanup();
    }

    /** Removes this process's temp export/backup folders and fake disks once the whole run ends. */
    private function registerProcessCleanup(): void
    {
        if (self::$processCleanupRegistered) {
            return;
        }
        self::$processCleanupRegistered = true;

        // Both folders end in this process id (see ExportTempFiles and DatabaseBackupService).
        $directories = [ExportTempFiles::directory(), app(DatabaseBackupService::class)->backupDirectory()];
        // Fake disks are only ours to remove when the token is the per-process one set above.
        $fakeDiskPattern = $_SERVER['TEST_TOKEN'] === 'p'.getmypid()
            ? storage_path('framework/testing/disks').'/*_test_'.$_SERVER['TEST_TOKEN']
            : null;

        register_shutdown_function(static function () use ($directories, $fakeDiskPattern): void {
            $fakeDisks = $fakeDiskPattern !== null ? (glob($fakeDiskPattern, GLOB_ONLYDIR) ?: []) : [];
            $files = new Filesystem;
            foreach ([...$directories, ...$fakeDisks] as $directory) {
                $files->deleteDirectory($directory);
            }
        });
    }

    public function withSession(array $data): static
    {
        $sessionUser = $data['login_user'] ?? null;
        if (is_array($sessionUser) && isset($sessionUser['id']) && ! User::query()->whereKey($sessionUser['id'])->exists()) {
            $user = new User;
            $user->forceFill([
                'id' => (int) $sessionUser['id'],
                'gmail' => $sessionUser['gmail'] ?? 'session-'.$sessionUser['id'].'@example.test',
                'password' => 'test-session-password',
                'tungkulin' => $sessionUser['tungkulin'] ?? 'Head Secretary',
                'is_verified' => true,
                'first_name' => $sessionUser['first_name'] ?? 'Test',
                'last_name' => $sessionUser['last_name'] ?? 'Session',
                'dashboard_allow' => true,
                'form_generator_allow' => true,
                'Delistment_allow' => true,
                'Inquire_Allow' => true,
            ])->save();
        }

        return parent::withSession($data);
    }
}
