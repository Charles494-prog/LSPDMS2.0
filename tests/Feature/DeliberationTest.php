<?php

namespace Tests\Feature;

use App\Models\MonthlyMovementSession;
use App\Models\MonthlyRecordSnapshot;
use App\Models\MonthlySessionActivity;
use App\Models\MonthlySessionMovement;
use App\Models\Purok;
use App\Models\R401Snapshot;
use App\Models\Record;
use App\Models\RecordAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliberationTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_is_protected_and_shows_registry_boxes_and_rfid_option(): void
    {
        $user = $this->makeSecretary();
        Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Central',
            'grupo_start' => 1,
            'grupo_end' => 3,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.deliberation'))
            ->assertOk()
            ->assertSee('Deliberation Form')
            ->assertSee('data-registry-character="12"', false)
            ->assertSee('data-suggestions-url=', false)
            ->assertSee('data-registry-check-url=', false)
            ->assertSee('id="addSuggestions"', false)
            ->assertSee('id="registryDuplicatePopup"', false)
            ->assertDontSee('scheduleSuggestions(registryInputs)', false)
            ->assertSee('registryInputs.classList.toggle(\'is-disabled\', rfid.checked || Boolean(selectedAddRecord))', false)
            ->assertSee('This member has RFID instead of a registry number')
            ->assertSee('Select Grupo')
            ->assertSee('>Central (1-3)</option>', false)
            ->assertDontSee('1 - Central', false)
            ->assertSee('id="delib-description"', false)
            ->assertDontSee('<option value="E">E</option>', false)
            ->assertSee('new Map(result.records.map(record => [record.id, record]))', false)
            ->assertSee('activeSubtractSearch?.abort()', false)
            ->assertSee('id="monthlySessionPrompt"', false)
            ->assertDontSee('window.confirm(', false)
            ->assertDontSee('window.prompt(', false)
            ->assertDontSee('window.alert(', false)
            ->assertSee('>Add</button>', false)
            ->assertSee('>Subtract</button>', false)
            ->assertSee('>Modify</a>', false);

        $unauthorized = $this->makeSecretary(['form_generator_allow' => false]);
        $this->withSession(['login_user' => ['id' => $unauthorized->id]])
            ->get(route('forms.monthly.deliberation'))
            ->assertForbidden();
    }

    public function test_monthly_finish_closes_activity_dialog_before_confirmations_and_loading(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();

        $html = $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.deliberation'))
            ->assertOk()
            ->assertSee("Yay, I understand there's no redos!", false)
            ->assertSee('blockerTitle.textContent = `Monthly ${periodLabel} done`', false)
            ->assertSee('successAcknowledge.addEventListener', false)
            ->getContent();
        $finishHandler = strpos($html, "finish.addEventListener('click', async () => {");
        $dialogClose = strpos($html, 'dialog.close();', $finishHandler);
        $warnings = strpos($html, 'const warnings = [', $finishHandler);
        $confirmation = strpos($html, 'if (!confirmation) return;', $warnings);
        $showLoading = strpos($html, 'showFinishing();', $confirmation);

        $this->assertNotFalse($finishHandler);
        $this->assertNotFalse($confirmation);
        $this->assertNotFalse($dialogClose);
        $this->assertNotFalse($warnings);
        $this->assertNotFalse($showLoading);
        $this->assertLessThan($warnings, $dialogClose);
        $this->assertLessThan($showLoading, $dialogClose);
        $this->assertStringNotContainsString('setTimeout(resolve, 2200)', $html);
    }

    public function test_monthly_changes_require_an_explicitly_started_session(): void
    {
        $user = User::query()->create([
            'gmail' => 'session.required@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
        ]);
        $this->makePurok();
        $session = ['login_user' => ['id' => $user->id]];
        $record = Record::query()->create([
            'full_name' => 'Session Required Member',
            'status' => 'MS',
            'purok' => '1',
            'grupo' => '1',
        ]);

        $this->withSession($session)
            ->get(route('forms.monthly.deliberation'))
            ->assertOk()
            ->assertSee('data-session-active="false"', false)
            ->assertSee('Start the monthly session using the session control', false)
            ->assertSee('id="delibSubmit" type="submit" disabled', false)
            ->assertSee('type="submit" class="delib-submit" id="subtractSubmit" disabled', false);
        $this->withSession($session)
            ->get(route('forms.monthly.modify'))
            ->assertOk()
            ->assertSee('data-session-active="false"', false)
            ->assertSee('Start the monthly session using the session control', false);

        $this->withSession($session)
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');
        $this->withSession($session)
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $record->id,
                'code' => 'D',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');
        $this->withSession($session)
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $record->id,
                'action' => 'status',
                'status' => 'UWP',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');
        $this->assertDatabaseHas('records', ['id' => $record->id, 'status' => 'MS']);
        $this->assertDatabaseCount('monthly_session_movements', 0);
        $this->assertDatabaseCount('records', 1);

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', 'Asia/Manila'));
        $this->withSession($session)
            ->postJson(route('forms.monthly.session.start'))
            ->assertOk()
            ->assertJsonPath('session.movement_count', 0)
            ->assertJsonPath('session.period_month', 'November 2026');
        $this->withSession($session)
            ->get(route('forms.monthly.deliberation'))
            ->assertOk()
            ->assertSee('data-session-active="true"', false)
            ->assertDontSee('id="delibSubmit" type="submit" disabled', false)
            ->assertDontSee('id="subtractSubmit" disabled', false);
        $this->withSession($session)
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload())
            ->assertCreated();
        $this->assertDatabaseCount('monthly_session_movements', 0);
        Carbon::setTestNow();
    }

    public function test_monthly_session_is_available_once_per_period_and_changes_period_after_midmonth(): void
    {
        config(['session.idle_timeout' => 0]); // the clock jumps days ahead within one signed-in session
        Storage::fake('local');
        $user = User::query()->create([
            'gmail' => 'period.session@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
        ]);
        $session = ['login_user' => ['id' => $user->id]];
        $headSession = ['login_user' => ['id' => User::query()->create([
            'gmail' => 'period.head@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ])->id, 'tungkulin' => 'Head Secretary']];

        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', 'Asia/Manila'));
        $this->withSession($session)
            ->getJson(route('forms.monthly.session.status'))
            ->assertOk()
            ->assertJsonPath('start_availability.allowed', true)
            ->assertJsonPath('start_availability.target_month', 'October 2026');
        $this->withSession($session)
            ->postJson(route('forms.monthly.session.start'))
            ->assertOk()
            ->assertJsonPath('session.period_month', 'October 2026');
        $this->withSession($session)->postJson(route('forms.monthly.session.finish'))->assertForbidden();
        $this->withSession($headSession)->postJson(route('forms.monthly.session.finish'))->assertOk();

        // One session per period month: from the 15th the target is the next month,
        // even though a session was already started this calendar month.
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'Asia/Manila'));
        $this->withSession($session)
            ->postJson(route('forms.monthly.session.start'))
            ->assertOk()
            ->assertJsonPath('session.period_month', 'November 2026');
        $this->withSession($headSession)->postJson(route('forms.monthly.session.finish'))->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-14 12:00:00', 'Asia/Manila'));
        $this->withSession($session)
            ->postJson(route('forms.monthly.session.start'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', 'Asia/Manila'));
        $this->withSession($session)
            ->postJson(route('forms.monthly.session.start'))
            ->assertOk()
            ->assertJsonPath('session.period_month', 'December 2026');
        $this->withSession($headSession)
            ->postJson(route('forms.monthly.session.finish'))
            ->assertOk();
        $this->withSession($session)
            ->postJson(route('forms.monthly.session.start'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');
        Carbon::setTestNow();
    }

    public function test_monthly_modify_cannot_change_mk_status_to_ms_or_uwp(): void
    {
        $user = $this->makeSecretary();
        $purok = Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Purok 1',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);
        $member = Record::query()->create([
            'full_name' => 'MK Status Member',
            'status' => 'MK',
            'monthly_code' => null,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'status',
                'status' => 'MS',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'status',
                'status' => 'MS',
                'purok_id' => $purok->id,
                'grupo' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'status',
                'status' => 'MS',
                'confirmed_mk_demotion' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('records', [
            'id' => $member->id,
            'status' => 'MK',
            'purok' => null,
            'grupo' => null,
            'monthly_code' => null,
        ]);
        $this->assertDatabaseCount('monthly_session_movements', 0);
        $this->assertDatabaseCount('monthly_session_activities', 0);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'status',
                'status' => 'UWP',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('monthly_record_movement_histories', 0);
    }

    public function test_monthly_modify_status_change_records_previous_code_and_sets_new_status_code(): void
    {
        $user = $this->makeSecretary();
        $member = Record::query()->create([
            'full_name' => 'Juan Dela Cruz',
            'registry' => 'JUAN123456789',
            'gender' => 'M',
            'kapisanan' => 'K',
            'date_of_baptism' => '2001-02-03',
            'evangelist' => 'Juan Evangelist',
            'purok' => '1',
            'grupo' => '1',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'status',
                'status' => 'UWP',
            ])
            ->assertOk()
            ->assertJsonPath('record.status', 'UWP')
            ->assertJsonPath('record.monthly_code', 'UWP: B')
            ->assertJsonPath('message', 'Status updated. Current monthly code: UWP: B; movement is in the active session.');

        $this->assertDatabaseHas('records', [
            'id' => $member->id,
            'full_name' => 'Juan Dela Cruz',
            'purok' => '1',
            'grupo' => '1',
            'status' => 'UWP',
            'monthly_code' => 'UWP: B',
        ]);
        $this->assertDatabaseHas('monthly_session_movements', [
            'record_id' => $member->id,
            'full_name' => 'Juan Dela Cruz',
            'registry' => 'JUAN123456789',
            'gender' => 'M',
            'kapisanan' => 'K',
            'evangelist' => 'Juan Evangelist',
            'previous_purok' => '1',
            'previous_grupo' => '1',
            'action' => 'status',
            'status' => 'MS',
            'monthly_code' => 'MS: G',
            'destination_description' => 'MS -> UWP',
        ]);
        $history = MonthlySessionMovement::query()->where('record_id', $member->id)->firstOrFail();
        $this->assertSame('2001-02-03', $history->date_of_baptism?->format('Y-m-d'));
        $this->assertDatabaseCount('monthly_record_code_histories', 0);
        $this->assertDatabaseCount('permanent_history', 0);
    }

    public function test_monthly_modify_to_mk_stores_previous_e_code_and_leaves_record_monthly_code_empty(): void
    {
        $user = $this->makeSecretary();
        $member = Record::query()->create([
            'full_name' => 'UWP to MK Member',
            'purok' => '2',
            'grupo' => '4',
            'status' => 'UWP',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'status',
                'status' => 'MK',
                'endorsement_reason' => 'Reviewed for R2-10.',
            ])
            ->assertOk()
            ->assertJsonPath('record.status', 'MK')
            ->assertJsonPath('record.monthly_code', null)
            ->assertJsonPath('message', 'Status updated to MK. Movement is in the active session.');

        $this->assertDatabaseHas('records', [
            'id' => $member->id,
            'status' => 'MK',
            'monthly_code' => null,
        ]);
        $this->assertStringNotContainsString('Reviewed for R2-10.', $member->fresh()->log_section);
        $this->assertStringContainsString(
            now()->timezone('Asia/Manila')->format('d-m-Y').': (Reviewed for R2-10.)',
            $member->fresh()->reason_of_r2_10_endorsement
        );
        $this->assertDatabaseHas('monthly_session_movements', [
            'record_id' => $member->id,
            'action' => 'status',
            'status' => 'UWP',
            'previous_purok' => '2',
            'previous_grupo' => '4',
            'monthly_code' => 'UWP: E',
            'destination_description' => 'UWP -> MK',
        ]);
        $this->assertDatabaseCount('monthly_record_code_histories', 0);
    }

    public function test_monthly_modify_only_accepts_active_records_without_monthly_code_and_disallows_grupo_only_moves(): void
    {
        $user = $this->makeSecretary();
        $source = Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Purok 1',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);
        $destination = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2',
            'grupo_start' => 1,
            'grupo_end' => 6,
        ]);
        $member = Record::query()->create([
            'full_name' => 'Current Transfer Member',
            'purok' => '1',
            'grupo' => '2',
            'status' => 'UWP',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $source->id,
                'grupo' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purok_id');

        $member->monthly_code = 'UWP: B';
        $member->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $destination->id,
                'grupo' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('record_id');

        $member->monthly_code = null;
        $member->status = 'INACTIVE';
        $member->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.monthly.modify.search', ['q' => 'Current Transfer']))
            ->assertOk()
            ->assertJsonPath('records.0.status', 'INACTIVE');
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'status',
                'status' => 'MS',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('record_id');
    }

    public function test_subtract_blocks_records_with_existing_monthly_code(): void
    {
        $user = $this->makeSecretary();
        $record = Record::query()->create([
            'full_name' => 'Already Moved Member',
            'status' => 'UWP',
            'monthly_code' => 'UWP: C+',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $record->id,
                'code' => 'D',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This record already has a monthly movement code. Clear the previous code in Inquire / Modify before proceeding.');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'status' => 'UWP',
            'monthly_code' => 'UWP: C+',
        ]);
    }

    public function test_monthly_modify_page_is_protected_and_offers_configured_puroks(): void
    {
        $user = $this->makeSecretary();
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2a',
            'grupo_start' => 1,
            'grupo_end' => 6,
        ]);
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2b',
            'grupo_start' => 7,
            'grupo_end' => 11,
        ]);
        Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Purok Uno',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.modify'))
            ->assertOk()
            ->assertSee('Monthly Modify')
            ->assertSee(route('forms.monthly.deliberation').'#add', false)
            ->assertSee(route('forms.monthly.deliberation').'#subtract', false)
            ->assertSee('aria-current="page">Modify</a>', false)
            ->assertSee('Purok 2a')
            ->assertSee('Purok 2b')
            ->assertSee('Purok Uno (1-24)')
            ->assertSee('Purok 2a (1-6)')
            ->assertSee('Purok 2b (7-11)')
            ->assertSee('id="monthlyModifyEndorsementReason"', false)
            ->assertSee('id="monthlyModifyConfirmDialog"', false)
            ->assertSee('id="monthlyModifySuccessDialog"', false)
            ->assertSee('resetModifyForm = () =>', false)
            ->assertDontSee('all Grupos')
            ->assertSee(route('forms.monthly.modify.search'), false);

        $unauthorized = $this->makeSecretary(['form_generator_allow' => false]);
        $this->withSession(['login_user' => ['id' => $unauthorized->id]])
            ->get(route('forms.monthly.modify'))
            ->assertForbidden();
    }

    public function test_add_uses_the_selected_split_purok_grupo_range(): void
    {
        $user = $this->makeSecretary();
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2a',
            'grupo_start' => 1,
            'grupo_end' => 6,
        ]);
        $purok2b = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2b',
            'grupo_start' => 7,
            'grupo_end' => 11,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.deliberation'))
            ->assertOk()
            ->assertSee('Purok 2a (1-6)')
            ->assertSee('Purok 2b (7-11)');

        $payload = $this->validPayload([
            'full_name' => 'Purok 2b Member',
            'registry' => 'purok2b123456',
            'purok_id' => $purok2b->id,
            'grupo' => 8,
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseHas('records', [
            'full_name' => 'PUROK 2B MEMBER',
            'registry' => 'PUROK2B123456',
            'purok' => '2',
            'grupo' => '8',
        ]);

        $payload['full_name'] = 'Invalid Purok 2b Member';
        $payload['registry'] = 'PUROK2B123457';
        $payload['grupo'] = 6;
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupo');
    }

    public function test_monthly_modify_limits_complete_purok_to_grupos_one_through_twenty_four(): void
    {
        $user = $this->makeSecretary();
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);
        $completePurok = Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Purok Uno',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);
        $member = Record::query()->create([
            'full_name' => 'Complete Purok Member',
            'purok' => '2',
            'grupo' => '1',
            'status' => 'MS',
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $completePurok->id,
                'grupo' => 25,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupo');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $completePurok->id,
                'grupo' => 24,
            ])
            ->assertOk()
            ->assertJsonPath('record.grupo', '24');
    }

    public function test_monthly_modify_uses_each_configured_range_even_if_complete_flag_is_set(): void
    {
        $user = $this->makeSecretary();
        $source = Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Purok 1',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);
        $purok3 = Purok::query()->create([
            'purok_number' => 3,
            'purok_name' => 'Purok 3',
            'grupo_start' => 1,
            'grupo_end' => 11,
            'full_purok' => true,
        ]);
        Purok::query()->create([
            'purok_number' => 4,
            'purok_name' => 'Purok 4',
            'grupo_start' => 1,
            'grupo_end' => 9,
            'full_purok' => true,
        ]);
        $member = Record::query()->create([
            'full_name' => 'Configured Range Transfer',
            'purok' => (string) $source->purok_number,
            'grupo' => '2',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.modify'))
            ->assertOk()
            ->assertSee('Purok 3 (1-11)')
            ->assertSee('Purok 4 (1-9)')
            ->assertDontSee('Purok 3 (1-24)')
            ->assertDontSee('Purok 4 (1-24)');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $purok3->id,
                'grupo' => 12,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupo');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $purok3->id,
                'grupo' => 11,
            ])
            ->assertOk()
            ->assertJsonPath('record.grupo', '11');
    }

    public function test_monthly_modify_moves_member_within_split_destination_range_and_appends_log(): void
    {
        $user = $this->makeSecretary();
        Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Purok 1',
            'grupo_start' => 1,
            'grupo_end' => 12,
        ]);
        $destination = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2b',
            'grupo_start' => 7,
            'grupo_end' => 11,
        ]);
        $member = Record::query()->create([
            'full_name' => 'Monthly Transfer Member',
            'registry' => 'TRANSFER12345',
            'gender' => 'F',
            'kapisanan' => 'B',
            'date_of_baptism' => '1998-07-09',
            'evangelist' => 'Maria Evangelist',
            'purok' => '1',
            'grupo' => '4',
            'status' => 'MS',
            'log_section' => '01/01/2025: Existing entry,',
        ]);
        $today = now()->timezone('Asia/Manila')->format('d/m/Y');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.monthly.modify.search', ['q' => 'Monthly Transfer']))
            ->assertOk()
            ->assertJsonPath('records.0.name', 'Monthly Transfer Member')
            ->assertJsonPath('records.0.purok_name', 'Purok 1');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $destination->id,
                'grupo' => 6,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupo');

        $this->withSession(['login_user' => ['id' => $user->id, 'gmail' => $user->gmail]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $destination->id,
                'grupo' => 8,
                'description' => 'Approved transfer',
            ])
            ->assertOk()
            ->assertJsonPath('record.purok', '2')
            ->assertJsonPath('record.grupo', '8');

        $this->assertDatabaseHas('records', [
            'id' => $member->id,
            'purok' => '2',
            'grupo' => '8',
            'status' => 'MS',
            'monthly_code' => 'MS: C+',
            'log_section' => '01/01/2025: Existing entry, '.$today.': Monthly modify: Purok 1 Grupo 4 to Purok 2b Grupo 8 (MS: C- / MS: C+): Approved transfer,',
        ]);
        $this->assertDatabaseHas('monthly_session_movements', [
            'record_id' => $member->id,
            'full_name' => 'Monthly Transfer Member',
            'registry' => 'TRANSFER12345',
            'previous_purok' => '1',
            'previous_grupo' => '4',
            'action' => 'transfer',
            'status' => 'MS',
            'monthly_code' => 'MS: C- / MS: C+',
            'destination_description' => '1-4 -> 2-8',
            'changed_by' => $user->gmail,
            'gender' => 'F',
            'kapisanan' => 'B',
            'evangelist' => 'Maria Evangelist',
        ]);
        $history = MonthlySessionMovement::query()->where('record_id', $member->id)->firstOrFail();
        $this->assertSame('1998-07-09', $history->date_of_baptism?->format('Y-m-d'));
        $this->assertDatabaseCount('monthly_record_code_histories', 0);
        $this->assertDatabaseCount('permanent_history', 0);
    }

    public function test_registry_must_be_exactly_thirteen_characters_unless_rfid_is_selected(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        $payload = $this->validPayload(['registry' => '12345']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registry');

        $payload = $this->validPayload([
            'registry' => 'RFID',
            'is_rfid' => true,
            'full_name' => 'RFID Member',
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('record.registry', 'RFID');
    }

    public function test_duplicate_names_and_registry_values_are_reported_and_cannot_be_added_again(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        Record::query()->create([
            'full_name' => 'Existing Member',
            'registry' => 'ABCDEFGHIJKLM',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.duplicates'), [
                'full_name' => ' existing member ',
                'registry' => '',
                'is_rfid' => false,
            ])
            ->assertOk()
            ->assertJsonPath('duplicates.0.name', 'Existing Member');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'full_name' => 'Another Name',
                'registry' => 'ABCDEFGHIJKLM',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('full_name');
    }

    public function test_rfid_is_not_used_for_registry_duplicate_matching(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        Record::query()->create([
            'full_name' => 'Other RFID Member',
            'registry' => 'RFID',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'full_name' => 'New RFID Member',
                'registry' => 'RFID',
                'is_rfid' => true,
            ]))
            ->assertCreated();
    }

    public function test_add_accepts_missing_baptism_date_evangelist_and_grupo(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'full_name' => 'Optional Information Member',
                'registry' => 'ABCDEFGHIJKLM',
                'grupo' => null,
                'date_of_baptism' => null,
                'evangelist' => null,
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('records', [
            'full_name' => 'OPTIONAL INFORMATION MEMBER',
            'purok' => '1',
            'grupo' => null,
            'date_of_baptism' => null,
            'evangelist' => null,
            'gender' => 'L',
            'kapisanan' => 'K',
            'monthly_code' => 'MS: B',
            'log_section' => now()->timezone('Asia/Manila')->format('d-m-Y').': MS: B,',
        ]);
        $this->assertSame(
            '2026-10-01',
            Record::query()->where('full_name', 'OPTIONAL INFORMATION MEMBER')->firstOrFail()->date_of_entry->format('Y-m-d')
        );
    }

    public function test_add_sets_monthly_code_from_the_selected_status(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());

        foreach (['MS', 'UWP', 'MK'] as $status) {
            $name = "{$status} Monthly Code Member";
            $this->withSession(['login_user' => ['id' => $user->id]])
                ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                    'full_name' => $name,
                    'registry' => str_pad((string) (Record::query()->count() + 1), 13, '0', STR_PAD_LEFT),
                    'status' => $status,
                ]))
                ->assertCreated();

            $this->assertDatabaseHas('records', [
                'full_name' => mb_strtoupper($name),
                'status' => $status,
                'monthly_code' => "{$status}: B",
            ]);
            $this->assertSame(
                '2026-10-01',
                Record::query()->where('full_name', mb_strtoupper($name))->firstOrFail()->date_of_entry->format('Y-m-d')
            );
        }
    }

    public function test_add_appends_optional_description_to_log_section(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        $today = now()->timezone('Asia/Manila')->format('d-m-Y');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'description' => 'Added after review',
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('records', [
            'full_name' => 'NEW MEMBER',
            'log_section' => $today.': MS: B, '.$today.': Added after review,',
        ]);
    }

    public function test_add_persists_female_and_buklod_codes_and_rfid_value(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'full_name' => 'Female RFID Buklod',
                'registry' => 'RFID',
                'is_rfid' => true,
                'gender' => 'Female',
                'kapisanan' => 'Buklod',
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('records', [
            'full_name' => 'FEMALE RFID BUKLOD',
            'registry' => 'RFID',
            'gender' => 'B',
            'kapisanan' => 'B',
        ]);
        $audit = RecordAuditLog::query()->where('action', 'created')->sole();
        $this->assertSame($user->gmail, $audit->actor_email);
        $this->assertSame('Added FEMALE RFID BUKLOD as MS.', $audit->summary);
    }

    public function test_add_suggestions_find_partial_and_similarly_spelled_names_only(): void
    {
        $user = $this->makeSecretary();
        $inactive = Record::query()->create([
            'full_name' => 'Eniron Mercado',
            'registry' => 'ABCD123456789',
            'status' => 'INACTIVE',
        ]);
        Record::query()->create([
            'full_name' => 'Eneron Member',
            'registry' => 'ZXCV987654321',
            'status' => 'UWP',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.monthly.deliberation.suggestions', ['q' => 'eni']))
            ->assertOk()
            ->assertJsonPath('records.0.id', $inactive->id)
            ->assertJsonPath('records.0.full_name', 'Eniron Mercado')
            ->assertJsonPath('records.0.status', 'INACTIVE')
            ->assertJsonPath('records.1.full_name', 'Eneron Member')
            ->assertJsonCount(2, 'records');
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.monthly.deliberation.suggestions', ['q' => '1234']))
            ->assertOk()
            ->assertJsonCount(0, 'records');
    }

    public function test_registry_checker_reports_exact_duplicates_but_ignores_rfid(): void
    {
        $user = $this->makeSecretary();
        $existing = Record::query()->create([
            'full_name' => 'Existing Registry Member',
            'registry' => 'ABCD123456789',
            'status' => 'UWP',
        ]);
        Record::query()->create([
            'full_name' => 'RFID Member',
            'registry' => 'RFID',
            'status' => 'INACTIVE',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.registry-check'), [
                'registry' => 'abcd123456789',
                'is_rfid' => false,
            ])
            ->assertOk()
            ->assertJsonPath('duplicates.0.id', $existing->id)
            ->assertJsonCount(1, 'duplicates');
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.registry-check'), [
                'registry' => 'ABCD123456789',
                'is_rfid' => true,
            ])
            ->assertOk()
            ->assertJsonCount(0, 'duplicates');
    }

    public function test_inactive_matching_record_can_be_reactivated_without_creating_a_duplicate(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        $existing = Record::query()->create([
            'full_name' => 'Inactive Member',
            'registry' => 'ABCDEFGHIJKLM',
            'status' => 'INACTIVE',
            'gender' => 'L',
            'purok' => '9',
            'grupo' => '7',
            'kapisanan' => 'K',
            'date_of_baptism' => '2000-01-01',
            'evangelist' => 'Original Evangelist',
            'date_of_birth' => '1980-05-06',
            'is_married' => false,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.session.start'))
            ->assertOk();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'full_name' => 'Inactive Member',
                'registry' => 'ABCDEFGHIJKLM',
                'status' => 'UWP',
                'reuse_record_id' => $existing->id,
            ]))
            ->assertOk()
            ->assertJsonPath('reused', true)
            ->assertJsonPath('record.status', 'UWP');

        $this->assertDatabaseHas('records', [
            'id' => $existing->id,
            'status' => 'UWP',
            'monthly_code' => 'UWP: B',
            'gender' => 'L',
            'purok' => '9',
            'grupo' => '7',
            'kapisanan' => 'K',
            'evangelist' => 'Original Evangelist',
            'date_of_birth' => '1980-05-06 00:00:00',
            'date_of_baptism' => '2000-01-01 00:00:00',
        ]);
        $this->assertDatabaseMissing('monthly_session_movements', ['record_id' => $existing->id]);
        $this->assertDatabaseCount('records', 1);
        $this->assertDatabaseHas('records', [
            'id' => $existing->id,
            'status' => 'UWP',
            'monthly_code' => 'UWP: B',
        ]);
    }

    public function test_add_rejects_inactive_as_a_new_status_and_blocks_records_with_existing_monthly_codes(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload(['status' => 'INACTIVE']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        Record::query()->create([
            'full_name' => 'Already Deliberated Member',
            'registry' => 'ABCDEFGHIJKLM',
            'status' => 'MS',
            'monthly_code' => 'MS: B',
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'full_name' => 'Already Deliberated Member',
                'registry' => 'ABCDEFGHIJKLM',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('full_name');
    }

    public function test_kapisanan_is_derived_from_marriage_and_exact_age_boundary(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());

        foreach ([
            ['Young Member', '2009-10-02', 0, 'binhi'],
            ['Eighteen Member', '2008-10-01', 0, 'K'],
            ['Married Member', '2009-10-02', 1, 'B'],
        ] as [$name, $birthDate, $isMarried, $expectedKapisanan]) {
            $this->withSession(['login_user' => ['id' => $user->id]])
                ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                    'full_name' => $name,
                    'registry' => str_pad((string) (Record::query()->count() + 1), 13, '0', STR_PAD_LEFT),
                    'date_of_birth' => $birthDate,
                    'is_married' => $isMarried,
                    'kapisanan' => 'Suliranin',
                ]))
                ->assertCreated();

            $this->assertDatabaseHas('records', [
                'full_name' => mb_strtoupper($name),
                'kapisanan' => $expectedKapisanan,
                'is_married' => $isMarried,
            ]);
        }
    }

    public function test_monthly_add_defaults_unanswered_marital_status_to_not_married(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();
        $payload = $this->validPayload([
            'full_name' => 'Default Not Married Member',
            'registry' => 'DEFAULT000001',
            'date_of_birth' => '2000-01-01',
        ]);
        unset($payload['is_married']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseHas('records', [
            'full_name' => 'DEFAULT NOT MARRIED MEMBER',
            'is_married' => 0,
            'kapisanan' => 'K',
        ]);
    }

    public function test_monthly_add_accepts_only_binary_marital_values(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'is_married' => 'yes',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_married');
    }

    public function test_monthly_add_marks_buklod_as_married_even_without_birthday(): void
    {
        $user = $this->makeSecretary();
        $this->makePurok();

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'full_name' => 'Buklod Marriage Member',
                'date_of_birth' => null,
                'is_married' => 0,
                'kapisanan' => 'Buklod',
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('records', [
            'full_name' => 'BUKLOD MARRIAGE MEMBER',
            'kapisanan' => 'B',
            'is_married' => 1,
        ]);
    }

    public function test_subtract_search_matches_name_and_registry_and_returns_readonly_record_details(): void
    {
        $user = $this->makeSecretary();
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2a',
            'grupo_start' => 1,
            'grupo_end' => 6,
        ]);
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2b',
            'grupo_start' => 7,
            'grupo_end' => 11,
        ]);
        Record::query()->create([
            'full_name' => 'Searchable Member',
            'registry' => 'ZXCVBNMASDFGH',
            'gender' => 'L',
            'purok' => '2',
            'grupo' => '8',
            'kapisanan' => 'K',
            'status' => 'uwp',
            'monthly_code' => 'UWP: D',
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.monthly.deliberation.subtract.search', ['q' => 'zxcv']))
            ->assertOk()
            ->assertJsonPath('records.0.full_name', 'Searchable Member')
            ->assertJsonPath('records.0.gender', 'Male')
            ->assertJsonPath('records.0.kapisanan', 'Kadiwa')
            ->assertJsonPath('records.0.status', 'UWP')
            ->assertJsonPath('records.0.monthly_code', 'UWP: D')
            ->assertJsonPath('records.0.purok_name', 'Purok 2b');
    }

    public function test_subtract_search_returns_a_record_once_when_both_name_and_registry_match(): void
    {
        $user = $this->makeSecretary();
        Record::query()->create([
            'full_name' => 'Alex Alex Member',
            'registry' => 'ALEX123456789',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.monthly.deliberation.subtract.search', ['q' => 'alex']))
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.full_name', 'Alex Alex Member');
    }

    public function test_subtract_marks_ms_or_uwp_inactive_and_preserves_the_record_with_reason_code(): void
    {
        $user = $this->makeSecretary();
        $record = Record::query()->create([
            'full_name' => 'Member to Subtract',
            'registry' => 'ZXCVBNMASDFGH',
            'status' => 'UWP',
            'reason_of_r2_10_endorsement' => 'Earlier note',
            'log_section' => 'Imported CSV entry,',
        ]);
        $today = now()->timezone('Asia/Manila')->format('d-m-Y');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $record->id,
                'code' => 'DD',
                'description' => 'Moved away',
            ])
            ->assertOk()
            ->assertJsonPath('record.status', 'INACTIVE');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'status' => 'INACTIVE',
            'monthly_code' => 'UWP: DD',
            // The subtract code is logged in log_section only; the R2-10 reason is untouched.
            'reason_of_r2_10_endorsement' => 'Earlier note',
            'log_section' => 'Imported CSV entry, '.$today.': UWP: DD, '.$today.': Moved away,',
        ]);
    }

    public function test_subtract_monthly_code_uses_previous_status_and_selected_removal_code(): void
    {
        $user = $this->makeSecretary();
        foreach (['MS' => 'D', 'UWP' => 'DD'] as $status => $code) {
            $record = Record::query()->create([
                'full_name' => "{$status} Member",
                'status' => $status,
            ]);

            $this->withSession(['login_user' => ['id' => $user->id]])
                ->postJson(route('forms.monthly.deliberation.subtract'), [
                    'record_id' => $record->id,
                    'code' => $code,
                ])
                ->assertOk();

            $this->assertDatabaseHas('records', [
                'id' => $record->id,
                'status' => 'INACTIVE',
                'monthly_code' => "{$status}: {$code}",
            ]);
        }
    }

    public function test_monthly_modify_to_mk_requires_and_saves_an_r2_10_reason(): void
    {
        $user = $this->makeSecretary();
        $record = Record::query()->create([
            'full_name' => 'Modify MK Reason Candidate',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $record->id,
                'action' => 'status',
                'status' => 'MK',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endorsement_reason');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $record->id,
                'code' => 'E',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $record->id,
                'action' => 'status',
                'status' => 'MK',
                'endorsement_reason' => 'Reviewed for R2-10.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'status' => 'MK',
            'monthly_code' => null,
        ]);
        $this->assertStringContainsString(
            now()->timezone('Asia/Manila')->format('d-m-Y').': (Reviewed for R2-10.)',
            $record->fresh()->reason_of_r2_10_endorsement
        );
    }

    public function test_subtract_rejects_inactive_and_mk_records(): void
    {
        $user = $this->makeSecretary();
        $inactive = Record::query()->create(['full_name' => 'Already Inactive', 'status' => 'INACTIVE']);
        $mk = Record::query()->create(['full_name' => 'MK Member', 'status' => 'MK']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $inactive->id,
                'code' => 'D',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Already Inactive.');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $mk->id,
                'code' => 'H',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'MK is not removable.');

        $this->assertDatabaseHas('records', ['id' => $inactive->id, 'status' => 'INACTIVE']);
        $this->assertDatabaseHas('records', ['id' => $mk->id, 'status' => 'MK']);
    }

    public function test_add_and_subtract_are_kept_in_records_until_session_finalization(): void
    {
        Storage::fake('local');
        $user = $this->makeSecretary();
        $purok = $this->makePurok();
        $session = ['login_user' => ['id' => $user->id]];

        $this->withSession($session)
            ->get(route('forms.monthly.session'))
            ->assertOk()
            ->assertSee('Only the Head Secretary can close the monthly session.');
        $this->withSession($session)
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'description' => 'Add note to undo',
            ]))
            ->assertCreated();
        $added = Record::query()->where('full_name', 'NEW MEMBER')->firstOrFail();
        $this->assertSame('MS: B', $added->monthly_code);
        $this->assertDatabaseHas('monthly_session_activities', [
            'record_id' => $added->id,
            'action' => 'add',
            'direction' => 'in',
            'status' => 'MS',
            'monthly_code' => 'MS: B',
            'purok' => (string) $purok->purok_number,
        ]);
        $this->assertStringContainsString('Add note to undo', $added->log_section);
        $this->assertStringContainsString('MS: B', $added->log_section);
        $this->assertDatabaseCount('monthly_session_movements', 0);

        $subtractRecord = Record::query()->create([
            'full_name' => 'Restore Subtract Member',
            'status' => 'UWP',
            'purok' => (string) $purok->purok_number,
            'grupo' => '2',
            'reason_of_r2_10_endorsement' => 'Existing reason',
            'log_section' => 'Existing log,',
        ]);
        $this->withSession($session)
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $subtractRecord->id,
                'code' => 'D',
                'description' => 'Subtract note to undo',
            ])
            ->assertOk();
        $this->assertDatabaseHas('records', [
            'id' => $subtractRecord->id,
            'status' => 'INACTIVE',
            'monthly_code' => 'UWP: D',
        ]);
        $this->assertDatabaseHas('monthly_session_activities', [
            'record_id' => $subtractRecord->id,
            'action' => 'subtract',
            'direction' => 'out',
            'status' => 'UWP',
            'monthly_code' => 'UWP: D',
            'purok' => (string) $purok->purok_number,
        ]);
        $this->assertDatabaseCount('monthly_session_movements', 0);
        $headSecretary = User::query()->create([
            'gmail' => 'undo.finish@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $finishSession = ['login_user' => ['id' => $headSecretary->id]];
        $this->withSession($finishSession)
            ->postJson(route('forms.monthly.session.finish'))
            ->assertOk()
            ->assertJsonPath('movements', 0)
            ->assertJsonPath('coded_records', 2);
        $this->assertDatabaseHas('permanent_history', ['record_id' => $added->id, 'monthly_code' => 'MS: B']);
        $this->assertDatabaseHas('permanent_history', ['record_id' => $subtractRecord->id, 'monthly_code' => 'UWP: D']);
        $this->assertNull($added->fresh()->monthly_code);
        $this->assertNull($subtractRecord->fresh()->monthly_code);
    }

    public function test_transfer_and_status_movements_restore_prior_values_on_undo(): void
    {
        $user = $this->makeSecretary();
        $source = Purok::query()->create(['purok_number' => 1, 'purok_name' => 'Source', 'grupo_start' => 1, 'grupo_end' => 10]);
        $destination = Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Destination', 'grupo_start' => 1, 'grupo_end' => 10]);
        $member = Record::query()->create([
            'full_name' => 'Undo Transfer Member',
            'purok' => '1',
            'grupo' => '4',
            'status' => 'MS',
            'reason_of_r2_10_endorsement' => 'Original endorsement note',
            'log_section' => 'Original log,',
        ]);
        $session = ['login_user' => ['id' => $user->id]];

        $this->withSession($session)
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'transfer',
                'purok_id' => $destination->id,
                'grupo' => 8,
                'description' => 'Transfer note to undo',
            ])
            ->assertOk();
        $transfer = MonthlySessionMovement::query()->where('record_id', $member->id)->firstOrFail();
        $this->assertSame('1-4 -> 2-8', $transfer->destination_description);
        $this->assertSame('Transfer note to undo', $transfer->description);
        $this->assertSame([
            ['MS: C+', 'in', '2', '8'],
            ['MS: C-', 'out', '1', '4'],
        ], MonthlySessionActivity::query()
            ->where('session_movement_id', $transfer->id)
            ->orderBy('direction')
            ->get(['monthly_code', 'direction', 'purok', 'grupo'])
            ->map(fn (MonthlySessionActivity $activity): array => [
                $activity->monthly_code,
                $activity->direction,
                $activity->purok,
                $activity->grupo,
            ])
            ->all());
        $this->assertStringContainsString('Transfer note to undo', $member->fresh()->log_section);
        $this->withSession($session)
            ->get(route('forms.monthly.session'))
            ->assertOk()
            ->assertSee('Undo Transfer Member')
            ->assertSee('1-4 -> 2-8')
            ->assertSee('monthly-session-entries', false);
        $this->withSession($session)
            ->getJson(route('forms.monthly.session.status').'?include_movements=1')
            ->assertOk()
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.action', 'Transfer')
            ->assertJsonCount(2, 'activities.0.entries')
            ->assertJsonPath('activities.0.entries.0.monthly_code', 'MS: C-')
            ->assertJsonPath('activities.0.entries.0.direction', 'out')
            ->assertJsonPath('activities.0.entries.0.name', 'Undo Transfer Member')
            ->assertJsonPath('activities.0.entries.0.location', '1-4')
            ->assertJsonPath('activities.0.entries.1.monthly_code', 'MS: C+')
            ->assertJsonPath('activities.0.entries.1.direction', 'in')
            ->assertJsonPath('activities.0.entries.1.location', '2-8');
        $this->withSession($session)->deleteJson(route('forms.monthly.session.undo', $transfer))->assertOk();
        $this->assertDatabaseMissing('monthly_session_activities', ['session_movement_id' => $transfer->id]);
        $this->assertDatabaseHas('records', [
            'id' => $member->id,
            'purok' => '1',
            'grupo' => '4',
            'status' => 'MS',
            'monthly_code' => null,
            'reason_of_r2_10_endorsement' => 'Original endorsement note',
            'log_section' => 'Original log,',
        ]);

        $this->withSession($session)
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $member->id,
                'action' => 'status',
                'status' => 'UWP',
                'description' => 'Status note to undo',
            ])
            ->assertOk();
        $statusChange = MonthlySessionMovement::query()->where('record_id', $member->id)->firstOrFail();
        $this->assertSame('MS -> UWP', $statusChange->destination_description);
        $this->assertDatabaseHas('monthly_session_activities', [
            'session_movement_id' => $statusChange->id,
            'monthly_code' => 'MS: G',
            'direction' => 'out',
        ]);
        $this->assertDatabaseHas('monthly_session_activities', [
            'session_movement_id' => $statusChange->id,
            'monthly_code' => 'UWP: B',
            'direction' => 'in',
        ]);
        $this->assertSame('Status note to undo', $statusChange->description);
        $this->assertStringContainsString('Status note to undo', $member->fresh()->log_section);
        $this->withSession($session)->deleteJson(route('forms.monthly.session.undo', $statusChange))->assertOk();
        $this->assertDatabaseHas('records', [
            'id' => $member->id,
            'purok' => '1',
            'grupo' => '4',
            'status' => 'MS',
            'monthly_code' => null,
            'reason_of_r2_10_endorsement' => 'Original endorsement note',
            'log_section' => 'Original log,',
        ]);
        $this->assertDatabaseCount('monthly_session_movements', 0);
    }

    public function test_finishing_session_archives_monthly_codes_and_movements_then_snapshots(): void
    {
        Storage::fake('local');
        $user = $this->makeSecretary();
        $purok = $this->makePurok();
        $secondPurokSegment = Purok::query()->create([
            'purok_number' => $purok->purok_number,
            'purok_name' => 'Central B',
            'grupo_start' => 4,
            'grupo_end' => 6,
        ]);
        $record = Record::query()->create([
            'full_name' => 'Finalized Member',
            'registry' => 'FINALIZED12345',
            'gender' => 'L',
            'kapisanan' => 'K',
            'date_of_baptism' => '2000-01-02',
            'evangelist' => 'Evangelist',
            'purok' => (string) $purok->purok_number,
            'grupo' => '2',
            'status' => 'MS',
        ]);
        $otherCodedRecord = Record::query()->create([
            'full_name' => 'Unrelated coded member',
            'status' => 'UWP',
            'monthly_code' => 'UWP: G',
        ]);
        $session = ['login_user' => ['id' => $user->id]];

        $this->withSession($session)
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $record->id,
                'code' => 'DD',
                'description' => 'Final movement',
            ])
            ->assertOk();
        $headSecretary = User::query()->create([
            'gmail' => 'finalize.monthly@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $this->withSession(['login_user' => ['id' => $headSecretary->id, 'tungkulin' => 'Head Secretary']])
            ->postJson(route('forms.monthly.session.finish'))
            ->assertOk()
            ->assertJsonPath('movements', 0)
            ->assertJsonPath('coded_records', 2)
            ->assertJsonPath('cleared_monthly_codes', 2)
            ->assertJsonPath('period_label', now()->timezone('Asia/Manila')->startOfMonth()->addMonthNoOverflow()->format('F Y'))
            ->assertJsonPath('message', 'Monthly '.now()->timezone('Asia/Manila')->startOfMonth()->addMonthNoOverflow()->format('F Y').' finalized.');

        $this->assertDatabaseHas('permanent_history', [
            'record_id' => $record->id,
            'period_month' => now()->timezone('Asia/Manila')->startOfMonth()->addMonthNoOverflow()->toDateString(),
            'action' => 'subtract',
            'full_name' => 'Finalized Member',
            'monthly_code' => 'MS: DD',
            'destination_description' => 'MS -> INACTIVE (DD)',
            'date_of_baptism' => '2000-01-02',
            'evangelist' => 'Evangelist',
        ]);
        $this->assertDatabaseHas('permanent_history', [
            'record_id' => $record->id,
            'monthly_code' => 'MS: DD',
        ]);
        $this->assertNull($record->fresh()->monthly_code);
        $this->assertNull($otherCodedRecord->fresh()->monthly_code);
        $this->assertDatabaseHas('system_maintenance_audits', [
            'action' => 'finish_monthly_clear_codes',
            'affected_count' => 2,
        ]);
        $this->assertDatabaseCount('monthly_session_movements', 0);
        $this->assertDatabaseHas('monthly_movement_sessions', [
            'status' => 'completed',
            'active_key' => null,
        ]);
        $completedSession = MonthlyMovementSession::query()->where('status', 'completed')->firstOrFail();
        $monthlyReport = R401Snapshot::query()->where('session_id', $completedSession->id)->firstOrFail();
        $this->assertSame('Monthly session', $monthlyReport->report_type);
        $this->assertSame('Monthly_'.Carbon::parse($completedSession->period_month)->format('F_Y').'_RESUMEN_MS_UWP.docx', $monthlyReport->file_name);
        $this->assertSame('docx', $monthlyReport->format);
        Storage::disk('local')->assertExists($monthlyReport->file_path);
        $document = new \PharData(Storage::disk('local')->path($monthlyReport->file_path));
        $documentXml = $document['word/document.xml']->getContent();
        $this->assertStringContainsString('BILANG NG NADAGDAG/NABAWAS NA MS/TS AT UWP', $documentXml);
        $this->assertStringContainsString('ULAT UKOL SA SAKOP NA MS / UWP', $documentXml);
        $this->assertStringContainsString('Finalized Member', $documentXml);
        $this->assertStringContainsString('Unrelated coded member', $documentXml);
        $this->assertLessThan(
            strpos($documentXml, 'ULAT UKOL SA SAKOP NA MS / UWP'),
            strpos($documentXml, 'BILANG NG NADAGDAG/NABAWAS NA MS/TS AT UWP')
        );
        $previewPath = preg_replace('/\.docx$/', '.html', $monthlyReport->file_path);
        Storage::disk('local')->assertExists($previewPath);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.r4-01.snapshots.view', $monthlyReport))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Monthly '.$completedSession->period_month->format('F Y'))
            ->assertSee('snapshotResumenHeading')
            ->assertSee('snapshotMSHeading')
            ->assertSee('snapshotUWPHeading');
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.r4-01.snapshots.download', $monthlyReport))
            ->assertOk()
            ->assertDownload($monthlyReport->file_name);
        $this->withSession(['login_user' => ['id' => $headSecretary->id, 'tungkulin' => 'Head Secretary']])
            ->get(route('forms.monthly.r4-01.snapshots', ['year' => Carbon::parse($completedSession->period_month)->format('Y')]))
            ->assertOk()
            ->assertSee($monthlyReport->file_name)
            ->assertSee('Generated on')
            ->assertSee('data-snapshot-view=', false)
            ->assertSee('r401SnapshotViewer', false)
            ->assertSee('Filter snapshots by year')
            ->assertSee('type="month"', false)
            ->assertSee('SUPERADMIN')
            ->assertSee('I understand this permanently deletes both the saved file and its archive entry.')
            ->assertSee('filters.requestSubmit()', false)
            ->assertDontSee('<th>Month</th>', false)
            ->assertDontSee('<th>Kalagayan</th>', false)
            ->assertDontSee('<th>Purok</th>', false)
            ->assertDontSee('>Filter<', false)
            ->assertDontSee('>Clear<', false);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.r4-01.snapshots', ['month' => Carbon::parse($completedSession->period_month)->format('Y-m')]))
            ->assertOk()
            ->assertSee($monthlyReport->file_name);
        $this->withSession(['login_user' => ['id' => $user->id, 'tungkulin' => 'Secretary']])
            ->delete(route('forms.monthly.r4-01.snapshots.delete', $monthlyReport), ['confirmation_phrase' => 'SUPERADMIN'])
            ->assertForbidden();
        $this->withSession(['login_user' => ['id' => $headSecretary->id, 'tungkulin' => 'Head Secretary']])
            ->delete(route('forms.monthly.r4-01.snapshots.delete', $monthlyReport), [
                'acknowledge_permanent_deletion' => '1',
                'confirmation_phrase' => 'superadmin',
            ])
            ->assertSessionHasErrors('confirmation_phrase');
        $this->withSession(['login_user' => ['id' => $headSecretary->id, 'tungkulin' => 'Head Secretary']])
            ->delete(route('forms.monthly.r4-01.snapshots.delete', $monthlyReport), ['confirmation_phrase' => 'SUPERADMIN'])
            ->assertSessionHasErrors('acknowledge_permanent_deletion');
        $this->withSession(['login_user' => ['id' => $headSecretary->id, 'tungkulin' => 'Head Secretary']])
            ->delete(route('forms.monthly.r4-01.snapshots.delete', $monthlyReport), [
                'acknowledge_permanent_deletion' => '1',
                'confirmation_phrase' => 'SUPERADMIN',
            ])
            ->assertRedirect(route('forms.monthly.r4-01.snapshots'));
        $this->assertDatabaseMissing('r4_01_snapshots', ['id' => $monthlyReport->id]);
        Storage::disk('local')->assertMissing($monthlyReport->file_path);
        Storage::disk('local')->assertMissing($previewPath);
        $this->assertDatabaseHas('monthly_record_snapshots', [
            'month' => now()->timezone('Asia/Manila')->startOfMonth()->addMonthNoOverflow()->toDateString(),
            'purok_number' => $purok->purok_number,
            'ms_count' => 0,
            'session_generated' => true,
        ]);
        $snapshot = MonthlyRecordSnapshot::query()
            ->whereDate('month', now()->timezone('Asia/Manila')->startOfMonth()->addMonthNoOverflow()->toDateString())
            ->where('purok_number', $purok->purok_number)
            ->where('purok_id', $purok->id)
            ->firstOrFail();
        $this->assertSame(1, $snapshot->movement_counts['MS']['DD'] ?? 0);
        $secondSegmentSnapshot = MonthlyRecordSnapshot::query()
            ->whereDate('month', now()->timezone('Asia/Manila')->startOfMonth()->addMonthNoOverflow()->toDateString())
            ->where('purok_id', $secondPurokSegment->id)
            ->firstOrFail();
        $this->assertSame([], $secondSegmentSnapshot->movement_counts);
        $this->assertDatabaseHas('monthly_record_snapshots', [
            'month' => '2025-01-01',
            'purok_number' => 1,
            'ms_count' => 67,
        ]);
    }

    public function test_add_and_subtract_activity_batches_can_be_undone_safely(): void
    {
        $user = $this->makeSecretary();
        $purok = $this->makePurok();
        $session = ['login_user' => ['id' => $user->id]];

        $this->withSession($session)
            ->postJson(route('forms.monthly.deliberation.store'), $this->validPayload([
                'description' => 'Add to undo',
            ]))
            ->assertCreated();
        $added = Record::query()->where('full_name', 'NEW MEMBER')->firstOrFail();
        $activity = MonthlySessionActivity::query()->where('record_id', $added->id)->firstOrFail();
        $this->withSession($session)
            ->getJson(route('forms.monthly.session.status').'?include_movements=1')
            ->assertOk()
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.action', 'Add')
            ->assertJsonPath('activities.0.entries.0.monthly_code', 'MS: B')
            ->assertJsonPath('activities.0.undo_url', route('forms.monthly.session.activity.undo', $activity));
        $this->withSession($session)
            ->deleteJson(route('forms.monthly.session.activity.undo', $activity))
            ->assertOk();
        $this->assertDatabaseHas('records', [
            'id' => $added->id,
            'status' => 'INACTIVE',
            'monthly_code' => null,
            'date_of_entry' => null,
            'log_section' => null,
        ]);
        $this->assertDatabaseMissing('monthly_session_activities', ['id' => $activity->id]);
        $this->assertDatabaseCount('monthly_session_movements', 0);

        $subtract = Record::query()->create([
            'full_name' => 'Undo Subtract Member',
            'status' => 'UWP',
            'purok' => (string) $purok->purok_number,
            'grupo' => '2',
            'reason_of_r2_10_endorsement' => 'Original reason',
            'log_section' => 'Original log,',
        ]);
        $this->withSession($session)
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $subtract->id,
                'code' => 'D',
                'description' => 'Subtract to undo',
            ])
            ->assertOk();
        $activity = MonthlySessionActivity::query()->where('record_id', $subtract->id)->firstOrFail();
        $this->withSession($session)
            ->deleteJson(route('forms.monthly.session.activity.undo', $activity))
            ->assertOk();
        $this->assertDatabaseHas('records', [
            'id' => $subtract->id,
            'status' => 'UWP',
            'monthly_code' => null,
            'reason_of_r2_10_endorsement' => 'Original reason',
            'log_section' => 'Original log,',
        ]);
        $this->assertDatabaseMissing('monthly_session_activities', ['id' => $activity->id]);
        $this->assertDatabaseCount('monthly_session_movements', 0);
    }

    public function test_finishing_session_archives_record_codes_and_modify_movement_separately(): void
    {
        Storage::fake('local');
        $user = $this->makeSecretary();
        Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Source',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);
        $destination = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Destination',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);
        $record = Record::query()->create([
            'full_name' => 'TRANSFER HISTORY MEMBER',
            'status' => 'MS',
            'purok' => '1',
            'grupo' => '4',
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $record->id,
                'action' => 'transfer',
                'purok_id' => $destination->id,
                'grupo' => 8,
            ])
            ->assertOk();

        $headSecretary = User::query()->create([
            'gmail' => 'transfer.history.head@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->postJson(route('forms.monthly.session.finish'))
            ->assertOk()
            ->assertJsonPath('movements', 1)
            ->assertJsonPath('coded_records', 1);

        $this->assertDatabaseHas('permanent_history', [
            'record_id' => $record->id,
            'action' => 'transfer',
            'monthly_code' => 'MS: C+',
        ]);
        $this->assertDatabaseHas('permanent_history', [
            'record_id' => $record->id,
            'action' => 'transfer',
            'monthly_code' => 'MS: C- / MS: C+',
            'previous_values->purok' => '1',
            'previous_values->grupo' => '4',
            'next_values->purok' => '2',
            'next_values->grupo' => '8',
        ]);
        $this->assertDatabaseCount('permanent_history', 2);
        $this->assertDatabaseCount('monthly_session_movements', 0);
        $this->assertDatabaseCount('monthly_session_activities', 2);
        $this->assertDatabaseHas('monthly_session_activities', [
            'record_id' => $record->id,
            'monthly_code' => 'MS: C-',
            'direction' => 'out',
            'session_movement_id' => null,
        ]);
        $this->assertDatabaseHas('monthly_session_activities', [
            'record_id' => $record->id,
            'monthly_code' => 'MS: C+',
            'direction' => 'in',
            'session_movement_id' => null,
        ]);
        $this->assertNull($record->fresh()->monthly_code);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'New Member',
            'registry' => '1234567890123',
            'is_rfid' => false,
            'gender' => 'Male',
            'purok_id' => 1,
            'grupo' => 2,
            'kapisanan' => 'Kadiwa',
            'date_of_baptism' => '2020-01-01',
            'evangelist' => 'Evangelist Name',
            'status' => 'MS',
            'date_of_birth' => null,
            'is_married' => 0,
            'description' => null,
        ], $overrides);
    }

    private function makePurok(): Purok
    {
        return Purok::query()->create([
            'purok_number' => 1,
            'purok_name' => 'Central',
            'grupo_start' => 1,
            'grupo_end' => 3,
        ]);
    }

    private function makeSecretary(array $attributes = []): User
    {
        $user = User::query()->create($attributes + [
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
        ]);

        MonthlyMovementSession::query()->firstOrCreate(
            ['active_key' => 'active'],
            [
                'status' => 'active',
                'period_month' => now()->timezone('Asia/Manila')->startOfMonth()->addMonthNoOverflow()->toDateString(),
                'started_by' => $user->gmail,
                'started_at' => now(),
            ]
        );

        return $user;
    }
}
