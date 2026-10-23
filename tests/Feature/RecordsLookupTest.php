<?php

namespace Tests\Feature;

use App\Models\Purok;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordsLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_full_name_or_registry_and_translates_kapisanan(): void
    {
        $withoutDelistment = $this->makeInquireUser();
        $withDelistment = $this->makeInquireUser(['Delistment_allow' => true]);
        Record::query()->create([
            'full_name' => 'Sample Buklod Person',
            'registry' => 'REG-2030-0001',
            'kapisanan' => 'B',
            'gender' => 'L',
            'purok' => '1',
            'grupo' => '3',
            'date_of_baptism' => '2020-02-03',
            'date_of_entry' => '2020-02-04',
            'status' => 'MS',
        ]);
        Record::query()->create([
            'full_name' => 'Sample Kadiwa Person',
            'registry' => 'REG-2030-0002',
            'kapisanan' => 'K',
        ]);
        Record::query()->create([
            'full_name' => 'Sample Binhi Person',
            'registry' => 'REG-2030-0003',
            'kapisanan' => 'binhi',
        ]);

        $this->withSession(['login_user' => ['id' => $withoutDelistment->id]])
            ->getJson(route('records.search', ['q' => 'SAMPLE BUKLOD']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Sample Buklod Person')
            ->assertJsonPath('data.0.kapisanan', 'Buklod')
            ->assertJsonPath('data.0.gender', 'Male')
            ->assertJsonPath('data.0.purok', '1')
            ->assertJsonPath('data.0.grupo', '3')
            ->assertJsonPath('data.0.date_of_baptism', '2020-02-03')
            ->assertJsonPath('data.0.date_of_entry', '2020-02-04')
            ->assertJsonPath('data.0.r210_eligible', null)
            ->assertJsonPath('data.0.status', 'MS');

        $this->withSession(['login_user' => ['id' => $withDelistment->id]])
            ->getJson(route('records.search', ['q' => 'SAMPLE BUKLOD']))
            ->assertOk()
            ->assertJsonPath('data.0.r210_eligible', 'ELIGIBLE');

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.search', ['q' => 'REG-2030-0002']))
            ->assertOk()
            ->assertJsonPath('data.0.kapisanan', 'Kadiwa');

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.search', ['q' => 'REG-2030-0003']))
            ->assertOk()
            ->assertJsonPath('data.0.kapisanan', 'binhi');
    }

    public function test_inquire_modify_page_uses_the_database_search_routes(): void
    {
        $user = $this->makeInquireUser();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('inquire-modify'))
            ->assertOk()
            ->assertSee('records\\/search', false)
            ->assertSee('records\\/smart-helper', false)
            ->assertSee('Smart Helper')
            ->assertSee('id="helper-open"', false)
            ->assertSee('id="helper-toggle" aria-label="Close Smart Helper"', false)
            ->assertSee('class="helper-panel" id="helper-panel"', false)
            ->assertSee('Press the button when you are ready.')
            ->assertSee('Delete inactive record')
            ->assertSee('Form Generator > Monthly > Deliberation', false)
            ->assertSee('Record update guide')
            ->assertSee('none of the matching records is Inactive')
            ->assertSee('id="remind-open" aria-expanded="false" aria-controls="reg-guide">Guidance</button>', false)
            ->assertDontSee('Naintindihan ko')
            ->assertSee('__RECORD_ID__', false)
            ->assertSee('<select id="rm-gender" required>', false)
            ->assertDontSee('<select id="rm-purok">', false)
            ->assertSee('id="rm-birthday"', false)
            ->assertDontSee('id="rm-marital-known"', false)
            ->assertSee('id="rm-married"', false)
            ->assertDontSee('Monthly action is locked by code', false)
            ->assertDontSee('Clear this previous monthly movement code', false)
            ->assertDontSee('rm-clear-monthly-code', false)
            ->assertSee('id="rm-purok-display"', false)
            ->assertSee('id="rm-r210-toggle" class="reg-eye" disabled', false)
            ->assertSee('id="rm-registry" maxlength="13" placeholder="Exactly 13 characters"', false)
            ->assertSee("date_of_birth: ['rm-birthday', 'Birthday']", false)
            ->assertSee('id="reg-save-btn" disabled', false)
            ->assertSee('Name match only. This is a warning, not an error.', false)
            ->assertSee('function normalizedValue(key, value)', false)
            ->assertSee("if (key === 'registry_number') return normalized.toUpperCase();", false)
            ->assertDontSee('PUROK_SPLITS')
            ->assertDontSee('DEMO_MEMBERS');
    }

    public function test_authorized_delistment_user_can_open_r210_eligibility(): void
    {
        $user = $this->makeInquireUser(['Delistment_allow' => true]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('inquire-modify'))
            ->assertOk()
            ->assertSee('id="rm-r210-toggle" class="reg-eye" >Show</button>', false);
    }

    public function test_inquire_modify_grupo_range_and_purok_label_follow_the_member_split_range(): void
    {
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2A',
            'grupo_start' => 1,
            'grupo_end' => 7,
        ]);
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2B',
            'grupo_start' => 8,
            'grupo_end' => 10,
        ]);
        Record::query()->create([
            'full_name' => 'Member in Purok 2A',
            'purok' => '2',
            'grupo' => '4',
        ]);
        Record::query()->create([
            'full_name' => 'Member in Purok 2B',
            'purok' => '2',
            'grupo' => '9',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.search', ['q' => 'Member in Purok 2A']))
            ->assertOk()
            ->assertJsonPath('data.0.purok_name', 'Purok 2A')
            ->assertJsonPath('data.0.grupo_min', 1)
            ->assertJsonPath('data.0.grupo_max', 7);

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.search', ['q' => 'Member in Purok 2B']))
            ->assertOk()
            ->assertJsonPath('data.0.purok_name', 'Purok 2B')
            ->assertJsonPath('data.0.grupo_min', 8)
            ->assertJsonPath('data.0.grupo_max', 10);
    }

    public function test_live_search_suggestions_narrow_as_name_or_registry_query_grows(): void
    {
        Record::query()->create([
            'full_name' => 'Juan Dela Cruz',
            'registry' => 'RZL0831000151',
        ]);
        Record::query()->create([
            'full_name' => 'Juanata Santos',
            'registry' => 'RZL0831000152',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.search', ['q' => 'jua']))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.search', ['q' => 'juana']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Juanata Santos');

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.search', ['q' => 'rzl08']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Juan Dela Cruz')
            ->assertJsonPath('data.0.registry_number', 'RZL0831000151');
    }

    public function test_smart_helper_finds_duplicate_registries_and_similar_names_across_statuses(): void
    {
        Record::query()->create([
            'full_name' => 'Juan Dela Cruz',
            'registry' => 'REG-1001',
            'status' => 'MS',
            'kapisanan' => 'B',
        ]);
        Record::query()->create([
            'full_name' => 'Juan Dela Croz',
            'registry' => 'REG-1002',
            'status' => 'UWP',
            'kapisanan' => 'K',
        ]);
        Record::query()->create([
            'full_name' => 'Juan Dela Cruz',
            'registry' => 'REG-1003',
            'status' => 'UWP',
            'kapisanan' => 'K',
        ]);
        Record::query()->create([
            'full_name' => 'Maria Santos',
            'registry' => 'REG-2000',
        ]);
        Record::query()->create([
            'full_name' => 'Pedro Reyes',
            'registry' => 'REG-2000',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.smart-helper'))
            ->assertOk()
            ->assertJsonPath('records_scanned', 5)
            ->assertJsonFragment([
                'type' => 'duplicate_registry',
                'field' => 'registry',
                'match_value' => 'REG-2000',
            ])
            ->assertJsonFragment([
                'type' => 'duplicate_name',
                'field' => 'full_name',
                'match_value' => 'Juan Dela Cruz',
                'similarity' => 100,
            ])
            ->assertJsonFragment([
                'type' => 'similar_name',
                'field' => 'full_name',
                'similarity' => 92,
            ])
            ->assertJsonFragment([
                'name' => 'Juan Dela Cruz',
                'registry_number' => 'REG-1001',
                'status' => 'MS',
                'kapisanan' => 'Buklod',
            ])
            ->assertJsonFragment([
                'name' => 'Juan Dela Croz',
                'registry_number' => 'REG-1002',
                'status' => 'UWP',
                'kapisanan' => 'Kadiwa',
            ]);

        $this->assertDatabaseCount('records', 5);
    }

    public function test_smart_helper_does_not_flag_jr_sr_as_same_name_or_rfid_as_registry(): void
    {
        Record::query()->create([
            'full_name' => 'Winifredo Balomino Jr.',
            'registry' => 'RFID',
            'status' => 'MS',
        ]);
        Record::query()->create([
            'full_name' => 'Winifredo Balomino Sr',
            'registry' => 'RFID',
            'status' => 'UWP',
        ]);
        Record::query()->create([
            'full_name' => 'Maria Santos',
            'registry' => 'RZL08310001',
        ]);
        Record::query()->create([
            'full_name' => 'Pedro Reyes',
            'registry' => 'RZL08310002',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.smart-helper'))
            ->assertOk()
            ->assertJsonPath('records_scanned', 4)
            ->assertJsonCount(0, 'data');
    }

    public function test_smart_helper_length_prefilter_keeps_boundary_matches(): void
    {
        // 20 vs 18 letters: distance 2 of 20 = exactly 90%, still reported.
        Record::query()->create(['full_name' => str_repeat('A', 20), 'registry' => 'BOUNDARY00001']);
        Record::query()->create(['full_name' => str_repeat('A', 18), 'registry' => 'BOUNDARY00002']);
        // 11 vs 9 letters: at most 82%, never reported.
        Record::query()->create(['full_name' => 'BCDEFGHIJKL', 'registry' => 'BOUNDARY00003']);
        Record::query()->create(['full_name' => 'BCDEFGHIJ', 'registry' => 'BOUNDARY00004']);

        $findings = $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.smart-helper'))
            ->assertOk()
            ->json('data');

        $similar = array_values(array_filter($findings, fn (array $finding): bool => $finding['type'] === 'similar_name'));
        $this->assertCount(1, $similar);
        $this->assertSame(90, $similar[0]['similarity']);
    }

    public function test_registry_conflict_is_rejected_inside_the_update_transaction(): void
    {
        Record::query()->create(['full_name' => 'Holder', 'registry' => 'TAKEN00000001', 'status' => 'MS']);
        $editing = Record::query()->create(['full_name' => 'Editor', 'registry' => 'OWN0000000001', 'status' => 'MS']);
        Record::query()->create(['full_name' => 'Card One', 'registry' => 'RFID', 'status' => 'MS']);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $editing), ['registry_number' => 'taken00000001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registry_number');
        $this->assertSame('OWN0000000001', $editing->fresh()->registry);

        // RFID is a shared sentinel, never a conflict.
        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $editing), ['registry_number' => 'RFID'])
            ->assertOk();
        $this->assertSame('RFID', $editing->fresh()->registry);
    }

    public function test_inactive_duplicate_record_can_be_deleted_permanently(): void
    {
        $inactive = Record::query()->create([
            'full_name' => 'Inactive Duplicate',
            'registry' => 'REG-INACTIVE-1',
            'status' => 'INACTIVE',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->deleteJson(route('records.destroy', $inactive))
            ->assertOk()
            ->assertJsonPath('message', 'Inactive record deleted.');

        $this->assertDatabaseMissing('records', ['id' => $inactive->id]);
    }

    public function test_non_inactive_record_cannot_be_deleted_from_smart_helper(): void
    {
        $active = Record::query()->create([
            'full_name' => 'Active Duplicate',
            'registry' => 'REG-ACTIVE-1',
            'status' => 'MS',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->deleteJson(route('records.destroy', $active))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'You can only delete this record after changing its status to Inactive in Form Generator > Monthly > Deliberation.'
            );

        $this->assertDatabaseHas('records', ['id' => $active->id, 'status' => 'MS']);
    }

    public function test_record_edits_are_saved_to_records_and_kapisanan_labels_are_stored_as_codes(): void
    {
        $record = Record::query()->create([
            'full_name' => 'Before Name',
            'registry' => 'REG-2030-0004',
            'gender' => 'L',
            'kapisanan' => 'B',
            'grupo' => '3',
            'date_of_baptism' => '2020-02-03',
            'evangelist' => 'Before Evangelist',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), [
                'name' => 'After Name',
                'kapisanan' => 'Kadiwa',
                'gender' => 'Female',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'AFTER NAME')
            ->assertJsonPath('data.kapisanan', 'Kadiwa')
            ->assertJsonPath('data.gender', 'Female');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'full_name' => 'AFTER NAME',
            'kapisanan' => 'K',
            'registry' => 'REG-2030-0004',
            'gender' => 'B',
            'grupo' => '3',
            'evangelist' => 'Before Evangelist',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['gender' => 'Male'])
            ->assertOk()
            ->assertJsonPath('data.gender', 'Male');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'gender' => 'L',
        ]);
    }

    public function test_record_edit_portal_can_update_birthday_and_restricts_group_to_current_purok(): void
    {
        Purok::query()->create([
            'purok_number' => 1,
            'grupo_start' => 5,
            'grupo_end' => 10,
        ]);
        $record = Record::query()->create([
            'full_name' => 'Missing Birthday',
            'purok' => '1',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['date_of_birth' => '2000-05-10'])
            ->assertOk()
            ->assertJsonPath('data.date_of_birth', '2000-05-10');

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['grupo' => 7])
            ->assertOk()
            ->assertJsonPath('data.grupo', '7')
            ->assertJsonPath('data.grupo_min', 5)
            ->assertJsonPath('data.grupo_max', 10);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['grupo' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupo');

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['purok' => '2'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purok');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'purok' => '1',
            'grupo' => '7',
        ]);
        $this->assertSame('2000-05-10', $record->fresh()->date_of_birth->format('Y-m-d'));
    }

    public function test_inquire_modify_auto_selects_kapisanan_when_birthdate_and_marital_status_are_known(): void
    {
        $record = Record::query()->create([
            'full_name' => 'Kapisanan Candidate',
            'status' => 'MS',
        ]);
        $user = $this->makeInquireUser();
        $birthday = now()->subYears(17)->toDateString();

        $this->assertFalse($record->fresh()->is_married);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $record), [
                'date_of_birth' => $birthday,
                'kapisanan' => 'Kadiwa',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_married', false)
            ->assertJsonPath('data.kapisanan', 'binhi');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'is_married' => 0,
            'kapisanan' => 'binhi',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $record), [
                'is_married' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_married', true)
            ->assertJsonPath('data.kapisanan', 'Buklod');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'is_married' => 1,
            'kapisanan' => 'B',
        ]);

        $nonMarriedBuklod = Record::query()->create([
            'full_name' => 'Buklod Must Be Married',
            'kapisanan' => 'K',
            'is_married' => false,
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $nonMarriedBuklod), ['kapisanan' => 'Buklod'])
            ->assertOk()
            ->assertJsonPath('data.is_married', true)
            ->assertJsonPath('data.kapisanan', 'Buklod');
        $this->assertDatabaseHas('records', [
            'id' => $nonMarriedBuklod->id,
            'kapisanan' => 'B',
            'is_married' => 1,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $record), [
                'is_married' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_married', false)
            ->assertJsonPath('data.kapisanan', 'binhi');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $record), ['is_married' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_married');

        $adult = Record::query()->create([
            'full_name' => 'Adult Not Married Member',
            'date_of_birth' => now()->subYears(18)->toDateString(),
            'is_married' => 0,
            'kapisanan' => 'binhi',
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $adult), ['name' => 'Adult Updated'])
            ->assertOk()
            ->assertJsonPath('data.kapisanan', 'Kadiwa');

        $manual = Record::query()->create([
            'full_name' => 'Manual Kapisanan Member',
            'is_married' => 0,
            'kapisanan' => 'K',
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $manual), ['kapisanan' => 'Suliranin'])
            ->assertOk()
            ->assertJsonPath('data.kapisanan', 'Suliranin');

        $marriedWithoutBirthday = Record::query()->create([
            'full_name' => 'Married Without Birthday',
            'is_married' => 0,
            'kapisanan' => 'K',
        ]);
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('records.update', $marriedWithoutBirthday), [
                'is_married' => 1,
                'kapisanan' => 'Kadiwa',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_married', true)
            ->assertJsonPath('data.kapisanan', 'Buklod');
        $this->assertDatabaseHas('records', [
            'id' => $marriedWithoutBirthday->id,
            'is_married' => 1,
            'kapisanan' => 'B',
        ]);
    }

    public function test_monthly_code_is_preserved_while_all_record_fields_can_be_edited(): void
    {
        $record = Record::query()->create([
            'full_name' => 'Locked Monthly Member',
            'status' => 'MS',
            'monthly_code' => 'MS: B',
            'date_of_birth' => '2000-01-01',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), [
                'name' => '  Charles Andrei Galvan ',
                'registry_number' => 'rzl0932123abc',
                'date_of_birth' => '1999-12-31',
                'gender' => 'Female',
                'grupo' => 2,
                'kapisanan' => 'Suliranin',
                'is_married' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'CHARLES ANDREI GALVAN')
            ->assertJsonPath('data.registry_number', 'RZL0932123ABC')
            ->assertJsonPath('data.is_married', true)
            ->assertJsonPath('data.kapisanan', 'Buklod')
            ->assertJsonPath('data.date_of_birth', '1999-12-31')
            ->assertJsonPath('data.monthly_code', 'MS: B');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'full_name' => 'CHARLES ANDREI GALVAN',
            'registry' => 'RZL0932123ABC',
            'gender' => 'B',
            'grupo' => '2',
            'monthly_code' => 'MS: B',
            'is_married' => 1,
            'kapisanan' => 'B',
        ]);
        $this->assertSame('1999-12-31', $record->fresh()->date_of_birth->format('Y-m-d'));
    }

    public function test_inquire_modify_cannot_transfer_between_split_puroks_sharing_a_header(): void
    {
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2a',
            'grupo_start' => 1,
            'grupo_end' => 7,
        ]);
        Purok::query()->create([
            'purok_number' => 2,
            'purok_name' => 'Purok 2b',
            'grupo_start' => 8,
            'grupo_end' => 10,
        ]);
        $record = Record::query()->create([
            'full_name' => 'Member in Split Purok',
            'purok' => '2',
            'grupo' => '3',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['grupo' => 8])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupo');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'purok' => '2',
            'grupo' => '3',
        ]);
    }

    public function test_registry_edits_require_thirteen_alphanumeric_characters_and_explain_duplicates(): void
    {
        $record = Record::query()->create([
            'full_name' => 'First Member',
            'registry' => 'ABCDEFGHIJKLM',
        ]);
        Record::query()->create([
            'full_name' => 'Existing Registry Owner',
            'registry' => 'ZXCVBNMASDFGH',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['registry_number' => 'TOO-SHORT'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registry_number')
            ->assertJsonPath('errors.registry_number.0', 'Enter exactly 13 letters or numbers, or RFID.');

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['registry_number' => 'zxcvbnmasdfgh'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registry_number')
            ->assertJsonPath(
                'errors.registry_number.0',
                'This registry number is already assigned to: Existing Registry Owner (record #2, registry ZXCVBNMASDFGH).'
            );

        $this->withSession(['login_user' => ['id' => 1]])
            ->putJson(route('records.update', $record), ['registry_number' => 'qwertyuiop123'])
            ->assertOk()
            ->assertJsonPath('data.registry_number', 'QWERTYUIOP123');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'registry' => 'QWERTYUIOP123',
        ]);
    }

    public function test_rfid_can_be_used_by_multiple_records_during_registry_edits(): void
    {
        $first = Record::query()->create(['full_name' => 'First RFID Member', 'registry' => 'ABCDEFGHIJKLM']);
        $second = Record::query()->create(['full_name' => 'Second RFID Member', 'registry' => 'ZXCVBNMASDFGH']);

        foreach ([$first, $second] as $record) {
            $this->withSession(['login_user' => ['id' => 1]])
                ->putJson(route('records.update', $record), ['registry_number' => 'rfid'])
                ->assertOk()
                ->assertJsonPath('data.registry_number', 'RFID');
        }
    }

    private function makeInquireUser(array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'Inquire_Allow' => true,
        ]);
    }
}
