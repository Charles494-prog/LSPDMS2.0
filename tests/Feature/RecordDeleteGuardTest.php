<?php

namespace Tests\Feature;

use App\Models\MonthlyMovementSession;
use App\Models\MonthlySessionActivity;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_subtracted_in_the_active_session_cannot_be_deleted_until_the_session_is_finished(): void
    {
        $user = $this->makeSecretary();
        $record = Record::query()->create([
            'full_name' => 'Subtracted Member',
            'registry' => 'SUBTRACT00001',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.monthly.deliberation.subtract'), [
                'record_id' => $record->id,
                'code' => 'D',
            ])
            ->assertOk();
        $this->assertDatabaseHas('records', ['id' => $record->id, 'status' => 'INACTIVE', 'monthly_code' => 'MS: D']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->deleteJson(route('records.destroy', $record))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This record has a movement in the active Monthly Session. Finish the session first, then delete it.');
        $this->assertDatabaseHas('records', ['id' => $record->id]);

        // Even with the code cleared, the active session's activity log still needs the record for undo.
        $record->forceFill(['monthly_code' => null])->save();
        $this->assertTrue(MonthlySessionActivity::query()->where('record_id', $record->id)->exists());
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->deleteJson(route('records.destroy', $record))
            ->assertUnprocessable();
        $this->assertDatabaseHas('records', ['id' => $record->id]);

        // Once the session is no longer active the inactive record can be removed as before.
        MonthlyMovementSession::query()->update(['active_key' => null, 'status' => 'completed']);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->deleteJson(route('records.destroy', $record))
            ->assertOk()
            ->assertJsonPath('message', 'Inactive record deleted.');
        $this->assertDatabaseMissing('records', ['id' => $record->id]);
    }

    public function test_inactive_record_with_a_pending_monthly_code_cannot_be_deleted(): void
    {
        $record = Record::query()->create([
            'full_name' => 'Coded Inactive',
            'registry' => 'CODED00000001',
            'status' => 'INACTIVE',
            'monthly_code' => 'UWP: DD',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->deleteJson(route('records.destroy', $record))
            ->assertUnprocessable();
        $this->assertDatabaseHas('records', ['id' => $record->id, 'monthly_code' => 'UWP: DD']);
    }

    private function makeSecretary(): User
    {
        $user = User::query()->create([
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
            'Inquire_Allow' => true,
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
