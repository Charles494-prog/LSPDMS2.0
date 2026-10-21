<?php

namespace Tests\Feature;

use App\Models\SystemSmtpSetting;
use App\Models\GlobalVariable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDatabaseAndSmtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_exposes_database_and_smtp_controls_without_credentials(): void
    {
        $headSecretary = $this->createHeadSecretary();
        $setting = SystemSmtpSetting::query()->create([
            'email' => 'mailbox@example.test',
            'app_password' => 'sensitive-app-password',
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('SMTP Settings')
            ->assertSee('Email delivery')
            ->assertSee('Configured')
            ->assertSee('Before you start')
            ->assertSee('smtp-setup-wrap', false)
            ->assertSee('Database')
            ->assertSee('mailbox@example.test')
            ->assertSee('Download entire database (lspdms.sql)')
            ->assertSee('id="excludeLoginUser"', false)
            ->assertSee('value="login_user"', false)
            ->assertSee('id="excludeMessages"', false)
            ->assertSee('value="messages"', false)
            ->assertSee('id="databaseSqlDropzone"', false)
            ->assertSee('Drop your SQL backup here')
            ->assertSee('Drag and drop an <strong>.sql</strong> file, or click to browse your computer', false)
            ->assertSee('min-height: 240px', false)
            ->assertSee('id="databaseImportModal"', false)
            ->assertSee('IMPORT DATABASE')
            ->assertSee('Head Secretary current password')
            ->assertDontSee('window.confirm(confirmation)', false)
            ->assertSee("addEventListener('drop'", false)
            ->assertSee('Export Selected Tables to XLSX')
            ->assertSee('Unchecked tables are included.')
            ->assertDontSee('Email delivery is not configured')
            ->assertDontSee('sensitive-app-password');

        $this->assertNotSame('sensitive-app-password', $setting->getRawOriginal('app_password'));
    }

    public function test_admin_is_prompted_to_configure_smtp_when_authentication_emails_are_enabled(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => null,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.mailers.smtp.port' => null,
        ]);
        $headSecretary = $this->createHeadSecretary();
        GlobalVariable::query()->create([
            'require_otp' => true,
            'allow_forgot_password' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('Email delivery is not configured')
            ->assertSee('Email verification and password recovery are enabled')
            ->assertSee('data-open-smtp', false)
            ->assertSee('id="smtpSecurityAlert"', false)
            ->assertSee('Configure SMTP')
            ->assertSee('const refreshSmtpPrompt', false)
            ->assertSee("activateTab('smtp-settings')", false);

        GlobalVariable::query()->update([
            'require_otp' => false,
            'allow_forgot_password' => false,
        ]);
        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('id="smtpSetupAlert"', false)
            ->assertSee('id="smtpSecurityAlert"', false)
            ->assertSee(' hidden >', false);
    }

    public function test_existing_environment_smtp_configuration_suppresses_setup_warning(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'environment-mailbox@example.test',
            'mail.mailers.smtp.password' => 'environment-secret',
        ]);
        $headSecretary = $this->createHeadSecretary();
        GlobalVariable::query()->create([
            'require_otp' => true,
            'allow_forgot_password' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('Configured via app environment')
            ->assertSee('environment-mailbox@example.test')
            ->assertSee('Laravel is currently using environment SMTP settings.')
            ->assertDontSee('Email delivery is not configured')
            ->assertDontSee('environment-secret');
    }

    public function test_smtp_settings_are_encrypted_applied_and_never_returned(): void
    {
        $headSecretary = $this->createHeadSecretary();

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->putJson(route('superadmin.smtp-settings.update'), [
                'email' => 'new-mailbox@example.test',
                'app_password' => 'abcd efgh ijkl mnop',
            ])
            ->assertOk()
            ->assertJsonPath('email', 'new-mailbox@example.test')
            ->assertJsonMissingPath('app_password')
            ->assertJsonMissingPath('password');

        $setting = SystemSmtpSetting::query()->firstOrFail();
        $this->assertSame('abcdefghijklmnop', $setting->app_password);
        $this->assertNotSame('abcdefghijklmnop', $setting->getRawOriginal('app_password'));
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame('new-mailbox@example.test', config('mail.mailers.smtp.username'));
        $this->assertSame('abcdefghijklmnop', config('mail.mailers.smtp.password'));
        $this->assertSame('new-mailbox@example.test', config('mail.from.address'));
    }

    public function test_smtp_password_is_required_when_the_gmail_address_changes(): void
    {
        $headSecretary = $this->createHeadSecretary();
        SystemSmtpSetting::query()->create([
            'email' => 'old-mailbox@example.test',
            'app_password' => 'existing-app-password',
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->putJson(route('superadmin.smtp-settings.update'), [
                'email' => 'new-mailbox@example.test',
                'app_password' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('app_password');
    }

    public function test_sql_export_is_marked_as_lspdms_format_and_excludes_smtp_credentials(): void
    {
        $headSecretary = $this->createHeadSecretary();
        SystemSmtpSetting::query()->create([
            'email' => 'mailbox@example.test',
            'app_password' => 'private-app-password',
        ]);

        $response = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin.database.export-sql'))
            ->assertOk()
            ->assertDownload('lspdms.sql');

        $sql = $response->streamedContent();
        $this->assertStringStartsWith("-- LSPDMS-SQL-EXPORT-V1\n-- ENGINE: sqlite", $sql);
        $this->assertStringContainsString('CREATE TABLE "login_user"', $sql);
        $this->assertStringContainsString('CREATE TABLE "messages"', $sql);
        $this->assertStringNotContainsString('private-app-password', $sql);
        $this->assertStringNotContainsString('system_smtp_settings', $sql);
        $this->assertDoesNotMatchRegularExpression('/INSERT INTO "login_user" \([^)]*head_secretary_slot/', $sql);
    }

    public function test_sql_export_can_exclude_login_user_and_messages_tables(): void
    {
        $headSecretary = $this->createHeadSecretary();

        $response = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin.database.export-sql', [
                'exclude_tables' => ['login_user', 'messages'],
            ]))
            ->assertOk()
            ->assertDownload('lspdms.sql');

        $sql = $response->streamedContent();
        $this->assertStringNotContainsString('CREATE TABLE "login_user"', $sql);
        $this->assertStringNotContainsString('INSERT INTO "login_user"', $sql);
        $this->assertStringNotContainsString('CREATE TABLE "messages"', $sql);
        $this->assertStringNotContainsString('INSERT INTO "messages"', $sql);
        $this->assertStringContainsString('CREATE TABLE "records"', $sql);
    }

    public function test_sql_import_replaces_included_tables_and_retains_omitted_tables(): void
    {
        $headSecretary = $this->createHeadSecretary();
        DB::table('cache')->insert([
            'key' => 'old-cache-entry',
            'value' => 'old value',
            'expiration' => 1,
        ]);
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => 1,
            'created_at' => 1,
        ]);
        $sqlFile = UploadedFile::fake()->createWithContent('partial-backup.sql', $this->partialCacheBackup());

        $preview = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->postJson(route('superadmin.database.import-preview'), ['sql_file' => $sqlFile])
            ->assertOk()
            ->assertJsonPath('tables.0', 'cache');
        $this->assertContains('jobs', $preview->json('omitted_tables'));

        $response = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => $sqlFile,
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Database import completed. A safety backup was saved before the import.');

        $this->assertDatabaseMissing('cache', ['key' => 'old-cache-entry']);
        $this->assertDatabaseHas('cache', ['key' => 'imported-cache-entry', 'value' => 'imported value']);
        $this->assertDatabaseHas('jobs', ['queue' => 'default', 'payload' => '{}']);

        $backupPath = storage_path('app/private/database-backups/'.$response->json('backup_file'));
        $this->assertFileExists($backupPath);
        unlink($backupPath);
    }

    public function test_sql_import_requires_head_secretary_password_and_exact_confirmation_before_changing_data(): void
    {
        $headSecretary = $this->createHeadSecretary();
        DB::table('cache')->insert([
            'key' => 'old-cache-entry',
            'value' => 'old value',
            'expiration' => 1,
        ]);

        foreach ([
            ['confirmation_text' => 'IMPORT DATABASE', 'current_password' => 'wrong-password'],
            ['confirmation_text' => 'continue', 'current_password' => 'secret-password'],
        ] as $confirmation) {
            $this->withSession(['login_user' => ['id' => $headSecretary->id]])
                ->postJson(route('superadmin.database.import'), [
                    'sql_file' => UploadedFile::fake()->createWithContent('partial-backup.sql', $this->partialCacheBackup()),
                    ...$confirmation,
                ])
                ->assertUnprocessable();

            $this->assertDatabaseHas('cache', ['key' => 'old-cache-entry', 'value' => 'old value']);
        }
    }

    public function test_sql_import_rejects_unrecognized_statements_without_changing_data(): void
    {
        $headSecretary = $this->createHeadSecretary();
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => 1,
            'created_at' => 1,
        ]);
        $sql = $this->partialCacheBackup()."DELETE FROM \"jobs\";\n";

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => UploadedFile::fake()->createWithContent('unsafe.sql', $sql),
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('jobs', ['queue' => 'default', 'payload' => '{}']);
    }

    public function test_xlsx_export_contains_only_the_selected_table(): void
    {
        $headSecretary = $this->createHeadSecretary();
        DB::table('cache')->insert([
            'key' => 'xlsx-entry',
            'value' => '=1+1',
            'expiration' => 1,
        ]);

        $response = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->post(route('superadmin.database.export-xlsx'), ['tables' => ['cache']])
            ->assertOk()
            ->assertDownload();

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertSame(true, $zip->open($path));
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($worksheet);
        $this->assertStringContainsString('<t>=1+1</t>', $worksheet);
        $this->assertStringNotContainsString('<f>1+1</f>', $worksheet);
        $zip->close();
        unlink($path);
    }

    private function createHeadSecretary(): User
    {
        return User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'first_name' => 'Head',
            'last_name' => 'Secretary',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
    }

    private function partialCacheBackup(): string
    {
        $value = static fn (string $text): string => "CAST(X'".bin2hex($text)."' AS TEXT)";

        return "-- LSPDMS-SQL-EXPORT-V1\n"
            ."-- ENGINE: sqlite\n\n"
            ."CREATE TABLE \"cache\" (\"key\" TEXT, \"value\" TEXT, \"expiration\" INTEGER);\n"
            .'INSERT INTO "cache" ("key", "value", "expiration") VALUES ('.$value('imported-cache-entry').', '.$value('imported value').', '.$value('1').");\n";
    }
}
