<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MonthlyMovementSession;
use App\Models\R401Snapshot;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\FileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyBackupDirectory();
    }

    protected function tearDown(): void
    {
        $this->emptyBackupDirectory();
        parent::tearDown();
    }

    /** Empties (not removes) the temp backup folder: removing it can lag on Windows. */
    private function emptyBackupDirectory(): void
    {
        $directory = app(DatabaseBackupService::class)->backupDirectory();
        if (is_dir($directory)) {
            File::cleanDirectory($directory);
        }
    }

    public function test_export_carries_images_but_never_secrets_sessions_or_remember_tokens(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $head = $this->makeHead(['remember_token' => 'remember-hash-value', 'remember_token_expires_at' => now()->addDay()]);
        app(FileStore::class)->put('public', 'profile-pictures/head.png', 'PNG-BYTES-HEAD', 'image/png');
        app(FileStore::class)->put('local', 'messages/1/chat.png', 'PNG-BYTES-CHAT', 'image/png');
        DB::table('sessions')->insert(['id' => 'session-id-secret', 'payload' => 'payload', 'last_activity' => 1]);
        DB::table('password_reset_tokens')->insert(['email' => 'x@example.test', 'token' => 'reset-secret', 'created_at' => now()]);

        $sql = $this->export($head);

        $this->assertStringContainsString('INSERT INTO "stored_file_chunks"', $sql);
        $this->assertStringContainsString(bin2hex(base64_encode('PNG-BYTES-HEAD')), $sql);
        $this->assertStringContainsString(bin2hex(base64_encode('PNG-BYTES-CHAT')), $sql);
        foreach (['migrations', 'sessions', 'system_smtp_settings', 'password_reset_tokens', 'it_recovery_accounts', 'it_recovery_audit_logs', 'cache', 'jobs'] as $table) {
            $this->assertStringNotContainsString('CREATE TABLE "'.$table.'"', $sql);
        }
        $this->assertStringNotContainsString(bin2hex('session-id-secret'), $sql);
        $this->assertStringNotContainsString(bin2hex('remember-hash-value'), $sql);
        $this->assertStringContainsString('INSERT INTO "login_user"', $sql);
    }

    public function test_export_without_messages_carries_no_chat_images(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $head = $this->makeHead();
        app(FileStore::class)->put('public', 'profile-pictures/head.png', 'PNG-BYTES-HEAD', 'image/png');
        app(FileStore::class)->put('local', 'messages/1/chat.png', 'PNG-BYTES-CHAT', 'image/png');

        $sql = $this->export($head, ['exclude_tables' => ['messages']]);

        $this->assertStringContainsString(bin2hex(base64_encode('PNG-BYTES-HEAD')), $sql);
        $this->assertStringNotContainsString(bin2hex(base64_encode('PNG-BYTES-CHAT')), $sql);
        $this->assertStringNotContainsString(bin2hex('messages/1/chat.png'), $sql);
    }

    public function test_every_exported_insert_fits_the_default_mariadb_packet(): void
    {
        Storage::fake('local');
        $head = $this->makeHead();
        app(FileStore::class)->put('local', 'messages/1/big.png', random_bytes(FileStore::CHUNK_BYTES * 2), 'image/png');

        foreach (explode("\n", $this->export($head)) as $line) {
            $this->assertLessThan(1048576, strlen($line));
        }
    }

    public function test_round_trip_restores_images_on_a_new_pc(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $head = $this->makeHead();
        $other = User::query()->create(['gmail' => 'other@example.test', 'password' => 'secret-password', 'tungkulin' => 'Secretary', 'is_verified' => true]);
        $conversation = Conversation::query()->create(['user_one_id' => min($head->id, $other->id), 'user_two_id' => max($head->id, $other->id)]);
        app(FileStore::class)->put('local', 'messages/'.$conversation->id.'/chat.png', 'PNG-BYTES-CHAT', 'image/png');
        Message::query()->create([
            'conversation_id' => $conversation->id, 'sender_id' => $head->id, 'attachment_path' => 'messages/'.$conversation->id.'/chat.png',
            'attachment_name' => 'chat.png', 'attachment_mime' => 'image/png', 'attachment_size' => 14, 'expires_at' => now()->addYear(),
        ]);
        $sql = $this->export($head);

        // A new PC: the files are gone from disk.
        Storage::fake('local');
        $response = $this->withSession(['login_user' => ['id' => $head->id]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => UploadedFile::fake()->createWithContent('lspdms.sql', $sql),
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, $response->json('restored_files'));
        Storage::disk('local')->assertExists('messages/'.$conversation->id.'/chat.png');
        $this->assertSame('PNG-BYTES-CHAT', Storage::disk('local')->get('messages/'.$conversation->id.'/chat.png'));
    }

    public function test_import_is_rolled_back_when_it_would_remove_every_head_secretary(): void
    {
        $head = $this->makeHead();
        $sql = $this->export($head);
        DB::table('login_user')->where('id', $head->id)->update(['is_verified' => false]);
        $withoutHead = $this->export($head, [], false);
        DB::table('login_user')->where('id', $head->id)->update(['is_verified' => true]);
        DB::table('records')->insert(['full_name' => 'LIVE RECORD', 'status' => 'MS']);

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => UploadedFile::fake()->createWithContent('lspdms.sql', $withoutHead),
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sql_file');

        $this->assertDatabaseHas('records', ['full_name' => 'LIVE RECORD']);
        $this->assertTrue((bool) $head->fresh()->is_verified);
        $this->assertNotSame('', $sql);
    }

    public function test_concurrent_import_is_refused_while_another_maintenance_task_runs(): void
    {
        $head = $this->makeHead();
        $lock = Cache::lock('lspdms:database-maintenance', 60);
        $this->assertTrue($lock->get());

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => UploadedFile::fake()->createWithContent('lspdms.sql', $this->export($head)),
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertStatus(423);
        $lock->release();
    }

    public function test_import_is_refused_when_disk_space_is_insufficient(): void
    {
        $head = $this->makeHead();
        $sql = $this->export($head);
        $this->app->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            public function freeDiskBytes(): ?float
            {
                return 1024.0;
            }
        });

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => UploadedFile::fake()->createWithContent('lspdms.sql', $sql),
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sql_file');
        $this->assertSame([], glob(app(DatabaseBackupService::class)->backupDirectory().'/*.sql') ?: []);
    }

    public function test_safety_backup_is_verified_and_recorded_with_its_hash(): void
    {
        $this->makeHead();
        $service = app(DatabaseBackupService::class);

        $path = $service->createSafetyBackup();

        $entry = $service->readIndex()['files'][basename($path)];
        $this->assertSame(hash_file('sha256', $path), $entry['sha256']);
        $this->assertTrue($service->backupIntact(basename($path)));
        file_put_contents($path, "\n-- tampered", FILE_APPEND);
        $this->assertFalse($service->backupIntact(basename($path)));
    }

    public function test_tampered_backup_is_not_downloaded_and_downloads_are_audited(): void
    {
        $head = $this->makeHead();
        $service = app(DatabaseBackupService::class);
        $path = $service->createSafetyBackup();

        $this->withSession(['login_user' => ['id' => $head->id, 'gmail' => $head->gmail]])
            ->get(route('superadmin.database.backups.download', basename($path)))
            ->assertOk();
        $this->assertDatabaseHas('system_maintenance_audits', ['action' => 'backup_download']);

        file_put_contents($path, "\n-- tampered", FILE_APPEND);
        $this->withSession(['login_user' => ['id' => $head->id]])
            ->get(route('superadmin.database.backups.download', basename($path)))
            ->assertStatus(409);
    }

    public function test_backups_are_not_deleted_automatically(): void
    {
        $head = $this->makeHead();
        $service = app(DatabaseBackupService::class);
        $path = $service->createSafetyBackup();
        touch($path, now()->subYears(3)->getTimestamp());
        $index = $service->readIndex();
        $index['files'][basename($path)]['created_at'] = now()->subYears(3)->toAtomString();
        $service->writeIndex($index);
        $service->createSafetyBackup();

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->getJson(route('superadmin.database.backups'))
            ->assertOk()
            ->assertJsonPath('pruned', 0)
            ->assertJsonCount(2, 'data');
        $this->assertFileExists($path);
    }

    public function test_manual_backup_must_be_an_lspdms_export(): void
    {
        $head = $this->makeHead();

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->postJson(route('superadmin.database.backups.store'), [
                'file' => UploadedFile::fake()->createWithContent('dump.sql', "CREATE TABLE login_user (id int);\nCREATE TABLE messages (id int);\n"),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->postJson(route('superadmin.database.backups.store'), [
                'file' => UploadedFile::fake()->createWithContent('lspdms.sql', $this->export($head)),
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'manual');
    }

    public function test_xlsx_export_never_includes_password_or_protected_tables(): void
    {
        $head = $this->makeHead();

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->post(route('superadmin.database.export-xlsx'), ['tables' => ['system_smtp_settings']])
            ->assertSessionHasErrors('tables.0');

        $response = $this->withSession(['login_user' => ['id' => $head->id]])
            ->post(route('superadmin.database.export-xlsx'), ['tables' => ['login_user']])
            ->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('head@example.test', $sheet);
        $this->assertStringNotContainsString('<t>password</t>', $sheet);
        $this->assertStringNotContainsString('remember_token', $sheet);
    }

    public function test_sync_command_stores_referenced_files_and_reports_missing_ones_without_changing_paths(): void
    {
        Storage::fake('public');
        $head = $this->makeHead(['profile_picture' => 'profile-pictures/present.png']);
        $other = User::query()->create([
            'gmail' => 'other@example.test', 'password' => 'secret-password', 'tungkulin' => 'Secretary',
            'is_verified' => true, 'profile_picture' => 'profile-pictures/missing.png',
        ]);
        Storage::disk('public')->put('profile-pictures/present.png', 'PNG');

        $this->artisan('lspdms:files:sync', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('stored_files')->count());

        $this->artisan('lspdms:files:sync')
            ->expectsOutputToContain('Stored: 1')
            ->expectsOutputToContain('login_user #'.$other->id.' profile picture')
            ->assertSuccessful();
        $this->assertTrue(app(FileStore::class)->isPersisted('public', 'profile-pictures/present.png'));
        $this->assertSame('profile-pictures/missing.png', $other->fresh()->profile_picture);
        $this->assertSame('profile-pictures/present.png', $head->fresh()->profile_picture);
    }

    public function test_backup_command_creates_a_verified_backup(): void
    {
        $this->makeHead();

        $exit = Artisan::call('lspdms:backup:create');
        $this->assertSame(0, $exit, Artisan::output());

        $files = glob(app(DatabaseBackupService::class)->backupDirectory().'/*_LSPDMS*.sql') ?: [];
        $this->assertCount(1, $files);
        $this->assertSame('auto', app(DatabaseBackupService::class)->readIndex()['files'][basename($files[0])]['source']);
        $this->assertDatabaseHas('system_maintenance_audits', ['action' => 'backup_create_auto']);
    }

    public function test_missing_snapshot_file_gives_a_clear_answer_and_db_copy_is_used_when_present(): void
    {
        Storage::fake('local');
        $head = $this->makeHead();
        $session = MonthlyMovementSession::query()->create([
            'status' => 'completed', 'period_month' => '2026-10-01', 'started_at' => now(),
        ]);
        $snapshot = R401Snapshot::query()->create([
            'session_id' => $session->id, 'period_month' => '2026-10-01', 'report_type' => 'Monthly session',
            'kalagayan' => '', 'purok_selection' => 'All Puroks', 'format' => 'docx',
            'file_path' => 'monthly-session-snapshots/2026-10/session-'.$session->id.'.docx', 'file_name' => 'Monthly.docx',
        ]);

        $this->withSession(['login_user' => ['id' => $head->id]])
            ->get(route('forms.monthly.r4-01.snapshots.view', $snapshot))
            ->assertNotFound()
            ->assertSee('The saved file for this snapshot is not on this server.');
        $this->withSession(['login_user' => ['id' => $head->id]])
            ->get(route('forms.monthly.r4-01.snapshots.download', $snapshot))
            ->assertRedirect(route('forms.monthly.r4-01.snapshots'))
            ->assertSessionHas('error');

        app(FileStore::class)->persist('local', $snapshot->file_path, 'DOCX-BYTES', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        app(FileStore::class)->persist('local', str_replace('.docx', '.html', $snapshot->file_path), '<p>preview</p>', 'text/html');
        $this->withSession(['login_user' => ['id' => $head->id]])
            ->get(route('forms.monthly.r4-01.snapshots.download', $snapshot))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="Monthly.docx"');
        $this->withSession(['login_user' => ['id' => $head->id]])
            ->get(route('forms.monthly.r4-01.snapshots.view', $snapshot))
            ->assertOk()
            ->assertSee('<p>preview</p>', false);
    }

    private function makeHead(array $attributes = []): User
    {
        $user = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'first_name' => 'Head',
            'last_name' => 'Secretary',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ] + array_intersect_key($attributes, array_flip(['profile_picture'])));
        $extra = array_diff_key($attributes, array_flip(['profile_picture']));
        if ($extra !== []) {
            $user->forceFill($extra)->save();
        }

        return $user;
    }

    private function export(User $head, array $query = [], bool $asHead = true): string
    {
        $stream = fopen('php://memory', 'w+b');
        app(DatabaseBackupService::class)->writeSql($stream, $query['exclude_tables'] ?? []);
        rewind($stream);
        $sql = (string) stream_get_contents($stream);
        fclose($stream);
        if ($asHead) {
            $this->assertStringStartsWith("-- LSPDMS-SQL-EXPORT-V1\n", $sql);
        }

        return $sql;
    }
}
