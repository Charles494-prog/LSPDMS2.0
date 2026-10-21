<?php

namespace Tests\Feature;

use App\Models\DelistmentHistory;
use App\Models\GlobalVariable;
use App\Models\Purok;
use App\Models\Record;
use App\Models\RecordAuditLog;
use App\Models\User;
use App\Services\DelistmentDocxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelistmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_delisted_viewer_displays_history_reason_date_and_delisted_by(): void
    {
        $record = $this->record([
            'full_name' => 'Member in Delisted Viewer',
            'registry' => 'DELISTER123456',
            'status' => 'INACTIVE',
        ]);
        DelistmentHistory::query()->create([
            'record_id' => $record->id,
            'delisted_by' => 'Test Secretary',
            'reason' => 'Moved out of the locale',
            'delisted_at' => '2026-09-30 10:30:00',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('de-listment'))
            ->assertOk()
            ->assertSee('Add MK member')
            ->assertSee('id="add-mk-dialog"', false)
            ->assertSee('function syncMkKapisanan()', false)
            ->assertSee("mkKapisanan.value = 'Buklod'", false)
            ->assertSee("age < 18 ? 'Binhi' : 'Kadiwa'", false)
            ->assertSee('View delistment history')
            ->assertSee('Download history (DOCX)')
            ->assertSee('id="delisted-history-dialog"', false)
            ->assertSee('<dialog', false)
            ->assertDontSee('<section class="delist-panel table-panel" aria-labelledby="delisted-records-heading">', false)
            ->assertSee('Member in Delisted Viewer')
            ->assertSee('DELISTER123456')
            ->assertSee('Moved out of the locale')
            ->assertSee('Test Secretary')
            ->assertSee('Sep 30, 2026 06:30 PM');
    }

    public function test_delistment_page_uses_live_eligible_active_records_and_required_date_gaps(): void
    {
        GlobalVariable::query()->create([
            'dateofbaptism_requirement_boolean' => true,
            'baptism_requirement' => '2025-12-25',
            'entry_years_requirement' => 1,
        ]);
        $eligible = $this->record([
            'full_name' => 'Eligible MS Member',
            'status' => 'MS',
            'date_of_baptism' => '2025-12-25',
            'date_of_entry' => '2024-01-15',
        ]);
        $mk = $this->record([
            'full_name' => 'Automatic MK Member',
            'status' => 'MK',
            'r2_10_endorsement' => 'ENDORSED',
            'date_of_baptism' => '2020-01-01',
            'date_of_entry' => null,
        ]);
        $baptizedAfterCutoff = $this->record([
            'full_name' => 'Baptized After Cutoff',
            'status' => 'MS',
            'date_of_baptism' => '2025-12-26',
        ]);
        $this->record([
            'full_name' => 'Inactive Member',
            'status' => 'INACTIVE',
        ]);
        $notEligible = $this->record([
            'full_name' => 'Not Eligible UWP Member',
            'status' => 'UWP',
        ]);

        $this->withSession(['login_user' => ['id' => 1, 'first_name' => 'Charles Andrei', 'last_name' => 'Galvan']])
            ->get(route('de-listment'))
            ->assertOk()
            ->assertDontSee('Sample Member')
            ->assertSee('delistment-charts-', false)
            ->assertDontSee('cdn.jsdelivr.net/npm/chart.js', false)
            ->assertSee('eligibilityChart.data.datasets[0].data = cats.map(c => c.count)', false)
            ->assertSee('chart-members-dialog')
            ->assertSee('one-year-confirmed')
            ->assertSee('id="pending-card"', false)
            ->assertSee('id="pending-total"', false)
            ->assertSee("status: 'pending'", false)
            ->assertSee('background: var(--delist-surface)', false)
            ->assertDontSee('linear-gradient(125deg', false)
            ->assertDontSee('radial-gradient(circle', false)
            ->assertSee('const PAGE_SIZE = 20;', false)
            ->assertSee('data-entry-years="1"', false)
            ->assertSee('id="delist-dialog"', false)
            ->assertSee('This list shows active MS, UWP, or MK records that have no baptism date recorded.')
            ->assertDontSee('baptism date must be on or before the configured cutoff')
            ->assertDontSee('Date of entry</th>', false)
            ->assertDontSee('A date of entry is not needed here.')
            ->assertViewHas('members', function (array $members) use ($eligible, $mk, $baptizedAfterCutoff): bool {
                $this->assertCount(4, $members);
                $byId = collect($members)->keyBy('id');
                $this->assertTrue($byId[$eligible->id]['eligible']);
                $this->assertTrue($byId[$mk->id]['eligible']);
                $this->assertTrue($byId[$mk->id]['reported']);
                $this->assertFalse($byId[$baptizedAfterCutoff->id]['eligible']);

                return true;
            })
            ->assertViewHas('missingRecords', function (array $missing) use ($notEligible, $baptizedAfterCutoff): bool {
                $this->assertCount(1, $missing);
                $this->assertSame([$notEligible->id], array_column($missing, 'id'));
                $this->assertNotContains($baptizedAfterCutoff->id, array_column($missing, 'id'));

                return true;
            });
    }

    public function test_dynamic_eligibility_uses_configured_entry_years_and_baptism_cutoff_while_mk_is_exempt(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        GlobalVariable::query()->create([
            'dateofbaptism_requirement_boolean' => true,
            'baptism_requirement' => '2025-12-25',
            'entry_years_requirement' => 2,
        ]);
        $meetsRequirements = $this->record([
            'full_name' => 'Dynamically Eligible',
            'status' => 'MS',
            'date_of_entry' => '2024-10-01',
            'date_of_baptism' => '2025-12-25',
        ]);
        $afterBaptismCutoff = $this->record([
            'full_name' => 'Baptized After Cutoff',
            'status' => 'UWP',
            'date_of_entry' => '2024-10-01',
            'date_of_baptism' => '2025-12-26',
        ]);
        $tooRecent = $this->record([
            'full_name' => 'Too Recent',
            'status' => 'MS',
            'date_of_entry' => '2025-10-02',
            'date_of_baptism' => '2025-12-25',
        ]);
        $mkWithoutDates = $this->record([
            'full_name' => 'MK Always Eligible',
            'status' => 'MK',
            'date_of_entry' => null,
            'date_of_baptism' => null,
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('de-listment'))
            ->assertOk()
            ->assertViewHas('members', function (array $members) use ($meetsRequirements, $afterBaptismCutoff, $tooRecent, $mkWithoutDates): bool {
                $byId = collect($members)->keyBy('id');
                $this->assertTrue($byId[$meetsRequirements->id]['eligible']);
                $this->assertFalse($byId[$afterBaptismCutoff->id]['eligible']);
                $this->assertFalse($byId[$tooRecent->id]['eligible']);
                $this->assertTrue($byId[$mkWithoutDates->id]['eligible']);

                return true;
            });
    }

    public function test_chart_category_can_be_exported_as_docx_with_wrong_endorsements_flagged(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        GlobalVariable::query()->create(['entry_years_requirement' => 2]);
        $wronglyEndorsed = $this->record([
            'full_name' => 'Recently Entered Endorsed Member',
            'registry' => 'RECENT1234567',
            'status' => 'MS',
            'date_of_entry' => '2026-09-01',
            'r2_10_endorsement' => 'ENDORSED',
        ]);
        $this->record([
            'full_name' => 'Eligible Reported Member',
            'status' => 'MS',
            'date_of_entry' => '2020-01-01',
            'r2_10_endorsement' => 'ENDORSED',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('de-listment'))
            ->assertOk()
            ->assertSee('id="chart-members-export"', false)
            ->assertSee('Endorsed · Not eligible — review required');

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('de-listment.export', ['category' => 'not-eligible-endorsed']))
            ->assertOk()
            ->assertDownload('R2-10 - Not eligible - Endorsed (review required).docx');

        $exportPath = app(DelistmentDocxExporter::class)->generate('Not eligible - Endorsed', [[
            'name' => $wronglyEndorsed->full_name,
            'registry' => $wronglyEndorsed->registry,
            'kalagayan' => 'MS',
            'eligible' => false,
            'endorsed' => true,
            'reported' => false,
        ]]);
        try {
            $document = new \PharData($exportPath);
            $xml = $document['word/document.xml']->getContent();
            $this->assertStringContainsString('Recently Entered Endorsed Member', $xml);
            $this->assertStringContainsString('RECENT1234567', $xml);
            $this->assertStringContainsString('Endorsed · Not eligible — review required', $xml);
            $this->assertStringContainsString('CONFIDENTIAL', $xml);
            $this->assertStringContainsString('Authorized and Confidential Information Ahead', $xml);
            $this->assertStringContainsString('This document contains confidential personal information.', $xml);
            $this->assertStringContainsString('Access and use are limited to authorized purposes and personnel.', $xml);
            $this->assertStringContainsString('securely delete this copy', $xml);
            $this->assertStringNotContainsString('Eligible Reported Member', $xml);
        } finally {
            unset($document);
            @unlink($exportPath);
        }
    }

    public function test_delistment_history_can_be_downloaded_as_a_confidential_docx(): void
    {
        $record = $this->record([
            'full_name' => 'History Export Member',
            'registry' => 'HISTORY123456',
            'status' => 'INACTIVE',
        ]);
        DelistmentHistory::query()->create([
            'record_id' => $record->id,
            'delisted_by' => 'secretary@example.test',
            'reason' => 'Moved to another locale',
            'delisted_at' => '2026-10-01 10:30:00',
        ]);

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('de-listment.history.download'))
            ->assertOk()
            ->assertDownload('De-Listment History.docx');

        $exportPath = app(DelistmentDocxExporter::class)->generate('De-Listment History', [[
            'name' => 'History Export Member',
            'registry' => 'HISTORY123456',
            'delisted_at' => 'Oct 01, 2026 06:30 PM',
            'reason' => 'Moved to another locale',
            'delisted_by' => 'secretary@example.test',
        ]], history: true);
        try {
            $document = new \PharData($exportPath);
            $xml = $document['word/document.xml']->getContent();
            $this->assertStringContainsString('History Export Member', $xml);
            $this->assertStringContainsString('HISTORY123456', $xml);
            $this->assertStringContainsString('Moved to another locale', $xml);
            $this->assertStringContainsString('secretary@example.test', $xml);
            $this->assertStringContainsString('CONFIDENTIAL', $xml);
        } finally {
            unset($document);
            @unlink($exportPath);
        }
    }

    public function test_endorsement_uses_dynamic_eligibility_instead_of_imported_eligibility_text(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        GlobalVariable::query()->create([
            'dateofbaptism_requirement_boolean' => true,
            'baptism_requirement' => '2025-12-25',
            'entry_years_requirement' => 2,
        ]);
        $notQualified = $this->record([
            'status' => 'MS',
            'date_of_entry' => '2025-10-01',
            'date_of_baptism' => '2025-12-25',
        ]);
        $qualified = $this->record([
            'status' => 'UWP',
            'date_of_entry' => '2024-10-01',
            'date_of_baptism' => '2025-12-25',
        ]);
        $user = ['id' => 1, 'gmail' => 'formalcharlesandreigalvan@gmail.com', 'first_name' => 'Charles', 'last_name' => 'Galvan'];

        $this->withSession(['login_user' => $user])
            ->putJson(route('de-listment.endorse', $notQualified), [
                'note' => 'Reviewed',
                'assurance' => true,
            ])
            ->assertUnprocessable();

        $this->withSession(['login_user' => $user])
            ->putJson(route('de-listment.endorse', $qualified), [
                'note' => 'Reviewed',
                'assurance' => true,
            ])
            ->assertOk();
    }

    public function test_only_endorsed_mk_can_be_delisted_and_history_and_record_log_are_saved(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        $user = User::query()->create([
            'gmail' => 'delister@example.test',
            'first_name' => 'Test',
            'last_name' => 'Secretary',
            'password' => 'password',
            'is_verified' => true,
            'Delistment_allow' => true,
        ]);
        $mk = $this->record([
            'full_name' => 'Endorsed MK',
            'status' => 'MK',
            'r2_10_endorsement' => 'ENDORSED',
            'log_section' => 'Existing entry,',
        ]);
        $notEndorsed = $this->record(['status' => 'MK']);
        $notMk = $this->record(['status' => 'MS', 'r2_10_endorsement' => 'ENDORSED']);

        foreach ([$notEndorsed, $notMk] as $record) {
            $this->withSession(['login_user' => ['id' => $user->id]])
                ->putJson(route('de-listment.delist', $record), ['reason' => 'Confirmed removal'])
                ->assertUnprocessable();
        }

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('de-listment.delist', $mk), ['reason' => 'Member requested removal'])
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE');

        $audit = RecordAuditLog::query()
            ->where('record_id', $mk->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('delister@example.test', $audit->actor_email);
        $this->assertStringContainsString('Changed the status of Endorsed MK from MK to INACTIVE.', $audit->summary);

        $this->assertDatabaseHas('records', [
            'id' => $mk->id,
            'status' => 'INACTIVE',
            'log_section' => 'Existing entry, 01/10/2026: Delisted on October 01, 2026: Member requested removal,',
        ]);
        $this->assertDatabaseHas('delistment_histories', [
            'record_id' => $mk->id,
            'delisted_by_user_id' => $user->id,
            'delisted_by' => 'delister@example.test',
            'reason' => 'Member requested removal',
            'delisted_at' => now('Asia/Manila')->format('Y-m-d H:i:s'),
        ]);
        $this->assertDatabaseCount('delistment_histories', 1);
    }

    public function test_eligible_record_endorsement_persists_reason_and_appends_attributed_entry(): void
    {
        $record = $this->record([
            'full_name' => 'Endorsement Candidate',
            'status' => 'MS',
            'reason_of_r2_10_endorsement' => 'Existing reason.',
            'log_section' => 'Existing log history.',
        ]);

        $this->withSession(['login_user' => ['id' => 1, 'gmail' => 'formalcharlesandreigalvan@gmail.com', 'first_name' => 'Charles Andrei', 'last_name' => 'Galvan']])
            ->putJson(route('de-listment.endorse', ['record' => $record->id]), [
                'note' => 'Reviewed and submitted with no changes.',
                'assurance' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.r2_10_endorsement', 'ENDORSED');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'r2_10_endorsement' => 'ENDORSED',
        ]);
        $this->assertStringContainsString('Existing reason.', $record->fresh()->reason_of_r2_10_endorsement);
        $this->assertMatchesRegularExpression(
            '/formalcharlesandreigalvan@gmail\.com \(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\): due to Reviewed and submitted with no changes\./',
            $record->fresh()->reason_of_r2_10_endorsement
        );
        $this->assertSame('Existing log history.', $record->fresh()->log_section);
    }

    public function test_ms_and_uwp_can_use_one_year_confirmation_but_mk_requires_a_description(): void
    {
        $user = ['id' => 1, 'gmail' => 'formalcharlesandreigalvan@gmail.com', 'first_name' => 'Charles Andrei', 'last_name' => 'Galvan'];
        $ms = $this->record([
            'status' => 'MS',
        ]);
        $this->withSession(['login_user' => $user])
            ->putJson(route('de-listment.endorse', ['record' => $ms->id]), [
                'note' => null,
                'one_year_confirmed' => true,
                'assurance' => true,
            ])
            ->assertOk();
        $this->assertMatchesRegularExpression(
            '/formalcharlesandreigalvan@gmail\.com \(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\): 1 year MS or UWP\./',
            $ms->fresh()->reason_of_r2_10_endorsement
        );

        $mk = $this->record([
            'status' => 'MK',
        ]);
        $this->withSession(['login_user' => $user])
            ->putJson(route('de-listment.endorse', ['record' => $mk->id]), [
                'note' => null,
                'one_year_confirmed' => false,
                'assurance' => true,
            ])
            ->assertUnprocessable();
        $this->assertNull($mk->fresh()->r2_10_endorsement);

        $this->withSession(['login_user' => $user])
            ->putJson(route('de-listment.endorse', ['record' => $mk->id]), [
                'note' => 'Reviewed with the member.',
                'one_year_confirmed' => true,
                'assurance' => true,
            ])
            ->assertUnprocessable();
        $this->assertNull($mk->fresh()->r2_10_endorsement);

        $this->withSession(['login_user' => $user])
            ->putJson(route('de-listment.endorse', ['record' => $mk->id]), [
                'note' => 'Reviewed with the member.',
                'assurance' => true,
            ])
            ->assertOk();
        $this->assertStringContainsString('due to Reviewed with the member.', $mk->fresh()->reason_of_r2_10_endorsement);
    }

    public function test_endorsement_rejects_ineligible_inactive_and_already_endorsed_records(): void
    {
        $notEligible = $this->record([
            'status' => 'MS',
            'date_of_entry' => now()->subDays(2)->toDateString(),
        ]);
        $inactive = $this->record(['status' => 'INACTIVE']);
        $endorsed = $this->record([
            'status' => 'UWP',
            'r2_10_endorsement' => 'ENDORSED',
        ]);

        foreach ([$notEligible, $inactive] as $record) {
            $this->withSession(['login_user' => ['id' => 1, 'gmail' => 'formalcharlesandreigalvan@gmail.com', 'first_name' => 'Charles', 'last_name' => 'Galvan']])
                ->putJson(route('de-listment.endorse', ['record' => $record->id]), [
                    'note' => 'Reviewed',
                    'assurance' => true,
                ])
                ->assertUnprocessable();
        }

        $this->withSession(['login_user' => ['id' => 1, 'gmail' => 'formalcharlesandreigalvan@gmail.com', 'first_name' => 'Charles', 'last_name' => 'Galvan']])
            ->putJson(route('de-listment.endorse', ['record' => $endorsed->id]), [
                'note' => 'Reviewed',
                'assurance' => true,
            ])
            ->assertStatus(409);
    }

    public function test_missing_baptism_dates_are_saved_for_active_records_and_future_dates_are_rejected(): void
    {
        $record = $this->record([
            'status' => 'UWP',
            'date_of_entry' => '2021-05-06',
        ]);

        $this->withSession(['login_user' => ['id' => 1, 'first_name' => 'Charles', 'last_name' => 'Galvan']])
            ->putJson(route('de-listment.update-dates'), [
                'changes' => [[
                    'id' => $record->id,
                    'baptism' => '2020-03-04',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.baptism', '2020-03-04');

        $this->assertSame('2020-03-04', $record->fresh()->date_of_baptism->format('Y-m-d'));
        $this->assertSame('2021-05-06', $record->fresh()->date_of_entry->format('Y-m-d'));

        $this->withSession(['login_user' => ['id' => 1, 'first_name' => 'Charles', 'last_name' => 'Galvan']])
            ->putJson(route('de-listment.update-dates'), [
                'changes' => [[
                    'id' => $record->id,
                    'baptism' => now()->addDay()->toDateString(),
                ]],
            ])
            ->assertUnprocessable();
    }

    public function test_mk_member_can_be_added_from_delistment_without_a_monthly_code(): void
    {
        $user = User::query()->create([
            'gmail' => 'mk-adder@example.test',
            'first_name' => 'Test',
            'last_name' => 'Secretary',
            'password' => 'password',
            'is_verified' => true,
            'Delistment_allow' => true,
        ]);
        $purok = Purok::query()->create([
            'purok_number' => 4,
            'purok_name' => 'Purok Four',
            'grupo_start' => 1,
            'grupo_end' => 12,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('de-listment.mk.store'), [
                'full_name' => 'MK Test Member',
                'registry' => 'MKTEST1234567',
                'gender' => 'Male',
                'purok_id' => $purok->id,
                'grupo' => 3,
                'kapisanan' => 'Kadiwa',
                'is_married' => false,
                'evangelist' => 'Test Evangelist',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kalagayan', 'MK')
            ->assertJsonPath('data.name', 'MK TEST MEMBER');

        $record = Record::query()->where('registry', 'MKTEST1234567')->sole();
        $this->assertSame('MK', $record->status);
        $this->assertNull($record->monthly_code);
        $this->assertSame('4', $record->purok);
        $this->assertSame('3', $record->grupo);
        $this->assertSame('L', $record->gender);
        $this->assertSame('K', $record->kapisanan);
        $this->assertSame('Test Evangelist', $record->evangelist);
        $this->assertSame(now('Asia/Manila')->toDateString(), $record->date_of_entry->format('Y-m-d'));
    }

    public function test_mk_kapisanan_is_automatically_derived_from_marital_status_and_age(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->startOfDay());
        $user = User::query()->create([
            'gmail' => 'mk-kapisanan@example.test',
            'first_name' => 'Test',
            'last_name' => 'Secretary',
            'password' => 'password',
            'is_verified' => true,
            'Delistment_allow' => true,
        ]);
        $purok = Purok::query()->create([
            'purok_number' => 5,
            'grupo_start' => 1,
            'grupo_end' => 12,
        ]);
        $payload = [
            'gender' => 'Female',
            'purok_id' => $purok->id,
            'kapisanan' => 'Kadiwa',
        ];

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('de-listment.mk.store'), $payload + [
                'full_name' => 'Married Adult',
                'registry' => 'MARRIED123456',
                'date_of_birth' => '2003-07-08',
                'is_married' => true,
            ])
            ->assertCreated();

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('de-listment.mk.store'), $payload + [
                'full_name' => 'Unmarried Minor',
                'registry' => 'MINOR12345678',
                'date_of_birth' => '2009-10-21',
                'is_married' => false,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('records', [
            'registry' => 'MARRIED123456',
            'kapisanan' => 'B',
            'is_married' => 1,
            'status' => 'MK',
            'monthly_code' => null,
        ]);
        $this->assertDatabaseHas('records', [
            'registry' => 'MINOR12345678',
            'kapisanan' => 'binhi',
            'is_married' => 0,
            'status' => 'MK',
            'monthly_code' => null,
        ]);
    }

    public function test_mk_add_rejects_invalid_registry_numbers_and_existing_registry_duplicates(): void
    {
        $user = User::query()->create([
            'gmail' => 'mk-adder@example.test',
            'first_name' => 'Test',
            'last_name' => 'Secretary',
            'password' => 'password',
            'is_verified' => true,
            'Delistment_allow' => true,
        ]);
        $purok = Purok::query()->create([
            'purok_number' => 1,
            'grupo_start' => 1,
            'grupo_end' => 10,
        ]);
        $this->record(['registry' => 'EXIST12345678']);

        $payload = [
            'full_name' => 'Another MK Member',
            'gender' => 'Female',
            'purok_id' => $purok->id,
            'kapisanan' => 'Binhi',
        ];
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('de-listment.mk.store'), $payload + ['registry' => 'not-valid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registry');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('de-listment.mk.store'), $payload + ['registry' => 'EXIST12345678'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('registry');
    }

    private function record(array $attributes = []): Record
    {
        return Record::query()->create($attributes + [
            'full_name' => 'Test Record',
            'status' => 'MS',
            'date_of_entry' => '2020-01-01',
        ]);
    }
}
