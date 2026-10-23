<?php

namespace Tests\Feature;

use App\Models\GlobalVariable;
use App\Models\Purok;
use App\Models\R402Hakbang;
use App\Models\Record;
use App\Models\RecordAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuperadminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_database_users_without_sample_users_or_credentials(): void
    {
        $user = User::query()->create([
            'gmail' => 'real.user@example.test',
            'password' => 'secret-password',
            'first_name' => 'Real',
            'last_name' => 'User',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('Real User')
            ->assertSee('real.user@example.test')
            ->assertDontSee('Maria Santos')
            ->assertDontSee('Juan Dela Cruz')
            ->assertDontSee('secret-password')
            ->assertDontSee('name="password"')
            ->assertSee('id="saveSignatoriesBtn" disabled', false)
            ->assertSee('id="appNotice"', false)
            ->assertSee('Mga R4-02 Hakbang')
            ->assertSee('data-hakbang-status="MS"', false)
            ->assertSee('data-hakbang-status="UWP"', false)
            ->assertSee('BNH FORM RESET')
            ->assertSee('bnhResetCountdownBar')
            ->assertSee('id="bnhResetEmptyState"', false)
            ->assertSee('.bnh-reset-state[hidden], .bnh-reset-code-panel[hidden] { display:none !important; }', false)
            ->assertSee('#tab-maintenance.sa-tab-panel.active { min-height:calc(100vh - 14rem); display:flex; align-items:center; justify-content:center; }', false)
            ->assertSee('#tab-maintenance > .sa-card { width:min(100%,760px); margin:0 auto; box-sizing:border-box; }', false)
            ->assertSee('#tab-maintenance .bnh-reset-grid { grid-template-columns:minmax(0,1fr); }', false)
            ->assertSee('#tab-maintenance .bnh-reset-field { grid-column:1 / -1; }', false)
            ->assertSee('No BNH code has been detected')
            ->assertSee('If a code becomes available, it will appear here instead.')
            ->assertSee('Enter the code below to reset the BNH Form Helper')
            ->assertDontSee('The checks reset automatically when all characters match.')
            ->assertSee('setInterval(updateCountdown, 100)')
            ->assertDontSee('Monthly session debug')
            ->assertDontSee('startMonthlyDebugSessionButton')
            ->assertDontSee('id="signatoriesSuccessModal"', false);
        $this->assertStringNotContainsString(
            "window.alert('BNH CHECKS is currently Empty.')",
            file_get_contents(resource_path('views/superadmin-dashboard.blade.php'))
        );
        $this->assertFalse(Route::has('superadmin.monthly-session.start-debug'));
    }

    public function test_record_activity_is_visible_in_the_head_secretary_dashboard(): void
    {
        $headSecretary = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'first_name' => 'Head',
            'last_name' => 'Secretary',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        RecordAuditLog::query()->create([
            'record_id' => 42,
            'record_name' => 'Juan Dela Cruz',
            'actor_email' => 'formalcharlesandreigalvan@gmail.com',
            'action' => 'updated',
            'summary' => 'Transferred Juan Dela Cruz from UWP to MS.',
            'changes' => ['status' => ['from' => 'UWP', 'to' => 'MS']],
            'created_at' => now(),
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('Secretary activity')
            ->assertSee('Record changes with the acting account, date and changed values.')
            ->assertSee('formalcharlesandreigalvan@gmail.com')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Transferred Juan Dela Cruz from UWP to MS.');
    }

    public function test_record_activity_can_be_filtered_by_date_record_name_and_secretary_email(): void
    {
        $headSecretary = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $base = [
            'action' => 'updated',
            'changes' => ['status' => ['from' => 'UWP', 'to' => 'MS']],
        ];
        RecordAuditLog::query()->create($base + [
            'record_name' => 'Juan Dela Cruz',
            'actor_email' => 'secretary.one@example.test',
            'summary' => 'Target activity entry.',
            'created_at' => '2026-10-02 18:00:00',
        ]);
        RecordAuditLog::query()->create($base + [
            'record_name' => 'Juan Dela Cruz',
            'actor_email' => 'secretary.two@example.test',
            'summary' => 'Different secretary entry.',
            'created_at' => '2026-10-02 18:00:00',
        ]);
        RecordAuditLog::query()->create($base + [
            'record_name' => 'Maria Santos',
            'actor_email' => 'secretary.one@example.test',
            'summary' => 'Different record entry.',
            'created_at' => '2026-10-02 18:00:00',
        ]);
        RecordAuditLog::query()->create($base + [
            'record_name' => 'Juan Dela Cruz',
            'actor_email' => 'secretary.one@example.test',
            'summary' => 'Outside date range entry.',
            'created_at' => '2026-10-03 18:00:00',
        ]);

        $filters = [
            'date_from' => '2026-10-03',
            'date_to' => '2026-10-03',
            'record_name' => 'Juan Dela',
            'actor_email' => 'secretary.one',
        ];
        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard', $filters))
            ->assertOk()
            ->assertSee('Target activity entry.')
            ->assertSee('value="Juan Dela"', false)
            ->assertSee('value="secretary.one"', false)
            ->assertDontSee('Different secretary entry.')
            ->assertDontSee('Different record entry.')
            ->assertDontSee('Outside date range entry.');
    }

    public function test_filtered_record_activity_can_be_downloaded_as_a_word_table(): void
    {
        $headSecretary = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        RecordAuditLog::query()->create([
            'record_name' => 'Juan Dela Cruz',
            'actor_email' => 'secretary.one@example.test',
            'action' => 'updated',
            'summary' => 'Transferred Juan Dela Cruz from UWP to MS.',
            'changes' => ['status' => ['from' => 'UWP', 'to' => 'MS']],
            'created_at' => '2026-10-02 18:00:00',
        ]);
        RecordAuditLog::query()->create([
            'record_name' => 'Maria Santos',
            'actor_email' => 'secretary.two@example.test',
            'action' => 'updated',
            'summary' => 'Should not be in the filtered export.',
            'changes' => ['status' => ['from' => 'UWP', 'to' => 'MS']],
            'created_at' => '2026-10-02 18:00:00',
        ]);

        $response = $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin.record-activity.download', [
                'date_from' => '2026-10-03',
                'date_to' => '2026-10-03',
                'record_name' => 'Juan',
                'actor_email' => 'secretary.one',
            ]))
            ->assertOk()
            ->assertDownload('record-activity-log.doc')
            ->assertHeader('Content-Type', 'application/msword; charset=UTF-8');

        $word = $response->streamedContent();
        $this->assertStringContainsString('<table>', $word);
        $this->assertStringContainsString('<th>Secretary email</th>', $word);
        $this->assertStringContainsString('<td>secretary.one@example.test</td>', $word);
        $this->assertStringContainsString('Oct 03, 2026 02:00:00 AM', $word);
        $this->assertStringContainsString('Transferred Juan Dela Cruz from UWP to MS.', $word);
        $this->assertStringNotContainsString('Maria Santos', $word);
        $this->assertStringNotContainsString('Should not be in the filtered export.', $word);
    }

    public function test_record_activity_download_is_head_secretary_only(): void
    {
        $secretary = User::query()->create([
            'gmail' => 'secretary@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('superadmin.record-activity.download'))
            ->assertForbidden();
    }

    public function test_superadmin_layout_keeps_authenticated_head_secretary_identity_after_user_loops(): void
    {
        $headSecretary = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'first_name' => 'Dudu',
            'last_name' => 'Bear',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        User::query()->create([
            'gmail' => 'last.user@example.test',
            'password' => 'secret-password',
            'first_name' => 'Last',
            'last_name' => 'User',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('Dudu Bear')
            ->assertSee('Head Secretary')
            ->assertSee('Superadmin Dashboard')
            ->assertDontSee('CHARLESS 2 Secretary');
    }

    public function test_head_secretary_role_can_only_be_assigned_through_confirmed_transfer(): void
    {
        $admin = User::query()->create([
            'gmail' => 'admin@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $user = User::query()->create([
            'gmail' => 'secretary@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('id="headSecretaryTransferPassword"', false)
            ->assertSee('head-secretary-transfer-controls', false);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.users.update', $user), [
                'is_verified' => 1,
                'tungkulin' => 'Head Secretary',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tungkulin');

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->postJson(route('superadmin.users.transfer-head-secretary'), [
                'target_user_id' => $user->id,
                'confirmation' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->postJson(route('superadmin.users.transfer-head-secretary'), [
                'target_user_id' => $user->id,
                'current_password' => 'incorrect-password',
                'confirmation' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertDatabaseHas('login_user', ['id' => $admin->id, 'tungkulin' => 'Head Secretary']);
        $this->assertDatabaseHas('login_user', ['id' => $user->id, 'tungkulin' => 'Secretary']);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->postJson(route('superadmin.users.transfer-head-secretary'), [
                'target_user_id' => $user->id,
                'current_password' => 'secret-password',
                'confirmation' => true,
            ])
            ->assertOk()
            ->assertJsonPath('target_user.id', $user->id);

        $this->assertDatabaseHas('login_user', ['id' => $admin->id, 'tungkulin' => 'Secretary']);
        $this->assertDatabaseHas('login_user', ['id' => $user->id, 'is_verified' => 1, 'tungkulin' => 'Head Secretary']);
    }

    public function test_permissions_can_only_be_saved_for_verified_users(): void
    {
        $admin = User::query()->create([
            'gmail' => 'admin@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $verifiedUser = User::query()->create([
            'gmail' => 'verified@example.test',
            'password' => 'secret-password',
            'is_verified' => true,
        ]);
        $unverifiedUser = User::query()->create([
            'gmail' => 'unverified@example.test',
            'password' => 'secret-password',
            'is_verified' => false,
        ]);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.permissions.update'), [
                'permissions' => [[
                    'id' => $verifiedUser->id,
                    'dashboard_allow' => 1,
                    'form_generator_allow' => 0,
                    'Delistment_allow' => 1,
                    'Inquire_Allow' => 0,
                    'tarheta_status_allow' => 1,
                    'filter_bnh_helper_allow' => 1,
                    'chatbot_records_allow' => 1,
                    'note_checker_allow' => 1,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('login_user', [
            'id' => $verifiedUser->id,
            'dashboard_allow' => 1,
            'form_generator_allow' => 0,
            'Delistment_allow' => 1,
            'Inquire_Allow' => 0,
            'tarheta_status_allow' => 1,
            'filter_bnh_helper_allow' => 1,
            'chatbot_records_allow' => 1,
            'note_checker_allow' => 1,
        ]);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('Allow Tarheta<br>Status Checker', false)
            ->assertSee('data-permission="tarheta_status_allow" checked', false)
            ->assertSee('Allow Filter &amp;<br>BNH Helper', false)
            ->assertSee('data-permission="filter_bnh_helper_allow" checked', false)
            ->assertSee('Allow chatbot<br>record lookups', false)
            ->assertSee('data-permission="chatbot_records_allow" checked', false)
            ->assertSee('Allow Note<br>Checker', false)
            ->assertSee('data-permission="note_checker_allow" checked', false);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.permissions.update'), [
                'permissions' => [[
                    'id' => $unverifiedUser->id,
                    'dashboard_allow' => 1,
                    'form_generator_allow' => 1,
                    'Delistment_allow' => 1,
                    'Inquire_Allow' => 1,
                    'tarheta_status_allow' => 1,
                    'filter_bnh_helper_allow' => 1,
                    'chatbot_records_allow' => 1,
                ]],
            ])
            ->assertUnprocessable();
    }

    public function test_signatories_and_global_variables_are_saved_to_their_tables(): void
    {
        $admin = User::query()->create([
            'gmail' => 'admin@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $secretary = User::query()->create([
            'gmail' => 'secretary@example.test',
            'password' => 'secret-password',
            'first_name' => 'Secretary',
        ]);
        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.signatories.update'), [
                'puroks' => [[
                    'purok_number' => 3,
                    'purok_name' => 'Purok Three',
                    'secretary' => $secretary->id,
                    'manggagawa' => 'Worker Name',
                    'ministerial_id' => 'MID-003',
                    'grupo_start' => 1,
                    'grupo_end' => 12,
                    'full_purok' => 0,
                ]],
            ])
            ->assertOk();

        $purok = Purok::query()->firstOrFail();
        $this->assertDatabaseHas('puroks', [
            'id' => $purok->id,
            'purok_number' => 3,
            'purok_name' => 'Purok Three',
            'secretary' => $secretary->id,
            'manggagawa' => 'Worker Name',
            'ministerial_id' => 'MID-003',
            'grupo_start' => 1,
            'grupo_end' => 12,
            'full_purok' => 0,
        ]);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('Purok Three')
            ->assertSee('secretary@example.test')
            ->assertSee('Worker Name')
            ->assertSee('Minimum years since date of entry')
            ->assertSee('Baptism cutoff date')
            ->assertDontSee('delistEntryCheck')
            ->assertSee('<select id="gvEntryYears" required', false)
            ->assertSee('type="text" class="signatory-input manggagawa-input"', false)
            ->assertDontSee('<select class="signatory-input manggagawa-input">', false)
            ->assertSee('label for="gvHeadLsp">Head LSP</label>', false);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.global-variables.update'), [
                'locale' => 'Sample Locale',
                'lcode' => 'L-123',
                'district' => 'Sample District',
                'dcode' => 'D-456',
                's_number' => 'S-123',
                'pastor_destinado' => 'Pastor Person',
                'ksp_pd' => 'KSP Person',
                'head_lsp' => 'Head LSP Person',
                'kalihim_ms' => 'MS Secretary',
                'kalihim_uwp' => 'UWP Secretary',
                'baptism_requirement' => '2026-01-01 00:00:00',
                'entry_years_requirement' => 2,
                'dateofbaptism_requirement_boolean' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseHas('global_variables', [
            'locale' => 'Sample Locale',
            'lcode' => 'L-123',
            'district' => 'Sample District',
            'dcode' => 'D-456',
            's_number' => 'S-123',
            'pastor_destinado' => 'Pastor Person',
            'ksp_pd' => 'KSP Person',
            'head_lsp' => 'Head LSP Person',
            'kalihim_ms' => 'MS Secretary',
            'kalihim_uwp' => 'UWP Secretary',
            'baptism_requirement' => '2026-01-01 00:00:00',
            'entry_years_requirement' => 2,
            'dateofbaptism_requirement_boolean' => 1,
        ]);

        $this->assertInstanceOf(GlobalVariable::class, GlobalVariable::query()->first());

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('Sample Locale')
            ->assertSee('L-123')
            ->assertSee('Sample District')
            ->assertSee('D-456')
            ->assertSee('Pastor Person');
    }

    public function test_puroks_can_share_a_header_number(): void
    {
        $admin = User::query()->create([
            'gmail' => 'admin@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.signatories.update'), [
                'puroks' => [
                    [
                        'purok_number' => 2,
                        'purok_name' => 'Purok 2a',
                        'grupo_start' => 1,
                        'grupo_end' => 6,
                        'full_purok' => 0,
                    ],
                    [
                        'purok_number' => 2,
                        'purok_name' => 'Purok 2b',
                        'grupo_start' => 7,
                        'grupo_end' => 10,
                        'full_purok' => 0,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame(2, Purok::query()->where('purok_number', 2)->count());
    }

    public function test_purok_ranges_sharing_a_header_cannot_overlap(): void
    {
        $admin = User::query()->create([
            'gmail' => 'admin@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.signatories.update'), [
                'puroks' => [
                    [
                        'purok_number' => 2,
                        'purok_name' => 'Purok 2a',
                        'grupo_start' => 1,
                        'grupo_end' => 6,
                        'full_purok' => 0,
                    ],
                    [
                        'purok_number' => 2,
                        'purok_name' => 'Purok 2b',
                        'grupo_start' => 6,
                        'grupo_end' => 10,
                        'full_purok' => 0,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('puroks.1.grupo_start');

        $this->assertSame(0, Purok::query()->count());
    }

    public function test_complete_purok_cannot_overlap_a_split_purok_with_the_same_header(): void
    {
        $admin = User::query()->create([
            'gmail' => 'admin@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $purok2a = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2a',
            'grupo_start' => 1,
            'grupo_end' => 6,
            'full_purok' => false,
        ]);
        $purok2b = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2b',
            'grupo_start' => 7,
            'grupo_end' => 11,
            'full_purok' => false,
        ]);

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.signatories.update'), [
                'puroks' => [
                    [
                        'id' => $purok2a->id,
                        'purok_number' => '2',
                        'purok_name' => 'Purok 2a',
                        'grupo_start' => 1,
                        'grupo_end' => 6,
                        'full_purok' => 1,
                    ],
                    [
                        'id' => $purok2b->id,
                        'purok_number' => 2,
                        'purok_name' => 'Purok 2b',
                        'grupo_start' => 7,
                        'grupo_end' => 11,
                        'full_purok' => 0,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('puroks.0.full_purok');

        $this->assertDatabaseHas('puroks', [
            'id' => $purok2a->id,
            'full_purok' => false,
            'grupo_start' => 1,
            'grupo_end' => 6,
        ]);
        $this->assertDatabaseHas('puroks', [
            'id' => $purok2b->id,
            'full_purok' => false,
            'grupo_start' => 7,
            'grupo_end' => 11,
        ]);
    }

    public function test_security_settings_are_persisted_and_only_head_secretary_can_access_them(): void
    {
        $headSecretary = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $secretary = User::query()->create([
            'gmail' => 'secretary@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->putJson(route('superadmin.security-settings.update'), [
                'require_otp' => 0,
                'allow_forgot_password' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.require_otp', false)
            ->assertJsonPath('data.allow_forgot_password', false);

        $this->assertDatabaseHas('global_variables', [
            'require_otp' => 0,
            'allow_forgot_password' => 0,
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertOk()
            ->assertSee('id="requireOtpSetting"', false)
            ->assertSee('id="allowForgotPasswordSetting"', false);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('superadmin-dashboard'))
            ->assertForbidden();

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->putJson(route('superadmin.security-settings.update'), [
                'require_otp' => 1,
                'allow_forgot_password' => 1,
            ])
            ->assertForbidden();
    }

    public function test_disabling_user_access_invalidates_the_account_session_and_remember_login(): void
    {
        $headSecretary = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $secretary = User::query()->create([
            'gmail' => 'secretary@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'remember_token' => 'stored-token',
            'remember_token_expires_at' => now()->addDay(),
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->putJson(route('superadmin.users.update', $secretary), ['is_verified' => 0])
            ->assertOk()
            ->assertJsonPath('data.is_verified', false)
            ->assertJsonPath('data.revoked_current_session', false);

        $this->assertDatabaseHas('login_user', [
            'id' => $secretary->id,
            'is_verified' => 0,
            'remember_token' => null,
            'remember_token_expires_at' => null,
        ]);
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('home'))
            ->assertRedirect('/login');
    }

    public function test_head_secretary_access_cannot_be_disabled_from_admin_portal(): void
    {
        $headSecretary = User::query()->create([
            'gmail' => 'head@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->putJson(route('superadmin.users.update', $headSecretary), ['is_verified' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_verified');

        $this->assertDatabaseHas('login_user', [
            'id' => $headSecretary->id,
            'is_verified' => 1,
        ]);
    }

    public function test_baptism_cutoff_is_required_when_enabled_and_entry_years_are_always_required(): void
    {
        $admin = User::query()->create([
            'gmail' => 'requirements-admin@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $base = [
            'dateofbaptism_requirement_boolean' => 1,
            'entry_years_requirement' => 2,
        ];

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.global-variables.update'), $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('baptism_requirement');

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.global-variables.update'), [
                'dateofbaptism_requirement_boolean' => 0,
                'entry_years_requirement' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entry_years_requirement');

        $this->withSession(['login_user' => ['id' => $admin->id]])
            ->putJson(route('superadmin.global-variables.update'), [
                'dateofbaptism_requirement_boolean' => 0,
                'entry_years_requirement' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.entry_years_requirement', 3);
    }

    public function test_bnh_reset_clears_only_helper_checks_for_all_records_and_is_head_secretary_only(): void
    {
        $admin = User::query()->create([
            'gmail' => 'bnh.reset@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $checked = Record::query()->create([
            'full_name' => 'Checked Member',
            'status' => 'MK',
            'monthly_code' => 'MK: E',
            'is_valid1' => true,
            'is_valid2' => true,
        ]);
        $unchecked = Record::query()->create([
            'full_name' => 'Unchecked Member',
            'status' => 'UWP',
            'is_valid1' => false,
            'is_valid2' => false,
        ]);

        $session = ['login_user' => ['id' => $admin->id]];
        $generatedCodeResponse = $this->withSession($session)
            ->postJson(route('superadmin.reset-bnh-helper-checks.code'))
            ->assertOk()
            ->assertJsonStructure(['code', 'expires_in_seconds'])
            ->assertJsonPath('expires_in_seconds', 45);
        $firstCode = json_decode($generatedCodeResponse->getContent(), true, flags: JSON_THROW_ON_ERROR)['code'];
        $replacementCodeResponse = $this->withSession($session)
            ->postJson(route('superadmin.reset-bnh-helper-checks.code'))
            ->assertOk();
        $confirmation = json_decode($replacementCodeResponse->getContent(), true, flags: JSON_THROW_ON_ERROR)['code'];
        $this->assertNotSame($firstCode, $confirmation);
        $this->withSession($session)
            ->postJson(route('superadmin.reset-bnh-helper-checks'), ['confirmation' => $confirmation])
            ->assertOk()
            ->assertJsonPath('affected', 1);
        $this->assertDatabaseHas('records', [
            'id' => $checked->id,
            'status' => 'MK',
            'monthly_code' => 'MK: E',
            'is_valid1' => false,
            'is_valid2' => false,
        ]);
        $this->assertDatabaseHas('records', [
            'id' => $unchecked->id,
            'status' => 'UWP',
            'is_valid1' => false,
            'is_valid2' => false,
        ]);
        $this->assertDatabaseHas('system_maintenance_audits', [
            'action' => 'reset_bnh_helper_checks',
            'affected_count' => 1,
        ]);
        $this->assertDatabaseMissing('record_audit_logs', [
            'record_id' => $checked->id,
            'action' => 'updated',
        ]);

        $secretary = User::query()->create([
            'gmail' => 'bnh.secretary@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->postJson(route('superadmin.reset-bnh-helper-checks.code'))
            ->assertForbidden();
    }

    public function test_bnh_reset_code_reports_empty_checks_and_rejects_unissued_codes(): void
    {
        $admin = User::query()->create([
            'gmail' => 'bnh.empty@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $session = ['login_user' => ['id' => $admin->id]];

        $this->withSession($session)
            ->postJson(route('superadmin.reset-bnh-helper-checks.code'))
            ->assertStatus(409)
            ->assertJsonPath('empty', true)
            ->assertJsonPath('message', 'BNH CHECKS is currently Empty.');
        $this->withSession($session)
            ->postJson(route('superadmin.reset-bnh-helper-checks'), ['confirmation' => 'asqlkgjs8579'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation');
    }

    public function test_bnh_reset_code_expires_after_45_seconds(): void
    {
        $admin = User::query()->create([
            'gmail' => 'bnh.expiry@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        Record::query()->create([
            'full_name' => 'Has BNH checks',
            'is_valid1' => true,
            'is_valid2' => false,
        ]);
        $session = ['login_user' => ['id' => $admin->id]];
        Carbon::setTestNow(Carbon::parse('2026-10-02 10:00:00'));

        $generatedCodeResponse = $this->withSession($session)
            ->postJson(route('superadmin.reset-bnh-helper-checks.code'))
            ->assertOk();
        $confirmation = json_decode($generatedCodeResponse->getContent(), true, flags: JSON_THROW_ON_ERROR)['code'];
        Carbon::setTestNow(Carbon::parse('2026-10-02 10:00:45'));

        $this->withSession($session)
            ->postJson(route('superadmin.reset-bnh-helper-checks'), ['confirmation' => $confirmation])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation');
        $this->assertDatabaseHas('records', ['full_name' => 'Has BNH checks', 'is_valid1' => true]);

        Carbon::setTestNow();
    }

    public function test_r402_hakbangs_are_managed_separately_for_ms_and_uwp(): void
    {
        $admin = User::query()->create([
            'gmail' => 'hakbang.admin@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $session = ['login_user' => ['id' => $admin->id]];

        $this->withSession($session)
            ->postJson(route('superadmin.r4-02.hakbangs.store'), [
                'status' => 'MS',
                'code' => '1-7',
                'description' => 'Dinalaw, Pinayuhan, Pinanalangin',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'MS')
            ->assertJsonPath('data.code', '1-7');

        $this->withSession($session)
            ->postJson(route('superadmin.r4-02.hakbangs.store'), [
                'status' => 'UWP',
                'code' => '1-7',
                'description' => 'UWP hakbang description',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'UWP');

        $msHakbang = R402Hakbang::query()->where('status', 'MS')->firstOrFail();
        $this->withSession($session)
            ->putJson(route('superadmin.r4-02.hakbangs.update', $msHakbang), [
                'status' => 'MS',
                'code' => '1-8',
                'description' => 'Updated MS description',
            ])
            ->assertOk()
            ->assertJsonPath('data.code', '1-8');

        $this->assertDatabaseHas('r4_02_hakbangs', [
            'status' => 'MS',
            'code' => '1-8',
            'description' => 'Updated MS description',
        ]);
        $this->assertDatabaseHas('r4_02_hakbangs', [
            'status' => 'UWP',
            'code' => '1-7',
            'description' => 'UWP hakbang description',
        ]);

        $this->withSession($session)
            ->deleteJson(route('superadmin.r4-02.hakbangs.destroy', $msHakbang))
            ->assertOk();
        $this->assertDatabaseMissing('r4_02_hakbangs', ['id' => $msHakbang->id]);
    }

    public function test_r402_hakbangs_reject_duplicate_codes_per_status_and_invalid_values(): void
    {
        $admin = User::query()->create([
            'gmail' => 'hakbang.validation@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $session = ['login_user' => ['id' => $admin->id]];
        $payload = ['status' => 'MS', 'code' => '1-7', 'description' => 'First description'];

        $this->withSession($session)
            ->postJson(route('superadmin.r4-02.hakbangs.store'), $payload)
            ->assertCreated();
        $this->withSession($session)
            ->postJson(route('superadmin.r4-02.hakbangs.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->withSession($session)
            ->postJson(route('superadmin.r4-02.hakbangs.store'), [
                'status' => 'MK',
                'code' => 'invalid',
                'description' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'code', 'description']);
    }

    public function test_only_head_secretary_can_manage_r402_hakbangs(): void
    {
        $secretary = User::query()->create([
            'gmail' => 'hakbang.secretary@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->postJson(route('superadmin.r4-02.hakbangs.store'), [
                'status' => 'MS',
                'code' => '1-7',
                'description' => 'Unauthorized description',
            ])
            ->assertForbidden();
    }
}
