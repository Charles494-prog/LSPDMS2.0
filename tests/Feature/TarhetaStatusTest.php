<?php

namespace Tests\Feature;

use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarhetaStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_checker_shows_mismatches_by_default_and_can_show_matches_and_saves_tarheta_status(): void
    {
        $user = $this->makeInquireUser();
        $mismatch = Record::query()->create([
            'full_name' => 'Member Needs Tarheta Update',
            'status' => 'UWP',
            'tarheta_status' => 'MS',
            'filing_status' => 'UWP',
            'filing_number' => 'F-001',
        ]);
        Record::query()->create([
            'full_name' => 'Member Already Matching',
            'status' => 'MK',
            'tarheta_status' => 'MK',
            'filing_status' => 'MK',
            'filing_number' => 'F-002',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('tarheta-status'))
            ->assertOk()
            ->assertSee('Member Needs Tarheta Update')
            ->assertSee('Member Already Matching')
            ->assertSee('data-matched="1"', false)
            ->assertSee('Tarheta Status Checker')
            ->assertSee('name="csrf-token"', false)
            ->assertSee('tarheta-choice')
            ->assertSee('Show already-matched records')
            ->assertSee('Monitor filing status')
            ->assertSee('Filing status')
            ->assertSee('Filing number')
            ->assertSee('filing-number-input')
            ->assertSee('tarhetaProgress')
            ->assertSee('tarhetaSearch')
            ->assertDontSee('tarheta-save')
            ->assertDontSee('>Save<', false);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('tarheta-status.update', $mismatch), ['tarheta_status' => 'UWP'])
            ->assertOk()
            ->assertJsonPath('matches', true)
            ->assertJsonPath('tarheta_status', 'UWP');

        $this->assertDatabaseHas('records', [
            'id' => $mismatch->id,
            'status' => 'UWP',
            'tarheta_status' => 'UWP',
            'filing_status' => 'UWP',
            'filing_number' => 'F-001',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('tarheta-status'))
            ->assertOk()
            ->assertSee('Member Needs Tarheta Update')
            ->assertSee('data-matched="1"', false)
            ->assertSee('100%');
    }

    public function test_checker_only_accepts_the_four_tarheta_statuses_and_requires_its_own_permission(): void
    {
        $user = $this->makeInquireUser();
        $record = Record::query()->create([
            'full_name' => 'Tarheta Validation Member',
            'status' => 'MS',
            'tarheta_status' => 'UWP',
            'filing_status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('tarheta-status.update', $record), ['tarheta_status' => 'OTHER'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tarheta_status');

        $unauthorized = User::query()->create([
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'Inquire_Allow' => true,
            'tarheta_status_allow' => false,
        ]);
        $this->withSession(['login_user' => ['id' => $unauthorized->id]])
            ->get(route('tarheta-status'))
            ->assertForbidden();
    }

    public function test_checker_treats_null_statuses_as_matching_and_progress_counts_the_full_evaluated_set(): void
    {
        $user = $this->makeInquireUser();
        Record::query()->create(['full_name' => 'Null Pair', 'status' => null, 'tarheta_status' => null]);
        Record::query()->create(['full_name' => 'Existing Match', 'status' => 'MS', 'tarheta_status' => 'MS', 'filing_status' => 'MS']);
        Record::query()->create(['full_name' => 'Status Mismatch', 'status' => 'UWP', 'tarheta_status' => 'MS', 'filing_status' => 'UWP']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('tarheta-status'))
            ->assertOk()
            ->assertSee('3</strong> evaluated records', false)
            ->assertSee('id="tarhetaMatched">2', false)
            ->assertSee('id="tarhetaTotal">3', false)
            ->assertSee('66.7%')
            ->assertSee('data-matched="1"', false)
            ->assertSee('data-matched="0"', false);
    }

    public function test_filing_status_and_number_are_saved_and_all_three_statuses_must_match(): void
    {
        $user = $this->makeInquireUser();
        $record = Record::query()->create([
            'full_name' => 'Inactive Filing Mismatch',
            'status' => 'INACTIVE',
            'tarheta_status' => 'INACTIVE',
            'filing_status' => 'MS',
            'filing_number' => '0007',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('tarheta-status'))
            ->assertOk()
            ->assertSee('data-status="INACTIVE" data-tarheta-status="INACTIVE" data-filing-status="MS" data-matched="0"', false)
            ->assertSee('value="0007"', false);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('tarheta-status.update', $record), [
                'filing_status' => 'INACTIVE',
                'filing_number' => '0008',
            ])
            ->assertOk()
            ->assertJsonPath('matches', true)
            ->assertJsonPath('filing_status', 'INACTIVE')
            ->assertJsonPath('filing_number', '0008');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'filing_status' => 'INACTIVE',
            'filing_number' => '0008',
        ]);
    }

    private function makeInquireUser(): User
    {
        return User::query()->create([
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'Inquire_Allow' => true,
            'tarheta_status_allow' => true,
        ]);
    }
}
