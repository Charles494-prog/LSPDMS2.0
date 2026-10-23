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
