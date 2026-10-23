<?php

namespace Tests\Feature;

use App\Models\MonthlyMovementSession;
use App\Models\MonthlySessionMovement;
use App\Models\Purok;
use App\Models\R210EndorsementReason;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class R210ReasonHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_endorsed_records_cannot_move_between_ms_and_uwp_in_either_direction(): void
    {
        $this->activeSession();
        foreach ([['MS', 'UWP'], ['UWP', 'MS']] as [$from, $to]) {
            $record = $this->record($from, 'ENDORSED');

            $this->asSecretary()->postJson(route('forms.monthly.modify.save'), [
                'record_id' => $record->id, 'action' => 'status', 'status' => $to,
            ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.status.0', "You can't change {$from} to {$to} since it's already R2-10 endorsed. It can only move to MK.");
            $this->assertSame($from, $record->fresh()->status);
        }
    }

    public function test_endorsed_records_cannot_be_subtracted_to_inactive(): void
    {
        $this->activeSession();
        foreach (['MS', 'UWP'] as $status) {
            $record = $this->record($status, 'ENDORSED');

            $this->asSecretary()->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $record->id, 'code' => 'DD',
            ])
                ->assertUnprocessable()
                ->assertJsonPath('message', "You can't subtract this record and make it INACTIVE since it's already R2-10 endorsed.");
            $this->assertSame($status, $record->fresh()->status);
            $this->assertNull($record->fresh()->monthly_code);
        }

        // The picker tells the page up front, so it can explain before anything is submitted.
        $this->asSecretary()->getJson(route('forms.monthly.deliberation.subtract.search', ['q' => 'MEMBER']))
            ->assertOk()
            ->assertJsonPath('records.0.r2_10_endorsed', true);
    }

    public function test_records_that_are_not_endorsed_still_move_between_ms_and_uwp(): void
    {
        $this->activeSession();
        $record = $this->record('MS', null);

        $this->asSecretary()->postJson(route('forms.monthly.modify.save'), [
            'record_id' => $record->id, 'action' => 'status', 'status' => 'UWP',
        ])->assertOk();

        $this->assertSame('UWP', $record->fresh()->status);
    }

    public function test_endorsed_ms_to_mk_keeps_both_reasons_with_their_record_types(): void
    {
        $this->activeSession();
        $record = $this->record('MS', 'ENDORSED', 'admin (2026-10-01 08:00:00): 1 year MS or UWP.');
        R210EndorsementReason::query()->create([
            'record_id' => $record->id, 'record_type' => 'MS', 'reason' => $record->reason_of_r2_10_endorsement,
            'source' => R210EndorsementReason::SOURCE_DELISTMENT_ENDORSE, 'is_current' => true, 'is_backfilled' => false,
            'created_at' => now(),
        ]);

        $this->asSecretary()->postJson(route('forms.monthly.modify.save'), [
            'record_id' => $record->id, 'action' => 'status', 'status' => 'MK',
            'endorsement_reason' => 'Married to an MK member',
        ])->assertOk();

        $history = R210EndorsementReason::query()->where('record_id', $record->id)->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame(['MS', 'MK'], $history->pluck('record_type')->all());
        $this->assertSame([false, true], $history->pluck('is_current')->all());
        $this->assertSame('admin (2026-10-01 08:00:00): 1 year MS or UWP.', $history[0]->reason);
        $this->assertStringContainsString('Married to an MK member', $history[1]->reason);
        $this->assertNotNull($history[1]->session_movement_id);
        // The column keeps only the current reason (no concatenation).
        $this->assertSame($history[1]->reason, $record->fresh()->reason_of_r2_10_endorsement);
        $this->assertSame('MK', $record->fresh()->status);
    }

    public function test_ms_to_mk_without_a_reason_is_rejected(): void
    {
        $this->activeSession();
        $record = $this->record('MS', 'ENDORSED');

        $this->asSecretary()->postJson(route('forms.monthly.modify.save'), [
            'record_id' => $record->id, 'action' => 'status', 'status' => 'MK', 'endorsement_reason' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors('endorsement_reason');

        $this->assertSame('MS', $record->fresh()->status);
        $this->assertSame(0, R210EndorsementReason::query()->count());
    }

    public function test_double_submit_does_not_create_a_second_history_row(): void
    {
        $this->activeSession();
        $record = $this->record('UWP', 'ENDORSED');
        $payload = ['record_id' => $record->id, 'action' => 'status', 'status' => 'MK', 'endorsement_reason' => 'Moved to MK'];

        $this->asSecretary()->postJson(route('forms.monthly.modify.save'), $payload)->assertOk();
        $this->asSecretary()->postJson(route('forms.monthly.modify.save'), $payload)->assertUnprocessable();

        $this->assertSame(1, R210EndorsementReason::query()->where('record_id', $record->id)->count());
    }

    public function test_undoing_the_mk_move_removes_its_reason_and_restores_the_previous_one(): void
    {
        $this->activeSession();
        $record = $this->record('MS', 'ENDORSED', 'original reason');
        R210EndorsementReason::query()->create([
            'record_id' => $record->id, 'record_type' => 'MS', 'reason' => 'original reason',
            'source' => R210EndorsementReason::SOURCE_DELISTMENT_ENDORSE, 'is_current' => true, 'is_backfilled' => false,
            'created_at' => now(),
        ]);
        $this->asSecretary()->postJson(route('forms.monthly.modify.save'), [
            'record_id' => $record->id, 'action' => 'status', 'status' => 'MK', 'endorsement_reason' => 'Moved to MK',
        ])->assertOk();
        $movement = MonthlySessionMovement::query()->where('record_id', $record->id)->sole();

        $this->asSecretary()->deleteJson(route('forms.monthly.session.undo', $movement))->assertOk();

        $history = R210EndorsementReason::query()->where('record_id', $record->id)->get();
        $this->assertCount(1, $history);
        $this->assertTrue($history[0]->is_current);
        $this->assertSame('original reason', $record->fresh()->reason_of_r2_10_endorsement);
        $this->assertSame('MS', $record->fresh()->status);
    }

    public function test_subtract_no_longer_writes_into_the_r2_10_reason(): void
    {
        $this->activeSession();
        $record = $this->record('MS', null, 'kept reason');

        $this->asSecretary()->postJson(route('forms.monthly.deliberation.subtract'), [
            'record_id' => $record->id, 'code' => 'DD', 'description' => 'Moved abroad',
        ])->assertOk();

        $this->assertSame('kept reason', $record->fresh()->reason_of_r2_10_endorsement);
        $this->assertStringContainsString('Moved abroad', (string) $record->fresh()->log_section);
    }

    public function test_backfill_copies_existing_reasons_once_without_inventing_authors_or_dates(): void
    {
        $record = $this->record('MK', 'ENDORSED', "line one\nline two");
        R210EndorsementReason::query()->delete();
        $migration = require database_path('migrations/2026_10_24_001200_backfill_r2_10_endorsement_reasons.php');

        $migration->up();
        $migration->up();

        $rows = R210EndorsementReason::query()->where('record_id', $record->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame("line one\nline two", $rows[0]->reason);
        $this->assertTrue($rows[0]->is_backfilled);
        $this->assertTrue($rows[0]->is_current);
        $this->assertNull($rows[0]->endorsed_by);
        $this->assertNull($rows[0]->endorsed_at);
        $this->assertSame('MK', $rows[0]->record_type);
        $this->assertSame("line one\nline two", $record->fresh()->reason_of_r2_10_endorsement);
    }

    public function test_delistment_add_mk_stores_the_rfid_sentinel_and_never_treats_rfid_as_a_conflict(): void
    {
        $purok = Purok::query()->create(['purok_number' => 1, 'purok_name' => 'Purok 1', 'grupo_start' => 1, 'grupo_end' => 24, 'full_purok' => true]);
        Record::query()->create(['full_name' => 'EXISTING RFID MEMBER', 'registry' => 'RFID', 'status' => 'MS']);
        $payload = [
            'full_name' => 'New MK Member', 'is_rfid' => true, 'gender' => 'Male', 'purok_id' => $purok->id,
            'grupo' => 1, 'kapisanan' => 'Kadiwa', 'date_of_baptism' => '2010-01-01', 'date_of_birth' => '1990-01-01',
        ];

        $this->asSecretary(['Delistment_allow' => true])->postJson(route('de-listment.mk.store'), $payload + ['registry' => 'ABCDEFGHIJKLM'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registry');

        $response = $this->asSecretary(['Delistment_allow' => true])->postJson(route('de-listment.mk.store'), $payload + ['registry' => 'RFID']);
        $this->assertContains($response->status(), [200, 201], (string) $response->getContent());
        $this->assertDatabaseHas('records', ['full_name' => 'NEW MK MEMBER', 'registry' => 'RFID']);

        $this->asSecretary(['Delistment_allow' => true])->postJson(route('de-listment.mk.store'), [
            ...$payload, 'full_name' => 'Too Long', 'is_rfid' => false, 'registry' => 'ABCDEFGHIJKLMN',
        ])->assertUnprocessable()->assertJsonValidationErrors('registry');
    }

    private function activeSession(): MonthlyMovementSession
    {
        return MonthlyMovementSession::query()->create([
            'active_key' => 'active',
            'status' => 'active',
            'period_month' => '2026-11-01',
            'started_by' => 'secretary@example.test',
            'started_at' => now(),
        ]);
    }

    private function record(string $status, ?string $endorsement, ?string $reason = null): Record
    {
        return Record::query()->create([
            'full_name' => 'MEMBER '.strtoupper(bin2hex(random_bytes(3))),
            'registry' => strtoupper(substr(bin2hex(random_bytes(7)), 0, 13)),
            'status' => $status,
            'purok' => '1',
            'grupo' => '1',
            'date_of_entry' => '2020-01-01',
            'r2_10_endorsement' => $endorsement,
            'reason_of_r2_10_endorsement' => $reason,
        ]);
    }

    private function asSecretary(array $permissions = []): static
    {
        $user = User::query()->where('gmail', 'secretary@example.test')->first()
            ?? User::query()->create([
                'gmail' => 'secretary@example.test', 'password' => 'secret-password', 'tungkulin' => 'Secretary',
                'is_verified' => true, 'form_generator_allow' => true,
            ]);
        if ($permissions !== []) {
            $user->forceFill($permissions)->save();
        }

        return $this->withSession(['login_user' => ['id' => $user->id, 'gmail' => $user->gmail]]);
    }
}
