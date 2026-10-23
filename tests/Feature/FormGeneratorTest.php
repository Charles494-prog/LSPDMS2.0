<?php

namespace Tests\Feature;

use App\Models\GlobalVariable;
use App\Models\MonthlyRecordSnapshot;
use App\Models\R401Snapshot;
use App\Models\Record;
use App\Models\User;
use App\Services\ExportTempFiles;
use App\Services\MonthlyResumenReport;
use App\Services\R401DocxExporter;
use App\Services\ResumenDocxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_generator_shows_generate_forms_and_a_separate_deliberation_section(): void
    {
        $user = $this->makeSecretary(['form_generator_allow' => true]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('form-generator'))
            ->assertOk()
            ->assertSeeInOrder(['id="generate-forms"', 'id="generate-weekly"', 'R4-02 Generator', 'R4-03 Generator',
                'id="generate-monthly"', 'Resumen &amp; R4-01 Generator (This Month)', 'R4-01 Snapshots',
                'id="deliberation"', 'Add', 'Subtract', 'Modify'], false)
            ->assertSee(route('forms.weekly.r4-02'), false)
            ->assertSee(route('forms.weekly.r4-03'), false)
            ->assertSee(route('forms.monthly.generators'), false)
            ->assertSee(route('forms.monthly.r4-01.snapshots'), false)
            ->assertSee(route('forms.monthly.deliberation', ['mode' => 'subtract']), false)
            ->assertSee(route('forms.monthly.modify'), false)
            ->assertSee('id="monthlySessionWidget"', false)
            ->assertSee('id="monthlySessionOpen"', false)
            ->assertSee('Please type the following exactly. It is case-sensitive:', false)
            ->assertSee('Please match the entire sentence exactly, including capitalization.', false)
            ->assertSee('monthlySessionBlocker', false)
            // Sidebar submenus and the breadcrumb trail
            ->assertSee('data-nav-tree="generate"', false)
            ->assertSee('data-nav-tree="deliberation"', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            // Notes moved to their own page
            ->assertDontSee('id="notesApp"', false);
    }

    public function test_old_notes_tab_link_redirects_and_form_pages_show_breadcrumbs(): void
    {
        $user = $this->makeSecretary(['form_generator_allow' => true, 'note_checker_allow' => true]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('form-generator', ['panel' => 'notes']))
            ->assertRedirect(route('note-checker'));

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.weekly.r4-03'))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Form Generator', 'Generate Forms', 'Weekly', 'R4-03 Generator'], false);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.deliberation', ['mode' => 'subtract']))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Form Generator', 'Deliberation Form', 'Subtract'], false);
    }

    public function test_note_checker_is_its_own_page(): void
    {
        $user = $this->makeSecretary(['note_checker_allow' => true]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('note-checker'))
            ->assertOk()
            ->assertSee('Note Checker')
            ->assertSee('id="notesApp"', false)
            ->assertSee('id="notesRecordPagination"', false)
            ->assertSee('id="previousRecordsPage"', false)
            ->assertSee('id="nextRecordsPage"', false)
            ->assertSee('new AbortController()', false)
            ->assertSee('Save or clear the unsaved note before switching records.', false)
            ->assertSee('const recordsPerPage = 10;', false)
            ->assertSee('href="'.route('note-checker').'"', false);
    }

    public function test_resumen_and_r4_01_generators_render_together_in_order(): void
    {
        $user = $this->makeSecretary(['form_generator_allow' => true]);

        $response = $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.generators', [
                'purok' => 'all',
                'kalagayan' => 'MS',
            ]));
        $response->assertOk()
            ->assertSeeInOrder([
                'id="resumen-generator-heading"',
                'Resumen Generator',
                'id="r401-generator-heading"',
                'R4-01 Generator',
            ], false)
            ->assertSee(route('forms.monthly.generators'), false)
            ->assertSee('id="r401Filters"', false)
            ->assertSee('id="r401PurokInput" value="all"', false)
            ->assertSee('id="r401KalagayanInput" value="MS"', false)
            ->assertDontSee('Report month')
            ->assertDontSee('Generate preview')
            ->assertSee('Monthly Forms</a>', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="monthlySessionWidget"'));
        $this->assertSame(1, substr_count($response->getContent(), '<aside class="sidebar"'));
        $this->assertSame(1, substr_count($response->getContent(), '<header class="topbar">'));
    }

    public function test_r4_01_preview_and_docx_filter_records_and_paginate_twenty_entries(): void
    {
        Storage::fake('local');
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        GlobalVariable::query()->create([
            'locale' => 'Sample Local',
            'lcode' => 'ABC-12',
            'district' => 'Sample District',
            'dcode' => 'D-7',
            's_number' => 'S-123',
            'kalihim_ms' => 'MS Secretary',
            'ksp_pd' => 'KSP Name',
            'pastor_destinado' => 'Pastor Name',
        ]);
        DB::table('puroks')->insert([
            'purok_number' => 1,
            'purok_name' => 'Sample Purok',
            'manggagawa' => 'Sample Manggagawa',
            'ministerial_id' => 'MIN-001',
            'grupo_start' => 1,
            'grupo_end' => 50,
            'full_purok' => false,
        ]);
        DB::table('monthly_movement_sessions')->insert([
            'active_key' => 'active',
            'status' => 'active',
            'period_month' => '2026-10-01',
            'started_by' => 'Secretary Example',
            'started_at' => '2026-10-01 09:00:00',
        ]);
        DB::table('puroks')->insert([
            'purok_number' => 2,
            'purok_name' => 'Purok 2A',
            'manggagawa' => 'Purok 2A Manggagawa',
            'ministerial_id' => 'MIN-002',
            'grupo_start' => 1,
            'grupo_end' => 7,
            'full_purok' => false,
        ]);
        DB::table('puroks')->insert([
            'purok_number' => 2,
            'purok_name' => 'Purok 2B',
            'manggagawa' => 'Purok 2B Manggagawa',
            'ministerial_id' => 'MIN-003',
            'grupo_start' => 8,
            'grupo_end' => 50,
            'full_purok' => false,
        ]);

        foreach (range(1, 43) as $grupo) {
            Record::query()->create([
                'full_name' => sprintf('Member %02d', $grupo),
                'registry' => sprintf('%013d', $grupo),
                'gender' => $grupo === 1 ? 'Male' : ($grupo === 2 ? 'Female' : 'L'),
                'purok' => '1',
                'grupo' => (string) $grupo,
                'kapisanan' => $grupo === 1 ? 'Binhi' : 'Kadiwa',
                'date_of_baptism' => '2020-03-04',
                'evangelist' => 'Maria Evangelist',
                'status' => 'MS',
                'monthly_code' => $grupo === 1 ? null : ($grupo === 2 ? 'MS: C+' : 'MS: B'),
            ]);
        }

        Record::query()->create([
            'full_name' => 'Different Status Member',
            'registry' => '9234567890123',
            'purok' => '1',
            'grupo' => '44',
            'status' => 'UWP',
        ]);
        Record::query()->create([
            'full_name' => 'Different Purok Member',
            'registry' => '8234567890123',
            'purok' => '2A',
            'grupo' => '1',
            'status' => 'MS',
        ]);
        Record::query()->create([
            'full_name' => 'Purok 2B Member',
            'registry' => '7234567890123',
            'purok' => '2B',
            'grupo' => '8',
            'status' => 'MS',
        ]);
        foreach (range(9, 28) as $grupo) {
            Record::query()->create([
                'full_name' => sprintf('Purok 2B Member %02d', $grupo),
                'registry' => sprintf('%013d', 7000000000000 + $grupo),
                'purok' => '2B',
                'grupo' => (string) $grupo,
                'status' => 'MS',
            ]);
        }

        $session = ['login_user' => [
            'id' => $user->id,
            'first_name' => 'Secretary',
            'last_name' => 'Example',
            'tungkulin' => 'Head Secretary',
        ]];
        $this->withSession($session)
            ->get(route('forms.monthly.r4-01', [
                'purok' => 1,
                'kalagayan' => 'MS',
            ]))
            ->assertOk()
            ->assertSee('font:700 8px Tahoma,Arial,sans-serif')
            ->assertSee('Sample Local')
            ->assertSee('ABC-12')
            ->assertSee('Sample District')
            ->assertSee('Member 01')
            ->assertSee('Member 43')
            ->assertSee('Maria Evangelist')
            ->assertSee('Sample Purok')
            ->assertSee('PANGALAN: Sample Manggagawa')
            ->assertSee('Assigned No.: MIN-001')
            ->assertSee('SAMPLE MANGGAGAWA')
            ->assertSee('Member 01')
            ->assertSee('C+')
            ->assertSee('Dcode: D-7')
            ->assertSee('Lcode: ABC-12')
            ->assertSee('PRK-GRP')
            ->assertSee('TUNGKULIN: MANGGAGAWA PAMUNUAN')
            ->assertSee('Member 20')
            ->assertSee('Member 21')
            ->assertSee('Member 40')
            ->assertSee('Member 41')
            ->assertSee('OCTOBER')
            ->assertDontSee('Report month')
            ->assertDontSee('Generate preview')
            ->assertDontSee('name="locale"', false)
            ->assertDontSee('name="lcode"', false)
            ->assertDontSee('Different Status Member')
            ->assertDontSee('Different Purok Member')
            ->assertSee('forms/monthly/r4-01/download?purok=1&amp;kalagayan=MS', false);

        // Exporter temp files go to the system temp directory while running tests (never storage/app).
        $outputDirectory = ExportTempFiles::directory();
        $this->assertStringStartsWith(rtrim(sys_get_temp_dir(), '\/'), $outputDirectory);
        $existingDocs = glob($outputDirectory.'/R4-01*.docx') ?: [];
        $this->withSession($session)
            ->get(route('forms.monthly.r4-01.download', [
                'purok' => 1,
                'kalagayan' => 'MS',
            ]))
            ->assertOk()
            ->assertDownload('R4-01 (MS) Oct 2026.docx');
        foreach (array_diff(glob($outputDirectory.'/R4-01*.docx') ?: [], $existingDocs) as $generatedDoc) {
            @unlink($generatedDoc);
        }
        $this->assertDatabaseCount('r4_01_snapshots', 0);
        $r401Preview = $this->withSession($session)
            ->get(route('forms.monthly.r4-01', ['purok' => 1, 'kalagayan' => 'MS']))
            ->assertDontSee('Download PDF')
            ->assertDontSee('download/pdf')
            ->getContent();
        $this->assertStringContainsString('class="r401-movement-row"', $r401Preview);
        $this->withSession($session)
            ->get('/forms/monthly/r4-01/download/pdf?purok=all&kalagayan=MS')
            ->assertNotFound();
        $this->withSession($session)
            ->get(route('forms.monthly.r4-01.snapshots'))
            ->assertOk()
            ->assertSee('R4-01 Snapshots')
            ->assertSee(route('form-generator', ['panel' => 'monthly', 'monthly_section' => 'monthly-forms']))
            ->assertSee('Open Resumen &amp; R4-01 Generators', false)
            ->assertDontSee('Open R4-01 Generator')
            ->assertDontSee('Open Resumen Generator')
            ->assertSee('No saved monthly session snapshots match these filters.');

        $allPuroksResponse = $this->withSession($session)
            ->get(route('forms.monthly.r4-01', ['purok' => 'all', 'kalagayan' => 'MS']))
            ->assertOk();
        preg_match_all('/<article class="r401-page-sheet">(.*?)<\/article>/s', $allPuroksResponse->getContent(), $allPurokPages);
        $allPurokPageText = array_map('strip_tags', $allPurokPages[1]);
        $this->assertCount(6, $allPurokPageText);
        $this->assertStringContainsString('Member 41', $allPurokPageText[2]);
        $this->assertStringContainsString('Member 43', $allPurokPageText[2]);
        $this->assertStringNotContainsString('Different Purok Member', $allPurokPageText[2]);
        $this->assertStringContainsString('Different Purok Member', $allPurokPageText[3]);
        $this->assertStringContainsString('Purok 2A Manggagawa', $allPurokPageText[3]);
        $this->assertStringContainsString('MIN-002', $allPurokPageText[3]);
        $this->assertStringContainsString('Different Purok Member', $allPurokPageText[3]);
        $this->assertStringNotContainsString('Purok 2B Member', $allPurokPageText[3]);
        $this->assertStringContainsString('2-1', $allPurokPageText[3]);
        $this->assertStringNotContainsString('2A-1', $allPurokPageText[3]);
        $this->assertStringContainsString('Purok 2B Member', $allPurokPageText[4]);
        $this->assertStringContainsString('Purok 2B Manggagawa', $allPurokPageText[4]);
        $this->assertStringContainsString('MIN-003', $allPurokPageText[4]);
        $this->assertStringContainsString('2-8', $allPurokPageText[4]);
        $this->assertStringContainsString('Purok 2B Member 28', $allPurokPageText[5]);
        $this->assertStringContainsString('2-28', $allPurokPageText[5]);
        $this->assertStringNotContainsString('2B-8', $allPurokPageText[3]);
        $this->assertStringNotContainsString('2B-8', $allPurokPageText[4]);
        $reportTableBodies = [];
        foreach ($allPurokPages[1] as $pageHtml) {
            preg_match('/<table class="r401-report-table".*?<tbody>(.*?)<\/tbody>/s', $pageHtml, $bodyMatch);
            preg_match('/<tr\s*(?:class="[^"]*")?\s*>\s*<td>(\d+)<\/td>/', $bodyMatch[1] ?? '', $firstRowMatch);
            $reportTableBodies[] = (int) ($firstRowMatch[1] ?? 0);
        }
        $this->assertSame([1, 21, 41, 1, 1, 21], $reportTableBodies);

        $this->withSession($session)
            ->get(route('forms.monthly.r4-01', ['purok' => 2, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('Different Purok Member')
            ->assertSee('Purok 2B Member')
            ->assertSee('Purok 2A Manggagawa')
            ->assertSee('Purok 2B Manggagawa')
            ->assertSee('MIN-002')
            ->assertSee('MIN-003')
            ->assertSee('2-1')
            ->assertSee('2-28')
            ->assertDontSee('2A-1')
            ->assertDontSee('2B-8');

        $docRow = [
            'code' => 'B',
            'name' => 'Monthly Member Example',
            'registry' => '1234567890123',
            'gender' => 'L',
            'purok_grupo' => '1-4',
            'kapisanan' => 'b',
            'date_of_baptism' => '04/03/2020',
            'doctrinator' => 'Maria Evangelist',
        ];
        $path = app(R401DocxExporter::class)->generate([
            'district' => 'Sample District',
            'dcode' => 'D-7',
            'locale' => 'Sample Local',
            'lcode' => 'ABC-12',
            'month' => 'October',
            'year' => '2026',
            'kalagayan' => 'MS',
            'signatures' => ['MS Secretary', 'KSP Name', '', 'Pastor Name'],
        ], [
            ['maysakop_name' => 'Page One Manggagawa', 'assigned_number' => 'MIN-001', 'page_number' => 1, 'rows' => array_fill(0, 20, $docRow)],
            ['maysakop_name' => 'Page Two Manggagawa', 'assigned_number' => 'MIN-002', 'page_number' => 2, 'rows' => [$docRow]],
        ]);
        try {
            $docx = new \PharData($path);
            $documentXml = $docx['word/document.xml']->getContent();
            $this->assertStringContainsString('Monthly Member Example', $documentXml);
            $this->assertStringContainsString('Sample District', $documentXml);
            $this->assertStringContainsString('Maria Evangelist', $documentXml);
            $this->assertStringContainsString('Dcode: D-7', $documentXml);
            $this->assertStringContainsString('Lcode: ABC-12', $documentXml);
            $this->assertStringContainsString('Page One Manggagawa', $documentXml);
            $this->assertStringContainsString('MIN-001', $documentXml);
            $this->assertStringContainsString('Page Two Manggagawa', $documentXml);
            $this->assertStringContainsString('MIN-002', $documentXml);
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($documentXml, LIBXML_NONET));
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $rowText = '';
            foreach ($xpath->query('//w:tbl[3]/w:tr[2]//w:t') as $text) {
                $rowText .= $text->textContent;
            }
            $this->assertStringContainsString('1234567890123', $rowText);
            $this->assertSame(21, $xpath->query('//w:tbl[3]/w:tr')->length);
            $this->assertSame(8, $xpath->query('//w:tbl')->length);
            $this->assertSame(21, $xpath->query('//w:tbl[7]/w:tr')->length);
            $this->assertSame(
                '21',
                trim($xpath->query('//w:tbl[7]/w:tr[2]/w:tc[1]//w:t')->item(0)->textContent)
            );
            $statusLabels = '';
            foreach ($xpath->query('//w:tbl[1]/w:tr[1]/w:tc[3]//w:t') as $text) {
                $statusLabels .= $text->textContent;
            }
            $this->assertStringContainsString('☑ MS', $statusLabels);
            $this->assertSame(
                'left',
                $xpath->query('//w:tbl[3]/w:tr[2]/w:tc[3]/w:p/w:pPr/w:jc')->item(0)
                    ->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val')
            );
            $this->assertSame(
                'center',
                $xpath->query('//w:tbl[3]/w:tr[2]/w:tc[1]/w:p/w:pPr/w:jc')->item(0)
                    ->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val')
            );
            $this->assertSame('landscape', $xpath->query('//w:sectPr/w:pgSz')->item(0)->getAttributeNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'orient'
            ));
        } finally {
            @unlink($path);
        }
    }

    public function test_r4_01_includes_coded_inactive_and_mk_records_and_session_transfer_at_previous_location(): void
    {
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        DB::table('puroks')->insert([
            [
                'purok_number' => 1,
                'purok_name' => 'Purok One',
                'manggagawa' => 'Worker One',
                'ministerial_id' => 'MIN-001',
                'grupo_start' => 1,
                'grupo_end' => 24,
                'full_purok' => true,
            ],
            [
                'purok_number' => 2,
                'purok_name' => 'Purok Two',
                'manggagawa' => 'Worker Two',
                'ministerial_id' => 'MIN-002',
                'grupo_start' => 1,
                'grupo_end' => 24,
                'full_purok' => true,
            ],
        ]);
        $sessionId = DB::table('monthly_movement_sessions')->insertGetId([
            'active_key' => 'active',
            'status' => 'active',
            'period_month' => '2026-10-01',
            'started_at' => '2026-10-01 09:00:00',
        ]);
        Record::query()->create([
            'full_name' => 'Active A Early',
            'status' => 'MS',
            'purok' => '1',
            'grupo' => '1',
        ]);
        Record::query()->create([
            'full_name' => 'Active A Late',
            'status' => 'MS',
            'purok' => '1',
            'grupo' => '20',
        ]);
        Record::query()->create([
            'full_name' => 'Inactive With DD Code',
            'status' => 'INACTIVE',
            'purok' => '1',
            'grupo' => '1',
            'monthly_code' => 'MS: DD',
        ]);
        Record::query()->create([
            'full_name' => 'MK With MS Code',
            'status' => 'MK',
            'purok' => '1',
            'grupo' => '2',
            'monthly_code' => 'MS: G',
        ]);
        $transfer = Record::query()->create([
            'full_name' => 'Jacquelyn Gascon',
            'registry' => 'RZL0831000164',
            'gender' => 'B',
            'kapisanan' => 'K',
            'date_of_baptism' => '1998-01-06',
            'evangelist' => 'Teodoro Mangandi',
            'status' => 'MS',
            'purok' => '2',
            'grupo' => '8',
            'monthly_code' => 'MS: C+',
        ]);
        DB::table('monthly_session_movements')->insert([
            'session_id' => $sessionId,
            'record_id' => $transfer->id,
            'action' => 'transfer',
            'full_name' => $transfer->full_name,
            'registry' => $transfer->registry,
            'gender' => $transfer->gender,
            'kapisanan' => $transfer->kapisanan,
            'date_of_baptism' => '1998-01-06',
            'evangelist' => $transfer->evangelist,
            'status' => 'MS',
            'monthly_code' => 'MS: C- / MS: C+',
            'previous_purok' => '1',
            'previous_grupo' => '8',
            'destination_description' => '1-8 -> 2-8',
            'previous_values' => json_encode(['status' => 'MS', 'purok' => '1', 'grupo' => '8']),
            'next_values' => json_encode(['status' => 'MS', 'purok' => '2', 'grupo' => '8']),
            'changed_at' => '2026-10-02 10:00:00',
        ]);

        $response = $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.r4-01', ['purok' => 1, 'kalagayan' => 'MS']));
        $response->assertOk()
            ->assertSee('class="r401-movement-row"', false);
        $this->assertMatchesRegularExpression(
            '/<tr class="r401-movement-row">\s*<td>3<\/td>\s*<td>DD<\/td>/',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<tr\s*>\s*<td>1<\/td>\s*<td>A<\/td>/',
            $response->getContent()
        );
        $response->assertOk()
            ->assertViewHas('reportRows', function (array $rows): bool {
                $this->assertSame([
                    'Active A Early',
                    'Active A Late',
                    'Inactive With DD Code',
                    'MK With MS Code',
                    'Jacquelyn Gascon',
                ], array_column($rows, 'name'));
                $this->assertSame(['A', 'A', 'DD', 'G', 'C-'], array_column($rows, 'code'));
                $this->assertSame('1-8', $rows[4]['purok_grupo']);
                $this->assertSame('RZL0831000164', $rows[4]['registry']);

                return true;
            });

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.r4-01', ['purok' => 2, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertViewHas('reportRows', function (array $rows): bool {
                $this->assertSame(['Jacquelyn Gascon'], array_column($rows, 'name'));
                $this->assertSame(['C+'], array_column($rows, 'code'));
                $this->assertSame('2-8', $rows[0]['purok_grupo']);

                return true;
            });
    }

    public function test_resumen_calculates_next_month_totals_from_prior_counts_and_movements(): void
    {
        Storage::fake('local');
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        GlobalVariable::query()->create([
            'locale' => 'Sample Local',
            'lcode' => 'LOC-1',
            'head_lsp' => 'Head LSP Person',
            'ksp_pd' => 'KSP Person',
            'pastor_destinado' => 'Pastor Person',
        ]);
        DB::table('puroks')->insert([
            [
                'purok_number' => 1,
                'purok_name' => 'Purok Uno',
                'manggagawa' => 'Worker One',
                'ministerial_id' => 'MIN-001',
                'grupo_start' => 1,
                'grupo_end' => 24,
                'full_purok' => false,
            ],
            [
                'purok_number' => 2,
                'purok_name' => 'Purok Dos',
                'manggagawa' => 'Worker Two',
                'ministerial_id' => 'MIN-002',
                'grupo_start' => 1,
                'grupo_end' => 24,
                'full_purok' => false,
            ],
        ]);
        foreach ([
            ['2026-06-01', 1, 56, []],
            ['2026-06-01', 2, 20, []],
            ['2026-07-01', 1, 57, ['MS' => ['B' => 1]]],
            ['2026-07-01', 2, 19, ['MS' => ['B' => 2, 'E' => 3]]],
        ] as [$month, $purok, $msCount, $movementCounts]) {
            MonthlyRecordSnapshot::query()->create([
                'month' => $month,
                'purok_number' => $purok,
                'ms_count' => $msCount,
                'uwp_count' => 0,
                'mk_count' => 0,
                'movement_counts' => $movementCounts,
            ]);
        }
        $this->createMonthlySession('2026-07-01');

        $session = ['login_user' => [
            'id' => $user->id,
            'first_name' => 'Secretary',
            'last_name' => 'Example',
            'tungkulin' => 'Head Secretary',
        ]];
        $this->withSession($session)
            ->get(route('forms.monthly.resumen'))
            ->assertOk()
            ->assertSee('Resumen Generator')
            ->assertSee('Download DOCX')
            ->assertDontSee('Download PDF')
            ->assertDontSee('window.print()')
            ->assertSee('Head LSP Person')
            ->assertSee('KSP Person')
            ->assertSee('Pastor Person')
            ->assertSee('Worker One')
            ->assertSee('Worker Two')
            ->assertViewHas('report', function (array $report): bool {
                $this->assertSame(56, $report['rows'][0]['ms']['A']);
                $this->assertSame(1, $report['rows'][0]['ms']['B']);
                $this->assertSame(57, $report['rows'][0]['ms_total']);
                $this->assertSame(20, $report['rows'][1]['ms']['A']);
                $this->assertSame(2, $report['rows'][1]['ms']['B']);
                $this->assertSame(3, $report['rows'][1]['ms']['E']);
                $this->assertSame(19, $report['rows'][1]['ms_total']);
                $this->assertSame(76, $report['totals']['ms']['A']);
                $this->assertSame(3, $report['totals']['ms']['B']);
                $this->assertSame(3, $report['totals']['ms']['E']);
                $this->assertSame(76, $report['totals']['ms_total']);

                return true;
            });

        $report = app(MonthlyResumenReport::class)
            ->generate(Carbon::createFromFormat('Y-m', '2026-07'));
        $docxPath = app(ResumenDocxExporter::class)
            ->generate($report, 'Sample Local', 'LOC-1', [
                'head_lsp' => 'Head LSP Person',
                'ksp_pd' => 'KSP Person',
                'pastor_destinado' => 'Pastor Person',
            ]);
        try {
            $docx = new \PharData($docxPath);
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($docx['word/document.xml']->getContent(), LIBXML_NONET));
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $cellText = fn (string $query): string => trim($xpath->query($query)->item(0)?->textContent ?? '');
            $this->assertSame('56', $cellText('//w:tbl[2]/w:tr[5]/w:tc[3]//w:t'));
            $this->assertSame('1', $cellText('//w:tbl[2]/w:tr[5]/w:tc[4]//w:t'));
            $this->assertSame('57', $cellText('//w:tbl[2]/w:tr[5]/w:tc[12]//w:t'));
            $this->assertSame('20', $cellText('//w:tbl[2]/w:tr[6]/w:tc[3]//w:t'));
            $this->assertSame('3', $cellText('//w:tbl[2]/w:tr[6]/w:tc[9]//w:t'));
            $this->assertSame('19', $cellText('//w:tbl[2]/w:tr[6]/w:tc[12]//w:t'));
            $this->assertStringContainsString('HEAD LSP PERSON', $cellText('//w:tbl[3]/w:tr[1]/w:tc[1]'));
            $this->assertStringContainsString('KSP PERSON', $cellText('//w:tbl[3]/w:tr[1]/w:tc[2]'));
            $this->assertStringContainsString('PASTOR PERSON', $cellText('//w:tbl[3]/w:tr[1]/w:tc[3]'));
        } finally {
            unset($docx);
            @unlink($docxPath);
        }

        $download = $this->withSession($session)
            ->get(route('forms.monthly.resumen.download'))
            ->assertOk()
            ->assertDownload('Resumen Jul 2026.docx');
        $this->assertDatabaseCount('r4_01_snapshots', 0);
        $this->withSession($session)
            ->get(route('forms.monthly.r4-01.snapshots'))
            ->assertOk()
            ->assertSee('Resumen & R4-01 Snapshots')
            ->assertSee('No saved monthly session snapshots match these filters.');
    }

    public function test_monthly_r401_snapshot_summary_describes_archived_actions_and_codes(): void
    {
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        $sessionId = DB::table('monthly_movement_sessions')->insertGetId([
            'active_key' => null,
            'status' => 'completed',
            'period_month' => '2026-10-01',
            'started_by' => 'Secretary',
            'started_at' => '2026-10-01 09:00:00',
            'completed_by' => 'Head Secretary',
            'completed_at' => '2026-10-01 10:00:00',
        ]);
        $snapshot = R401Snapshot::query()->create([
            'session_id' => $sessionId,
            'period_month' => '2026-10-01',
            'report_type' => 'Monthly session',
            'kalagayan' => '',
            'purok_selection' => 'All Puroks',
            'format' => 'docx',
            'file_path' => 'monthly-session-snapshots/2026-10/session-'.$sessionId.'.docx',
            'file_name' => 'Monthly_October_2026_RESUMEN_MS_UWP.docx',
            'generated_by' => 'Head Secretary',
        ]);
        $insertHistory = static function (array $values) use ($sessionId): void {
            DB::table('permanent_history')->insert($values + [
                'session_id' => $sessionId,
                'period_month' => '2026-10-01',
                'registry' => null,
                'gender' => null,
                'kapisanan' => null,
                'destination_description' => null,
                'description' => null,
                'changed_by' => 'Head Secretary',
                'changed_at' => '2026-10-01 10:00:00',
                'date_of_baptism' => null,
                'evangelist' => null,
            ]);
        };

        $insertHistory([
            'record_id' => 1,
            'action' => 'add',
            'full_name' => 'Charles Andrei Galvan',
            'status' => 'MS',
            'monthly_code' => 'MS: B',
            'previous_values' => json_encode(['status' => 'MS', 'purok' => '1', 'grupo' => '1']),
            'next_values' => json_encode(['status' => 'MS', 'purok' => '1', 'grupo' => '1']),
        ]);
        $insertHistory([
            'record_id' => 2,
            'action' => 'add',
            'full_name' => 'Juan Dela Cruz',
            'status' => 'UWP',
            'monthly_code' => 'UWP: B',
            'previous_values' => json_encode(['status' => 'UWP', 'purok' => '2', 'grupo' => '3']),
            'next_values' => json_encode(['status' => 'UWP', 'purok' => '2', 'grupo' => '3']),
        ]);
        $insertHistory([
            'record_id' => 2,
            'action' => 'status',
            'full_name' => 'Juan Dela Cruz',
            'status' => 'MS',
            'monthly_code' => 'MS: G',
            'previous_values' => json_encode(['status' => 'MS', 'purok' => '2', 'grupo' => '3']),
            'next_values' => json_encode(['status' => 'UWP', 'purok' => '2', 'grupo' => '3']),
        ]);
        $insertHistory([
            'record_id' => 3,
            'action' => 'subtract',
            'full_name' => 'Kimberly Ostil',
            'status' => 'UWP',
            'monthly_code' => 'UWP: D',
            'previous_values' => json_encode(['status' => 'UWP', 'purok' => '1', 'grupo' => '4']),
            'next_values' => json_encode(['status' => 'INACTIVE', 'purok' => '1', 'grupo' => '4']),
        ]);
        $insertHistory([
            'record_id' => 4,
            'action' => 'transfer',
            'full_name' => 'Emman Ostil',
            'status' => 'MS',
            'monthly_code' => 'MS: C- / MS: C+',
            'previous_values' => json_encode(['status' => 'MS', 'purok' => '4', 'grupo' => '9']),
            'next_values' => json_encode(['status' => 'MS', 'purok' => '3', 'grupo' => '5']),
        ]);
        $insertHistory([
            'record_id' => 5,
            'action' => 'status',
            'full_name' => 'Jed Wong',
            'status' => 'UWP',
            'monthly_code' => 'UWP: E',
            'previous_values' => json_encode(['status' => 'UWP', 'purok' => '5', 'grupo' => '2']),
            'next_values' => json_encode(['status' => 'MK', 'purok' => '5', 'grupo' => '2']),
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.r4-01.snapshots'))
            ->assertOk()
            ->assertSee('Summary')
            ->assertSee(route('forms.monthly.r4-01.snapshots.summary', $snapshot), false);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.monthly.r4-01.snapshots.summary', $snapshot))
            ->assertOk()
            ->assertJsonPath('period', 'October 2026')
            ->assertJsonCount(5, 'entries')
            ->assertJsonPath('entries.0.name', 'Charles Andrei Galvan')
            ->assertJsonPath('entries.0.summary', 'in: MS (Code: B)')
            ->assertJsonPath('entries.1.name', 'Juan Dela Cruz')
            ->assertJsonPath('entries.1.summary', 'transfer: MS (Code: G) -> UWP (Code: B)')
            ->assertJsonPath('entries.2.summary', 'out: UWP (Code: D)')
            ->assertJsonPath('entries.3.summary', 'transfer 4-9 (Code: C-) -> 3-5 (Code: C+)')
            ->assertJsonPath('entries.4.summary', 'transfer: UWP (Code: E) -> MK');
    }

    public function test_resumen_assigns_snapshot_counts_to_separate_split_purok_rows(): void
    {
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        DB::table('puroks')->insert([
            [
                'purok_number' => 2,
                'purok_name' => 'Purok 2A',
                'manggagawa' => 'Worker 2A',
                'ministerial_id' => 'MIN-002A',
                'grupo_start' => 1,
                'grupo_end' => 6,
                'full_purok' => false,
            ],
            [
                'purok_number' => 2,
                'purok_name' => 'Purok 2B',
                'manggagawa' => 'Worker 2B',
                'ministerial_id' => 'MIN-002B',
                'grupo_start' => 7,
                'grupo_end' => 24,
                'full_purok' => false,
            ],
        ]);
        Record::query()->create(['full_name' => 'Purok 2A Member', 'purok' => '2', 'grupo' => '2', 'status' => 'MS']);
        Record::query()->create(['full_name' => 'Purok 2B Member', 'purok' => '2', 'grupo' => '8', 'status' => 'MS']);
        foreach ([
            ['2026-06-01', 20, []],
            ['2026-07-01', 19, ['MS' => ['B' => 2, 'E' => 3]]],
        ] as [$month, $count, $movementCounts]) {
            MonthlyRecordSnapshot::query()->create([
                'month' => $month,
                'purok_number' => 2,
                'ms_count' => $count,
                'uwp_count' => 0,
                'mk_count' => 0,
                'movement_counts' => $movementCounts,
            ]);
        }
        $this->createMonthlySession('2026-07-01');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.resumen'))
            ->assertOk()
            ->assertSee('Purok 2A')
            ->assertSee('Purok 2B')
            ->assertViewHas('report', function (array $report): bool {
                $rows = $report['rows']->all();
                $this->assertCount(2, $rows);
                $this->assertSame(['Purok 2A', 'Purok 2B'], array_column($rows, 'purok_label'));
                $this->assertSame(20, array_sum(array_column(array_column($rows, 'ms'), 'A')));
                $this->assertSame(2, array_sum(array_column(array_column($rows, 'ms'), 'B')));
                $this->assertSame(3, array_sum(array_column(array_column($rows, 'ms'), 'E')));
                $this->assertSame(19, array_sum(array_column($rows, 'ms_total')));
                $this->assertSame(19, $report['totals']['ms_total']);

                return true;
            });
    }

    public function test_completed_transfer_movement_is_assigned_to_its_archived_grupo_segment(): void
    {
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        DB::table('puroks')->insert([
            [
                'purok_number' => 2,
                'purok_name' => 'Purok 2A',
                'manggagawa' => 'Worker 2A',
                'grupo_start' => 1,
                'grupo_end' => 6,
                'full_purok' => false,
            ],
            [
                'purok_number' => 2,
                'purok_name' => 'Purok 2B',
                'manggagawa' => 'Worker 2B',
                'grupo_start' => 7,
                'grupo_end' => 11,
                'full_purok' => false,
            ],
            [
                'purok_number' => 5,
                'purok_name' => 'Purok 5',
                'manggagawa' => 'Worker 5',
                'grupo_start' => 1,
                'grupo_end' => 24,
                'full_purok' => true,
            ],
        ]);
        foreach (range(1, 10) as $group) {
            Record::query()->create([
                'full_name' => 'Purok 2A weighting member '.$group,
                'purok' => '2',
                'grupo' => (string) min($group, 6),
                'status' => 'MS',
            ]);
        }
        $aira = Record::query()->create([
            'full_name' => 'AIRA LLENARESAS',
            'registry' => 'AIRA123456789',
            'gender' => 'B',
            'purok' => '5',
            'grupo' => '6',
            'status' => 'MS',
        ]);
        $this->createMonthlySession('2026-10-01', 'completed');
        $sessionId = (int) DB::table('monthly_movement_sessions')->whereDate('period_month', '2026-10-01')->value('id');

        MonthlyRecordSnapshot::query()->create([
            'month' => '2026-10-01',
            'purok_number' => 2,
            'ms_count' => 10,
            'uwp_count' => 0,
            'mk_count' => 0,
            'movement_counts' => ['MS' => ['C-' => 1, 'C+' => 1]],
            'session_generated' => true,
        ]);
        DB::table('permanent_history')->insert([
            [
                'session_id' => $sessionId,
                'record_id' => $aira->id,
                'action' => 'transfer',
                'full_name' => $aira->full_name,
                'registry' => $aira->registry,
                'status' => 'MS',
                'monthly_code' => 'MS: C+',
                'changed_at' => now(),
                'previous_values' => json_encode(['status' => 'MS', 'purok' => '5', 'grupo' => '6']),
                'next_values' => json_encode(['status' => 'MS', 'purok' => '5', 'grupo' => '6']),
            ],
            [
                'session_id' => $sessionId,
                'record_id' => $aira->id,
                'action' => 'transfer',
                'full_name' => $aira->full_name,
                'registry' => $aira->registry,
                'status' => 'MS',
                'monthly_code' => 'MS: C- / MS: C+',
                'changed_at' => now(),
                'previous_values' => json_encode(['status' => 'MS', 'purok' => '2', 'grupo' => '10']),
                'next_values' => json_encode(['status' => 'MS', 'purok' => '5', 'grupo' => '6']),
            ],
        ]);

        $report = app(MonthlyResumenReport::class)
            ->generate(Carbon::createFromFormat('Y-m-d', '2026-10-01'), true);
        $purok2a = collect($report['rows'])->firstWhere('purok_label', 'Purok 2A');
        $purok2b = collect($report['rows'])->firstWhere('purok_label', 'Purok 2B');
        $this->assertSame(0, $purok2a['ms']['C-']);
        $this->assertSame(1, $purok2b['ms']['C-']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.r4-01', ['purok' => 2, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('AIRA LLENARESAS')
            ->assertSee('C-')
            ->assertSee('2-10');
    }

    public function test_resumen_starts_a_fresh_current_month_when_no_session_exists(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3));
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        DB::table('puroks')->insert([
            'purok_number' => 1,
            'purok_name' => 'Purok Uno',
            'manggagawa' => 'Worker One',
            'ministerial_id' => 'MIN-001',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => false,
        ]);
        Record::query()->create([
            'full_name' => 'Current Opening Member',
            'purok' => '1',
            'grupo' => '1',
            'status' => 'MS',
        ]);
        MonthlyRecordSnapshot::query()->create([
            'month' => '2025-10-01',
            'purok_number' => 1,
            'ms_count' => 51,
            'uwp_count' => 11,
            'mk_count' => 0,
            'movement_counts' => ['MS' => ['B' => 2, 'D' => 3]],
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.resumen'))
            ->assertOk()
            ->assertSee('OCTOBER 2026')
            ->assertViewHas('report', function (array $report): bool {
                $this->assertSame('2026-10', $report['month']->format('Y-m'));
                $this->assertSame(1, $report['rows'][0]['ms']['A']);
                $this->assertSame(0, $report['rows'][0]['ms']['B']);
                $this->assertSame(0, $report['rows'][0]['ms']['D']);
                $this->assertSame(1, $report['rows'][0]['ms_total']);

                return true;
            });
    }

    public function test_resumen_after_completed_session_uses_final_counts_and_archive_reconstructs_opening(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3));
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        DB::table('puroks')->insert([
            'purok_number' => 1,
            'purok_name' => 'Purok Uno',
            'manggagawa' => 'Worker One',
            'ministerial_id' => 'MIN-001',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => false,
        ]);
        foreach (range(1, 4) as $grupo) {
            Record::query()->create([
                'full_name' => 'MS Opening '.$grupo,
                'purok' => '1',
                'grupo' => (string) $grupo,
                'status' => 'MS',
            ]);
        }
        foreach (range(5, 6) as $grupo) {
            Record::query()->create([
                'full_name' => 'UWP Opening '.$grupo,
                'purok' => '1',
                'grupo' => (string) $grupo,
                'status' => 'UWP',
            ]);
        }
        $this->createMonthlySession('2026-10-01', 'completed');
        MonthlyRecordSnapshot::query()->create([
            'month' => '2026-10-01',
            'purok_number' => 1,
            'ms_count' => 4,
            'uwp_count' => 2,
            'mk_count' => 0,
            'movement_counts' => ['MS' => ['B' => 3, 'D' => 1], 'UWP' => ['G' => 2]],
            'session_generated' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.resumen'))
            ->assertOk()
            ->assertViewHas('report', function (array $report): bool {
                $this->assertSame('2026-10', $report['month']->format('Y-m'));
                $this->assertSame(4, $report['rows'][0]['ms']['A']);
                $this->assertSame(0, $report['rows'][0]['ms']['B']);
                $this->assertSame(0, $report['rows'][0]['ms']['D']);
                $this->assertSame(2, $report['rows'][0]['uwp']['A']);
                $this->assertSame(0, $report['rows'][0]['uwp']['G']);
                $this->assertSame(4, $report['rows'][0]['ms_total']);
                $this->assertSame(2, $report['rows'][0]['uwp_total']);

                return true;
            });

        $archiveReport = app(MonthlyResumenReport::class)
            ->generate(Carbon::parse('2026-10-01'), true);
        $this->assertSame(2, $archiveReport['rows'][0]['ms']['A']);
        $this->assertSame(3, $archiveReport['rows'][0]['ms']['B']);
        $this->assertSame(1, $archiveReport['rows'][0]['ms']['D']);
        $this->assertSame(4, $archiveReport['rows'][0]['uwp']['A']);
        $this->assertSame(2, $archiveReport['rows'][0]['uwp']['G']);
        $this->assertSame(4, $archiveReport['rows'][0]['ms_total']);
        $this->assertSame(2, $archiveReport['rows'][0]['uwp_total']);
    }

    public function test_monthly_archive_resumen_reconciles_ending_totals_to_current_records(): void
    {
        DB::table('puroks')->insert([
            'purok_number' => 1,
            'purok_name' => 'Purok Uno',
            'manggagawa' => 'Worker One',
            'ministerial_id' => 'MIN-001',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => true,
        ]);
        foreach ([['MS', '1'], ['MS', '2'], ['UWP', '3'], ['UWP', '']] as $index => [$status, $grupo]) {
            Record::query()->create([
                'full_name' => 'Current Member '.$index,
                'purok' => '1',
                'grupo' => $grupo,
                'status' => $status,
            ]);
        }
        $this->createMonthlySession('2026-10-01', 'completed');
        MonthlyRecordSnapshot::query()->create([
            'month' => '2026-10-01',
            'purok_number' => 1,
            'ms_count' => 3,
            'uwp_count' => 1,
            'mk_count' => 0,
            'movement_counts' => ['MS' => ['B' => 1], 'UWP' => ['G' => 1]],
            'session_generated' => true,
        ]);

        $report = app(MonthlyResumenReport::class)
            ->generate(Carbon::parse('2026-10-01'), true, true);

        $this->assertSame(2, $report['totals']['ms_total']);
        $this->assertSame(2, $report['totals']['uwp_total']);
        $this->assertSame(1, $report['rows'][0]['ms']['A']);
        $this->assertSame(1, $report['rows'][0]['ms']['B']);
        $this->assertSame(3, $report['rows'][0]['uwp']['A']);
        $this->assertSame(1, $report['rows'][0]['uwp']['G']);
    }

    public function test_resumen_and_r4_01_count_add_reactivation_and_status_entry_consistently(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3));
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        DB::table('puroks')->insert([
            'purok_number' => 1,
            'purok_name' => 'Purok Uno',
            'manggagawa' => 'Worker One',
            'ministerial_id' => 'MIN-001',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => false,
        ]);
        $sessionId = DB::table('monthly_movement_sessions')->insertGetId([
            'active_key' => 'active',
            'status' => 'active',
            'period_month' => '2026-10-01',
            'started_by' => 'Secretary Example',
            'started_at' => '2026-10-01 09:00:00',
        ]);
        $added = Record::query()->create([
            'full_name' => 'New Add Member',
            'registry' => 'RZL12345678901',
            'status' => 'MS',
            'purok' => '1',
            'grupo' => '1',
            'monthly_code' => 'MS: B',
        ]);
        $reactivated = Record::query()->create([
            'full_name' => 'Reactivated Member',
            'registry' => 'RZL12345678902',
            'status' => 'MS',
            'purok' => '1',
            'grupo' => '2',
            'monthly_code' => 'MS: B',
        ]);
        $statusChange = Record::query()->create([
            'full_name' => 'Status Entry Member',
            'registry' => 'RZL12345678903',
            'status' => 'MS',
            'purok' => '1',
            'grupo' => '3',
            'monthly_code' => 'MS: B',
        ]);
        $previousValues = [
            'status' => 'UWP',
            'purok' => '1',
            'grupo' => '3',
            'monthly_code' => null,
            'date_of_entry' => null,
            'reason_of_r2_10_endorsement' => null,
            'log_section' => null,
        ];
        $nextValues = [
            ...$previousValues,
            'status' => 'MS',
            'monthly_code' => 'MS: B',
        ];
        $movementId = DB::table('monthly_session_movements')->insertGetId([
            'session_id' => $sessionId,
            'record_id' => $statusChange->id,
            'action' => 'status',
            'full_name' => $statusChange->full_name,
            'status' => 'UWP',
            'monthly_code' => 'UWP: G',
            'previous_purok' => '1',
            'previous_grupo' => '3',
            'destination_description' => 'UWP -> MS',
            'previous_values' => json_encode($previousValues),
            'next_values' => json_encode($nextValues),
            'changed_at' => now(),
        ]);
        foreach ([
            [$added, 'add', 'in', 'MS: B', null],
            [$reactivated, 'reactivate', 'in', 'MS: B', null],
            [$statusChange, 'status', 'out', 'UWP: G', $movementId],
            [$statusChange, 'status', 'in', 'MS: B', $movementId],
        ] as [$record, $action, $direction, $code, $linkedMovementId]) {
            DB::table('monthly_session_activities')->insert([
                'session_id' => $sessionId,
                'session_movement_id' => $linkedMovementId,
                'record_id' => $record->id,
                'action' => $action,
                'direction' => $direction,
                'full_name' => $record->full_name,
                'status' => str_starts_with($code, 'UWP:') ? 'UWP' : 'MS',
                'monthly_code' => $code,
                'purok' => '1',
                'grupo' => $record->grupo,
                'changed_at' => now(),
            ]);
        }

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.resumen'))
            ->assertOk()
            ->assertViewHas('report', function (array $report): bool {
                $this->assertSame(3, $report['rows'][0]['ms']['B']);
                $this->assertSame(1, $report['rows'][0]['uwp']['G']);

                return true;
            });

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('forms.monthly.r4-01', ['purok' => 1, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertViewHas('reportRows', function (array $rows) use ($added, $reactivated, $statusChange): bool {
                $bRows = array_values(array_filter($rows, fn (array $row): bool => $row['code'] === 'B'));
                $this->assertCount(3, $bRows);
                $this->assertEqualsCanonicalizing(
                    [$added->full_name, $reactivated->full_name, $statusChange->full_name],
                    array_column($bRows, 'name')
                );

                return true;
            });
    }

    public function test_r4_01_can_use_the_secretary_assigned_to_each_purok(): void
    {
        $user = $this->makeSecretary(['form_generator_allow' => true]);
        $purokSecretary = $this->makeSecretary([
            'first_name' => 'Purok',
            'last_name' => 'Secretary',
        ]);
        GlobalVariable::query()->create([
            'locale' => 'Sample Local',
            'lcode' => 'ABC-12',
            'district' => 'Sample District',
            'dcode' => 'D-7',
            'kalihim_ms' => 'Global MS Secretary',
            'ksp_pd' => 'KSP Name',
            'r401_use_purok_secretary' => true,
        ]);
        DB::table('puroks')->insert([
            'purok_number' => 1,
            'purok_name' => 'Sample Purok',
            'secretary' => $purokSecretary->id,
            'manggagawa' => 'Sample Manggagawa',
            'ministerial_id' => 'MIN-001',
            'grupo_start' => 1,
            'grupo_end' => 24,
            'full_purok' => false,
        ]);
        Record::query()->create([
            'full_name' => 'Sample Member',
            'registry' => '1234567890123',
            'gender' => 'Female',
            'purok' => '1',
            'grupo' => '1',
            'kapisanan' => 'Kadiwa',
            'status' => 'MS',
        ]);

        $session = ['login_user' => ['id' => $user->id]];
        $preview = $this->withSession($session)
            ->get(route('forms.monthly.r4-01', ['purok' => 1, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('PUROK SECRETARY')
            ->assertDontSee('GLOBAL MS SECRETARY')
            ->getContent();

        $this->assertStringContainsString('PUROK SECRETARY', $preview);

        $download = $this->withSession($session)
            ->get(route('forms.monthly.r4-01.download', ['purok' => 1, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertDownload();
        $archive = new \ZipArchive;
        $this->assertSame(true, $archive->open($download->baseResponse->getFile()->getPathname()));
        $document = $archive->getFromName('word/document.xml');
        $this->assertNotFalse($document);
        $this->assertStringContainsString('PUROK SECRETARY', $document);
        $this->assertStringNotContainsString('GLOBAL MS SECRETARY', $document);
        $archive->close();
        @unlink($download->baseResponse->getFile()->getPathname());
    }

    public function test_secretary_can_search_append_and_delete_dated_record_notes(): void
    {
        $user = $this->makeSecretary(['note_checker_allow' => true]);
        $record = Record::query()->create([
            'full_name' => 'Member With Notes',
            'registry' => 'REG-NOTE-1',
            'log_section' => '09/06/2025: Existing note, includes commas',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.notes').'?q=REG-NOTE-1')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $record->id)
            ->assertJsonPath('0.notes.0', '09/06/2025: Existing note, includes commas');

        $response = $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('forms.notes.append', $record), [
                'note' => 'Should be on deliberation, discuss with head secretary',
            ])
            ->assertOk()
            ->assertJsonCount(2, 'notes');

        $today = now()->timezone('Asia/Manila')->format('d/m/Y');
        $this->assertSame(
            $today.': Should be on deliberation, discuss with head secretary',
            $response->json('notes.1'),
        );
        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'log_section' => '09/06/2025: Existing note, includes commas, '.$today.': Should be on deliberation, discuss with head secretary',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->deleteJson(route('forms.notes.delete', ['record' => $record, 'noteIndex' => 0]))
            ->assertOk()
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0', $today.': Should be on deliberation, discuss with head secretary');
    }

    public function test_note_checker_search_finds_records_without_notes(): void
    {
        $user = $this->makeSecretary(['note_checker_allow' => true]);
        Record::query()->create(['full_name' => 'Eniron Mercado', 'registry' => 'REG-EM-1', 'status' => 'MS']);
        Record::query()->create(['full_name' => 'Noted Person', 'registry' => 'REG-NP-1', 'log_section' => '01/09/2025: First']);

        // No search: only records that already have notes.
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.notes'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Noted Person');

        // A search finds any record, so a first note can be added.
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.notes').'?q=Eniron')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Eniron Mercado')
            ->assertJsonPath('0.status', 'MS')
            ->assertJsonPath('0.notes', []);
    }

    public function test_note_checker_needs_its_own_permission(): void
    {
        // Form Generator access alone no longer opens the notes.
        $user = $this->makeSecretary(['form_generator_allow' => true, 'note_checker_allow' => false]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('note-checker'))
            ->assertForbidden();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('forms.notes'))
            ->assertForbidden();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('form-generator'))
            ->assertOk()
            ->assertDontSee('href="'.route('note-checker').'"', false);

        // And the Note Checker works without Form Generator access.
        $noteOnly = $this->makeSecretary(['form_generator_allow' => false, 'note_checker_allow' => true]);
        $this->withSession(['login_user' => ['id' => $noteOnly->id]])
            ->get(route('form-generator'))
            ->assertForbidden();
        $this->withSession(['login_user' => ['id' => $noteOnly->id]])
            ->getJson(route('forms.notes'))
            ->assertOk();
    }

    private function makeSecretary(array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
    }

    private function createMonthlySession(string $periodMonth, string $status = 'active'): void
    {
        DB::table('monthly_movement_sessions')->insert([
            'active_key' => $status === 'active' ? 'active' : null,
            'status' => $status,
            'period_month' => $periodMonth,
            'started_by' => 'Secretary Example',
            'started_at' => $periodMonth.' 09:00:00',
            'completed_by' => $status === 'completed' ? 'Head Secretary' : null,
            'completed_at' => $status === 'completed' ? $periodMonth.' 10:00:00' : null,
        ]);
    }
}
