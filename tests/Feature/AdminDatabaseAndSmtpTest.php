<?php

namespace Tests\Feature;

use App\Mail\SmtpSetupVerificationMail;
use App\Models\SystemSmtpSetting;
use App\Models\GlobalVariable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            ->assertSee('Email delivery')
            ->assertSee('id="tab-smtp-settings"', false)
            ->assertSee('id="smtpConnectBtn"', false)
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
            ->assertSee('data-open-smtp', false)
            ->assertSee('id="smtpSecurityAlert"', false)
            ->assertSee('Configure SMTP')
            ->assertSee('const refreshSmtpPrompt', false)
            ->assertSee("activateTab('smtp-settings')", false);

        // Without a verified sender the email-dependent features are switched off, never left on to lock people out.
        $settings = GlobalVariable::query()->firstOrFail();
        $this->assertFalse($settings->require_otp);
        $this->assertFalse($settings->allow_forgot_password);
        $this->assertDatabaseHas('system_maintenance_audits', [
            'action' => 'email_features_off:smtp_not_configured',
            'affected_count' => 2,
        ]);

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
            ->assertSee("Emails currently send using the server's environment settings.", false)
            ->assertDontSee('Email delivery is not configured')
            ->assertDontSee('environment-secret');
    }

    public function test_smtp_settings_are_encrypted_and_never_returned(): void
    {
        Mail::fake();
        $headSecretary = $this->createHeadSecretary();

        $this->withSession(['login_user' => ['id' => $headSecretary->id, 'gmail' => $headSecretary->gmail]])
            ->putJson(route('superadmin.smtp-settings.update'), [
                'email' => 'new-mailbox@example.test',
                'app_password' => 'abcd efgh ijkl mnop',
            ])
            ->assertOk()
            ->assertJsonPath('email_sent', true)
            ->assertJsonPath('state.email', 'new-mailbox@example.test')
            ->assertJsonPath('state.verified', false)
            ->assertJsonPath('state.pending', true)
            ->assertJsonMissingPath('app_password')
            ->assertJsonMissingPath('password')
            ->assertDontSee('abcdefghijklmnop');

        $setting = SystemSmtpSetting::query()->firstOrFail();
        $this->assertSame('abcdefghijklmnop', $setting->app_password);
        $this->assertNotSame('abcdefghijklmnop', $setting->getRawOriginal('app_password'));
        $this->assertFalse($setting->isVerified());
        Mail::assertSent(SmtpSetupVerificationMail::class, fn (SmtpSetupVerificationMail $mail): bool => $mail->hasTo($headSecretary->gmail)
            && $mail->senderEmail === 'new-mailbox@example.test');
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
        $this->insertHakbang('OLD', 'old hakbang');
        DB::table('records')->insert(['full_name' => 'KEEP ME', 'status' => 'MS']);
        $sqlFile = UploadedFile::fake()->createWithContent('partial-backup.sql', $this->partialBackup());

        $preview = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->postJson(route('superadmin.database.import-preview'), ['sql_file' => $sqlFile])
            ->assertOk()
            ->assertJsonPath('tables.0', 'r4_02_hakbangs')
            ->assertJsonPath('row_counts.r4_02_hakbangs', 1);
        $this->assertContains('records', $preview->json('omitted_tables'));

        $response = $this->withSession(['login_user' => ['id' => $headSecretary->id, 'gmail' => $headSecretary->gmail]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => $sqlFile,
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertOk()
            ->assertJsonPath('inserted_rows', 1);
        $this->assertStringContainsString('verified safety backup', $response->json('message'));

        $this->assertDatabaseMissing('r4_02_hakbangs', ['code' => 'OLD']);
        $this->assertDatabaseHas('r4_02_hakbangs', ['code' => 'NEW', 'description' => 'imported hakbang']);
        $this->assertDatabaseHas('records', ['full_name' => 'KEEP ME']);
        $this->assertDatabaseHas('system_maintenance_audits', ['action' => 'database_import', 'affected_count' => 1, 'changed_by' => $headSecretary->gmail]);

        $backupPath = app(\App\Services\DatabaseBackupService::class)->backupDirectory().DIRECTORY_SEPARATOR.$response->json('backup_file');
        $this->assertFileExists($backupPath);
        unlink($backupPath);
    }

    public function test_sql_import_skips_protected_tables_found_in_older_backups(): void
    {
        $headSecretary = $this->createHeadSecretary();
        DB::table('cache')->insert(['key' => 'live-cache', 'value' => 'live', 'expiration' => 1]);
        $value = static fn (string $text): string => "CAST(X'".bin2hex($text)."' AS TEXT)";
        $sql = $this->partialBackup()
            ."CREATE TABLE \"cache\" (\"key\" TEXT, \"value\" TEXT, \"expiration\" INTEGER);\n"
            .'INSERT INTO "cache" ("key", "value", "expiration") VALUES ('.$value('imported-cache').', '.$value('x').', '.$value('1').");\n"
            ."CREATE TABLE \"system_smtp_settings\" (\"id\" INTEGER);\n";

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->postJson(route('superadmin.database.import-preview'), ['sql_file' => UploadedFile::fake()->createWithContent('legacy.sql', $sql)])
            ->assertOk()
            ->assertJsonPath('ignored_tables', ['cache', 'system_smtp_settings']);

        $response = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => UploadedFile::fake()->createWithContent('legacy.sql', $sql),
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertOk();

        $this->assertDatabaseHas('cache', ['key' => 'live-cache']);
        $this->assertDatabaseMissing('cache', ['key' => 'imported-cache']);
        unlink(app(\App\Services\DatabaseBackupService::class)->backupDirectory().DIRECTORY_SEPARATOR.$response->json('backup_file'));
    }

    public function test_sql_import_requires_head_secretary_password_and_exact_confirmation_before_changing_data(): void
    {
        $headSecretary = $this->createHeadSecretary();
        $this->insertHakbang('OLD', 'old hakbang');

        foreach ([
            ['confirmation_text' => 'IMPORT DATABASE', 'current_password' => 'wrong-password'],
            ['confirmation_text' => 'continue', 'current_password' => 'secret-password'],
        ] as $confirmation) {
            $this->withSession(['login_user' => ['id' => $headSecretary->id]])
                ->postJson(route('superadmin.database.import'), [
                    'sql_file' => UploadedFile::fake()->createWithContent('partial-backup.sql', $this->partialBackup()),
                    ...$confirmation,
                ])
                ->assertUnprocessable();

            $this->assertDatabaseHas('r4_02_hakbangs', ['code' => 'OLD']);
        }
    }

    public function test_sql_import_rejects_unrecognized_statements_without_changing_data(): void
    {
        $headSecretary = $this->createHeadSecretary();
        $this->insertHakbang('OLD', 'old hakbang');
        $sql = $this->partialBackup()."DELETE FROM \"records\";\n";

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->postJson(route('superadmin.database.import'), [
                'sql_file' => UploadedFile::fake()->createWithContent('unsafe.sql', $sql),
                'confirmation_text' => 'IMPORT DATABASE',
                'current_password' => 'secret-password',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('r4_02_hakbangs', ['code' => 'OLD']);
    }

    public function test_xlsx_export_contains_only_the_selected_table(): void
    {
        $headSecretary = $this->createHeadSecretary();
        $this->insertHakbang('XLSX', '=1+1');

        $response = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->post(route('superadmin.database.export-xlsx'), ['tables' => ['r4_02_hakbangs']])
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

    private function insertHakbang(string $code, string $description): void
    {
        DB::table('r4_02_hakbangs')->insert([
            'status' => 'MS',
            'code' => $code,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function partialBackup(): string
    {
        $value = static fn (string $text): string => "CAST(X'".bin2hex($text)."' AS TEXT)";

        return "-- LSPDMS-SQL-EXPORT-V1\n"
            ."-- ENGINE: sqlite\n\n"
            ."CREATE TABLE \"r4_02_hakbangs\" (\"id\" INTEGER, \"status\" TEXT, \"code\" TEXT, \"description\" TEXT);\n"
            .'INSERT INTO "r4_02_hakbangs" ("id", "status", "code", "description") VALUES ('.$value('1').', '.$value('MS').', '.$value('NEW').', '.$value('imported hakbang').");\n";
    }
}
