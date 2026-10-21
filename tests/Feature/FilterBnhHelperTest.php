<?php

namespace Tests\Feature;

use App\Models\Purok;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterBnhHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_permission_is_required_to_open_the_helper(): void
    {
        $secretary = User::query()->create([
            'gmail' => 'helper-secretary@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'filter_bnh_helper_allow' => false,
        ]);

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('filter-bnh-helper'))
            ->assertForbidden();

        $headSecretary = User::query()->create([
            'gmail' => 'helper-admin@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);
        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->putJson(route('superadmin.permissions.update'), [
                'permissions' => [[
                    'id' => $secretary->id,
                    'dashboard_allow' => 0,
                    'form_generator_allow' => 0,
                    'Delistment_allow' => 0,
                    'Inquire_Allow' => 0,
                    'tarheta_status_allow' => 0,
                    'filter_bnh_helper_allow' => 1,
                ]],
            ])
            ->assertOk();

        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->get(route('filter-bnh-helper'))
            ->assertOk()
            ->assertSee('Filter &amp; BNH Helper', false)
            ->assertSee('fbnhReload', false)
            ->assertSee('Reload Data')
            ->assertSee('fbnhAllStatuses', false)
            ->assertSee('value="MK"', false)
            ->assertSee('type="checkbox" value="MK"  disabled', false)
            ->assertSee('Purok Group Filter')
            ->assertSee('BNH Form Helper')
            ->assertSee('BNH Helper Form Resumen')
            ->assertSee('fbnhShowQualified', false)
            ->assertSee('fbnhShowMinuses', false)
            ->assertSee('fbnhShowBoth', false)
            ->assertSee('name="fbnhCountMode"', false)
            ->assertSee('type="radio"', false)
            ->assertSee('fbnhPanelMatrix', false)
            ->assertSee('fbnhPanelList', false)
            ->assertSee('fbnhPanelResumen', false)
            ->assertSee('fbnhMembersModal', false);
        $this->withSession(['login_user' => ['id' => $secretary->id]])
            ->getJson(route('session.status'))
            ->assertOk()
            ->assertJsonPath('permissions.filter_bnh_helper_allow', true);
    }

    public function test_group_filter_drills_into_active_records_and_respects_split_purok_ranges(): void
    {
        [$purok2a] = $this->makeSplitPuroks();
        $male = $this->makeRecord([
            'full_name' => 'Male MS',
            'purok' => '2',
            'grupo' => '1',
            'gender' => 'Male',
            'status' => 'MS',
        ]);
        $female = $this->makeRecord([
            'full_name' => 'Female UWP',
            'purok' => '2',
            'grupo' => '7',
            'gender' => 'Female',
            'status' => 'UWP',
        ]);
        $inactive = $this->makeRecord([
            'full_name' => 'Inactive Male',
            'purok' => '2',
            'grupo' => '1',
            'gender' => 'Male',
            'status' => 'INACTIVE',
        ]);

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.matrix', ['purok_id' => $purok2a->id, 'status' => 'MS']))
            ->assertOk()
            ->assertJsonPath('rows.0.label', '2a-1')
            ->assertJsonPath('rows.0.male', 1)
            ->assertJsonPath('rows.0.total', 1);
        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.matrix', ['purok_id' => $purok2a->id, 'status' => 'ALL']))
            ->assertOk()
            ->assertJsonPath('rows.2.type', 'purok_total')
            ->assertJsonPath('rows.2.label', 'Total 2a')
            ->assertJsonPath('rows.2.total', 1)
            ->assertJsonPath('rows.3.type', 'grand_total')
            ->assertJsonPath('rows.3.label', 'Overall Total')
            ->assertJsonPath('rows.3.total', 1);

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.members', [
                'purok_id' => $purok2a->id,
                'grupo' => 1,
                'gender' => 'male',
                'status' => 'MS',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.name', 'Male MS');

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.members', [
                'purok_id' => $purok2a->id,
                'gender' => 'all',
                'status' => 'ALL',
            ]))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('records.0.name', 'Male MS');

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.members', [
                'purok_id' => 'all',
                'gender' => 'all',
                'status' => 'ALL',
            ]))
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.matrix', ['purok_id' => $this->purok2b()->id, 'status' => 'UWP']))
            ->assertOk()
            ->assertJsonPath('rows.0.label', '2b-7')
            ->assertJsonPath('rows.0.female', 1);

        $purok2b = Purok::query()->where('purok_name', 'Purok 2b')->firstOrFail();
        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.members', [
                'purok_id' => $purok2b->id,
                'grupo' => 7,
                'gender' => 'female',
                'status' => 'UWP',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.name', 'Female UWP');

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.members', [
                'purok_id' => $purok2a->id,
                'grupo' => 1,
                'gender' => 'male',
                'status' => 'ALL',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.name', 'Male MS');

        $this->assertNotSame($male->id, $female->id);
        $this->assertNotSame($male->id, $inactive->id);
    }

    public function test_filter_accepts_multiple_puroks_and_an_explicit_empty_selection(): void
    {
        [$purok2a, $purok2b] = $this->makeSplitPuroks();
        $this->makeRecord([
            'full_name' => 'Member 2A',
            'purok' => '2',
            'grupo' => '1',
            'gender' => 'Male',
            'status' => 'MS',
        ]);
        $this->makeRecord([
            'full_name' => 'Member 2B',
            'purok' => '2',
            'grupo' => '7',
            'gender' => 'Female',
            'status' => 'MK',
        ]);

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.matrix', [
                'purok_selection' => 'selected',
                'purok_ids' => [$purok2a->id, $purok2b->id],
                'status' => 'ALL',
            ]))
            ->assertOk()
            ->assertJsonPath('rows.0.label', '2a-1')
            ->assertJsonPath('rows.3.label', '2b-7')
            ->assertJsonPath('rows.2.label', 'Total 2a')
            ->assertJsonPath('rows.5.label', 'Total 2b')
            ->assertJsonPath('rows.6.total', 2);

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.bnh-records', [
                'purok_selection' => 'selected',
                'purok_ids' => [$purok2b->id],
                'status_selection' => 'selected',
                'status_ids' => ['MK'],
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.name', 'Member 2B');

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.matrix', [
                'purok_selection' => 'selected',
                'purok_ids' => [$purok2a->id, $purok2b->id],
                'status_selection' => 'selected',
                'status_ids' => ['MS'],
            ]))
            ->assertOk()
            ->assertJsonPath('rows.0.total', 1)
            ->assertJsonPath('rows.5.total', 0)
            ->assertJsonPath('rows.6.total', 1);

        $this->withSession(['login_user' => ['id' => 9001]])
            ->getJson(route('filter-bnh-helper.matrix', [
                'purok_selection' => 'selected',
                'status' => 'ALL',
                'status_selection' => 'selected',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.type', 'grand_total')
            ->assertJsonPath('rows.0.total', 0);
    }

    public function test_mk_filter_requires_delistment_permission_or_head_secretary_role(): void
    {
        [$purok2a] = $this->makeSplitPuroks();
        $this->makeRecord([
            'full_name' => 'MS Member',
            'purok' => '2',
            'grupo' => '1',
            'gender' => 'Male',
            'status' => 'MS',
        ]);
        $this->makeRecord([
            'full_name' => 'MK Member',
            'purok' => '2',
            'grupo' => '2',
            'gender' => 'Female',
            'status' => 'MK',
        ]);
        $regularSecretary = User::query()->create([
            'gmail' => 'mk-filter-regular@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'filter_bnh_helper_allow' => true,
            'Delistment_allow' => false,
        ]);
        $delistmentSecretary = User::query()->create([
            'gmail' => 'mk-filter-delistment@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'filter_bnh_helper_allow' => true,
            'Delistment_allow' => true,
        ]);
        $headSecretary = User::query()->create([
            'gmail' => 'mk-filter-head@example.test',
            'password' => 'password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
            'filter_bnh_helper_allow' => false,
            'Delistment_allow' => false,
        ]);
        $purokSelection = [
            'purok_selection' => 'selected',
            'purok_ids' => [$purok2a->id],
            'status_selection' => 'selected',
            'status_ids' => ['MS', 'UWP', 'MK'],
        ];

        $this->withSession(['login_user' => ['id' => $regularSecretary->id]])
            ->getJson(route('filter-bnh-helper.matrix', $purokSelection))
            ->assertOk()
            ->assertJsonPath('totals.total', 1);

        $this->withSession(['login_user' => ['id' => $regularSecretary->id]])
            ->getJson(route('filter-bnh-helper.matrix', [
                'purok_id' => $purok2a->id,
                'status' => 'MK',
            ]))
            ->assertForbidden();

        $this->withSession(['login_user' => ['id' => $delistmentSecretary->id]])
            ->getJson(route('filter-bnh-helper.matrix', [
                'purok_id' => $purok2a->id,
                'status' => 'MK',
            ]))
            ->assertOk()
            ->assertJsonPath('totals.total', 1);

        $this->withSession(['login_user' => ['id' => $headSecretary->id]])
            ->getJson(route('filter-bnh-helper.matrix', [
                'purok_id' => $purok2a->id,
                'status' => 'MK',
            ]))
            ->assertOk()
            ->assertJsonPath('totals.total', 1);
    }

    public function test_bnh_checks_qualified_totals_and_word_exports_work(): void
    {
        [$purok2a] = $this->makeSplitPuroks();
        $this->makeRecord([
            'full_name' => 'Other Purok UWP',
            'purok' => '2',
            'grupo' => '7',
            'gender' => 'Female',
            'status' => 'UWP',
        ]);
        $record = $this->makeRecord([
            'full_name' => 'Qualified Member',
            'purok' => '2',
            'grupo' => '1',
            'gender' => 'Male',
            'status' => 'MS',
            'is_valid1' => true,
            'is_valid2' => false,
        ]);
        $unqualified = $this->makeRecord([
            'full_name' => 'Unqualified Member',
            'purok' => '2',
            'grupo' => '2',
            'gender' => 'Female',
            'status' => 'MS',
            'is_valid1' => true,
            'is_valid2' => false,
        ]);

        $this->withSession(['login_user' => ['id' => 9002]])
            ->putJson(route('filter-bnh-helper.checks.update', $record), [
                'is_valid1' => 1,
                'is_valid2' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('qualified', true);

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'is_valid1' => 1,
            'is_valid2' => 1,
        ]);

        $this->withSession(['login_user' => ['id' => 9002]])
            ->getJson(route('filter-bnh-helper.qualified-counts', ['purok_id' => $purok2a->id, 'status' => 'MS']))
            ->assertOk()
            ->assertJsonPath('rows.0.label', '2a-1')
            ->assertJsonPath('rows.0.male', 1)
            ->assertJsonPath('rows.0.total', 1);

        $this->withSession(['login_user' => ['id' => 9002]])
            ->getJson(route('filter-bnh-helper.bnh-records', ['purok_id' => $purok2a->id, 'status' => 'MS']))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('records.0.name', 'Qualified Member');

        $this->withSession(['login_user' => ['id' => 9002]])
            ->getJson(route('filter-bnh-helper.bnh-records', [
                'purok_id' => $purok2a->id,
                'status' => 'MS',
                'q' => 'Unqualified',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.name', 'Unqualified Member');

        $listWord = $this->withSession(['login_user' => ['id' => 9002]])
            ->get(route('filter-bnh-helper.bnh-word', [
                'purok_id' => $purok2a->id,
                'status' => 'MS',
                'q' => 'Unqualified',
            ]));
        $listWord->assertOk()->assertHeader('content-type', 'application/msword; charset=UTF-8');
        $this->assertStringContainsString('Unqualified Member', $listWord->streamedContent());
        $this->assertStringContainsString('Authorized and Confidential Information Ahead', $listWord->streamedContent());
        $this->assertStringContainsString('page-break-after:always', $listWord->streamedContent());
        $this->assertStringContainsString('securely delete this copy', $listWord->streamedContent());
        $this->assertLessThan(
            strpos($listWord->streamedContent(), '<h1>BNH Form Helper</h1>'),
            strpos($listWord->streamedContent(), 'Authorized and Confidential Information Ahead')
        );
        $this->assertStringNotContainsString('Qualified Member', $listWord->streamedContent());
        $this->assertStringNotContainsString('Other Purok UWP', $listWord->streamedContent());
        $this->assertStringNotContainsString('NOT QUALIFIED', $listWord->streamedContent());
        $this->assertStringNotContainsString('CHECK IN', $listWord->streamedContent());
        $this->assertStringContainsString('<span style="font-family: Wingdings">ü</span>', $listWord->streamedContent());
        $this->assertStringContainsString('<td class="center"><span style="font-family: Wingdings"></span></td>', $listWord->streamedContent());

        $qualifiedListWord = $this->withSession(['login_user' => ['id' => 9002]])
            ->get(route('filter-bnh-helper.bnh-word', [
                'purok_id' => $purok2a->id,
                'status' => 'MS',
                'q' => 'Qualified Member',
            ]));
        $this->assertStringContainsString('<td class="center"><span style="font-family: Wingdings">ü</span></td>', $qualifiedListWord->streamedContent());

        $countsWord = $this->withSession(['login_user' => ['id' => 9002]])
            ->get(route('filter-bnh-helper.counts-word', ['purok_id' => $purok2a->id, 'status' => 'MS']));
        $countsWord->assertOk();
        $this->assertStringContainsString('2a-1', $countsWord->streamedContent());
        $this->assertStringContainsString('Authorized and Confidential Information Ahead', $countsWord->streamedContent());
        $this->assertStringContainsString('page-break-after:always', $countsWord->streamedContent());
        $this->assertLessThan(
            strpos($countsWord->streamedContent(), '<h1>BNH Helper Form Resumen</h1>'),
            strpos($countsWord->streamedContent(), 'Authorized and Confidential Information Ahead')
        );
        $this->assertStringContainsString('BNH Helper Form Resumen', $countsWord->streamedContent());
        $this->assertStringContainsString('Total 2a', $countsWord->streamedContent());
        $this->assertStringNotContainsString('2b-7', $countsWord->streamedContent());
    }

    public function test_resumen_shows_qualified_and_unqualified_counts_for_the_selected_statuses(): void
    {
        [$purok2a] = $this->makeSplitPuroks();
        foreach ([
            ['Qualified One', true, true],
            ['Unqualified Two', true, false],
            ['Unqualified Three', false, false],
        ] as [$name, $isValid1, $isValid2]) {
            $this->makeRecord([
                'full_name' => $name,
                'purok' => '2',
                'grupo' => '1',
                'gender' => 'Male',
                'status' => 'MS',
                'is_valid1' => $isValid1,
                'is_valid2' => $isValid2,
            ]);
        }

        $this->withSession(['login_user' => ['id' => 9003]])
            ->getJson(route('filter-bnh-helper.qualified-counts', [
                'purok_id' => $purok2a->id,
                'status' => 'MS',
            ]))
            ->assertOk()
            ->assertJsonPath('rows.0.total', 1)
            ->assertJsonPath('rows.0.minuses_male', 2)
            ->assertJsonPath('rows.0.minuses_total', 2)
            ->assertJsonPath('rows.2.total', 1)
            ->assertJsonPath('rows.2.minuses_total', 2)
            ->assertJsonPath('totals.total', 1)
            ->assertJsonPath('minuses_totals.total', 2);

        $minusesWord = $this->withSession(['login_user' => ['id' => 9003]])
            ->get(route('filter-bnh-helper.counts-word', [
                'purok_id' => $purok2a->id,
                'status' => 'MS',
                'show_qualified' => 0,
                'show_minuses' => 1,
            ]));

        $minusesWord->assertOk();
        $this->assertStringContainsString('TOTAL MINUSES', $minusesWord->streamedContent());
        $this->assertStringNotContainsString('QUALIFIED FOR BNH', $minusesWord->streamedContent());
        $this->assertStringContainsString('<td class="number">2</td>', $minusesWord->streamedContent());

        $this->withSession(['login_user' => ['id' => 9003]])
            ->get(route('filter-bnh-helper.counts-word', [
                'purok_id' => $purok2a->id,
                'status' => 'MS',
                'show_qualified' => 0,
                'show_minuses' => 0,
            ]))
            ->assertUnprocessable();
    }

    /**
     * @return array{0: Purok, 1: Purok}
     */
    private function makeSplitPuroks(): array
    {
        $first = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2a',
            'grupo_start' => 1,
            'grupo_end' => 2,
            'full_purok' => false,
        ]);
        $second = Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2b',
            'grupo_start' => 7,
            'grupo_end' => 8,
            'full_purok' => false,
        ]);

        return [$first, $second];
    }

    private function makeRecord(array $attributes): Record
    {
        return Record::query()->create($attributes + [
            'registry' => 'REG-'.fake()->unique()->numerify('######'),
            'kapisanan' => 'K',
            'is_married' => false,
            'is_valid1' => false,
            'is_valid2' => false,
        ]);
    }

    private function purok2b(): Purok
    {
        return Purok::query()->where('purok_name', 'Purok 2b')->firstOrFail();
    }
}
