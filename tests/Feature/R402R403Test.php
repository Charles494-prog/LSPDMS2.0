<?php

namespace Tests\Feature;

use App\Models\Record;
use App\Models\R402GeneratedForm;
use App\Models\R402Hakbang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class R402R403Test extends TestCase
{
    use RefreshDatabase;

    public function test_r402_filters_the_r401_list_and_saves_split_hakbang_codes_for_selected_rows(): void
    {
        $secretary = $this->makeSecretary();
        $this->makePurok(1, 'Purok One');
        $this->makePurok(2, 'Purok Two');
        R402Hakbang::query()->create(['status' => 'MS', 'code' => '1-7', 'description' => 'Dalaw']);
        R402Hakbang::query()->create(['status' => 'MS', 'code' => '1-8', 'description' => 'Pinayuhan']);
        $records = collect(range(1, 4))->map(fn (int $grupo): Record => Record::query()->create([
            'full_name' => "Member {$grupo}",
            'registry' => sprintf('%013d', $grupo),
            'gender' => 'Male',
            'purok' => '1',
            'grupo' => (string) $grupo,
            'kapisanan' => 'Kadiwa',
            'status' => 'MS',
            'monthly_code' => 'MS: A',
        ]));
        Record::query()->create([
            'full_name' => 'Other status',
            'registry' => '9999999999999',
            'purok' => '1',
            'grupo' => '5',
            'status' => 'UWP',
            'monthly_code' => 'UWP: A',
        ]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('forms.weekly.r4-02', ['purok' => 1, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('R4-01 records · MS')
            ->assertSee('Purok One')
            ->assertSee('Member 1')
            ->assertSee('Member 4')
            ->assertDontSee('Other status');

        $response = $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->post(route('forms.weekly.r4-02.store', [
                'purok' => 1,
                'kalagayan' => 'MS',
            ]), [
                'purok' => 1,
                'kalagayan' => 'MS',
                'selection' => '1-3',
                'row_keys' => $this->displayedRowKeys($secretary, '1', [1, 2, 3]),
                'split_codes' => '1',
                'code_assignments' => '1-2=1-7;3=1-8',
                'date_of_dalaw' => '2026-10-20',
                'date_of_ulat' => '2026-10-21',
            ]);
        $form = R402GeneratedForm::query()->with('formRecords')->firstOrFail();

        $response->assertRedirect(route('forms.weekly.r4-02.print', $form));
        $this->assertCount(3, $form->formRecords);
        $this->assertSame(['1-7', '1-7', '1-8'], $form->formRecords->pluck('hakbang_code')->all());
        $this->assertSame($records->take(3)->pluck('id')->all(), $form->formRecords->pluck('record_id')->all());
        $this->assertSame('2026-10-20', $form->date_of_dalaw->format('Y-m-d'));

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('forms.weekly.r4-02.print', $form))
            ->assertOk()
            ->assertSee('Print / Save as PDF')
            ->assertSee('Member 3')
            ->assertSee('1-8');
    }

    public function test_r402_rejects_unconfigured_codes_and_malformed_row_selections(): void
    {
        $secretary = $this->makeSecretary();
        $this->makePurok(1, 'Purok One');
        R402Hakbang::query()->create(['status' => 'MS', 'code' => '1-7', 'description' => 'Dalaw']);
        Record::query()->create([
            'full_name' => 'Member One',
            'registry' => '1234567890123',
            'purok' => '1',
            'grupo' => '1',
            'status' => 'MS',
            'monthly_code' => 'MS: A',
        ]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->from(route('forms.weekly.r4-02', ['purok' => 1, 'kalagayan' => 'MS']))
            ->post(route('forms.weekly.r4-02.store'), [
                'purok' => 1,
                'kalagayan' => 'MS',
                'selection' => '0-2',
                'row_keys' => '1:r1',
                'hakbang_code' => '1-7',
            ])
            ->assertRedirect(route('forms.weekly.r4-02', ['purok' => 1, 'kalagayan' => 'MS']))
            ->assertSessionHasErrors('selection');
        $this->assertDatabaseCount('r4_02_generated_forms', 0);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->post(route('forms.weekly.r4-02.store'), [
                'purok' => 1,
                'kalagayan' => 'MS',
                'selection' => '1',
                'row_keys' => '1:r1',
                'hakbang_code' => '9-9',
            ])
            ->assertSessionHasErrors('hakbang_code');
        $this->assertDatabaseCount('r4_02_generated_forms', 0);
    }

    public function test_r403_print_groups_only_puroks_with_saved_r402_forms(): void
    {
        $secretary = $this->makeSecretary();
        $this->makePurok(1, 'Purok One');
        $this->makePurok(2, 'Purok Two');
        $form = R402GeneratedForm::query()->create([
            'purok_number' => 1,
            'kalagayan' => 'UWP',
            'created_by' => 'Secretary Test',
            'date_of_ulat' => '2026-10-21',
        ]);
        $form->formRecords()->create([
            'source_row_number' => 2,
            'hakbang_code' => '1-8',
            'full_name' => 'Saved Member',
            'purok_grupo' => '1-2',
        ]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('forms.weekly.r4-03'))
            ->assertOk()
            ->assertSee('Purok One')
            ->assertDontSee('Purok Two')
            ->assertSee(route('forms.weekly.r4-03.print', ['kalagayan' => 'all']), false);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('forms.weekly.r4-03.print', ['kalagayan' => 'UWP']))
            ->assertOk()
            ->assertSee('Saved Member')
            ->assertSee('Purok One')
            ->assertDontSee('Purok Two');
    }
    public function test_r402_refuses_to_save_when_the_list_was_renumbered_after_it_was_displayed(): void
    {
        $secretary = $this->makeSecretary();
        $this->makePurok(1, 'Purok One');
        R402Hakbang::query()->create(['status' => 'MS', 'code' => '1-7', 'description' => 'Dalaw']);
        $records = collect(range(1, 4))->map(fn (int $grupo): Record => Record::query()->create([
            'full_name' => "Member {$grupo}",
            'registry' => sprintf('%013d', $grupo),
            'purok' => '1',
            'grupo' => (string) $grupo,
            'status' => 'MS',
            'monthly_code' => 'MS: A',
        ]));
        // The secretary opens the generator and picks rows 3 and 4 (Member 3 and Member 4).
        $staleKeys = $this->displayedRowKeys($secretary, '1', [3, 4]);
        $payload = ['purok' => 1, 'kalagayan' => 'MS', 'selection' => '3-4', 'hakbang_code' => '1-7'];

        // Another secretary recodes Member 2 to D, which moves it after the A rows: row 3 is now Member 4.
        $records[1]->update(['monthly_code' => 'MS: D']);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->post(route('forms.weekly.r4-02.store'), [...$payload, 'row_keys' => $staleKeys])
            ->assertSessionHasErrors(['selection' => 'The R4-01 list changed since you opened it, so row numbers may now point to different members. Reload the page, check the list and select the rows again.']);
        // A page opened before row keys existed (or a hand-made post) cannot save blind either.
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->post(route('forms.weekly.r4-02.store'), $payload)
            ->assertSessionHasErrors('row_keys');
        $this->assertDatabaseCount('r4_02_generated_forms', 0);

        // After reloading, rows 3-4 are Member 4 and Member 2, and the save records exactly those members.
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->post(route('forms.weekly.r4-02.store'), [...$payload, 'row_keys' => $this->displayedRowKeys($secretary, '1', [3, 4])])
            ->assertSessionHasNoErrors();
        $form = R402GeneratedForm::query()->with('formRecords')->sole();
        $this->assertSame(['Member 4', 'Member 2'], $form->formRecords->pluck('full_name')->all());
        $this->assertSame([$records[3]->id, $records[1]->id], $form->formRecords->pluck('record_id')->all());
    }

    public function test_r402_still_saves_when_only_unselected_rows_changed(): void
    {
        $secretary = $this->makeSecretary();
        $this->makePurok(1, 'Purok One');
        R402Hakbang::query()->create(['status' => 'MS', 'code' => '1-7', 'description' => 'Dalaw']);
        foreach (range(1, 2) as $grupo) {
            Record::query()->create([
                'full_name' => "Member {$grupo}",
                'registry' => sprintf('%013d', $grupo),
                'purok' => '1',
                'grupo' => (string) $grupo,
                'status' => 'MS',
                'monthly_code' => 'MS: A',
            ]);
        }
        $keys = $this->displayedRowKeys($secretary, '1', [1, 2]);
        // A member added after the selected rows does not renumber them, so the save goes through.
        Record::query()->create([
            'full_name' => 'Member 9',
            'registry' => '0000000000009',
            'purok' => '1',
            'grupo' => '9',
            'status' => 'MS',
            'monthly_code' => 'MS: A',
        ]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->post(route('forms.weekly.r4-02.store'), [
                'purok' => 1,
                'kalagayan' => 'MS',
                'selection' => '1-2',
                'row_keys' => $keys,
                'hakbang_code' => '1-7',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(
            ['Member 1', 'Member 2'],
            R402GeneratedForm::query()->with('formRecords')->sole()->formRecords->pluck('full_name')->all()
        );
    }

    /** The "number:key" pairs the R4-02 page posts for these rows, read from the rendered list and its data-key attributes. */
    private function displayedRowKeys(User $secretary, string $token, array $numbers): string
    {
        $response = $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('forms.weekly.r4-02', ['purok' => $token, 'kalagayan' => 'MS']))
            ->assertOk();
        $group = collect($response->viewData('purokGroups'))->firstWhere('number', $token);

        return collect($numbers)->map(function (int $n) use ($group, $response): string {
            $key = $group['rows'][$n - 1]['row_key'];
            $response->assertSee('data-row="'.$n.'" data-key="'.$key.'"', false);

            return $n.':'.$key;
        })->implode(',');
    }

    private function makeSecretary(): User
    {
        return User::query()->create([
            'gmail' => 'r402-secretary@example.test',
            'password' => 'password',
            'first_name' => 'R402',
            'last_name' => 'Secretary',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
        ]);
    }

    private function makePurok(int $number, string $name): void
    {
        DB::table('puroks')->insert([
            'purok_number' => $number,
            'purok_name' => $name,
            'grupo_start' => 1,
            'grupo_end' => 99,
            'full_purok' => false,
        ]);
    }
}
