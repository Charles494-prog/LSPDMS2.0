<?php

namespace Tests\Feature;

use App\Models\GlobalVariable;
use App\Models\MonthlyMovementSession;
use App\Models\R402GeneratedForm;
use App\Models\R402Hakbang;
use App\Models\Record;
use App\Models\User;
use App\Services\ExportTempFiles;
use App\Services\MonthlySessionSnapshotExporter;
use App\Services\PurokSelection;
use App\Services\R401ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Purok 2a (id 3, grupo 1-6) and Purok 2b (id 4, grupo 7-13) share purok_number 2, mirroring the live configuration.
 */
class PurokSegmentReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretary = User::query()->create([
            'gmail' => 'segment-secretary@example.test',
            'password' => 'password',
            'first_name' => 'Segment',
            'last_name' => 'Secretary',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'form_generator_allow' => true,
        ]);
        DB::table('puroks')->insert([
            ['id' => 1, 'purok_number' => 1, 'purok_name' => 'Purok 1', 'manggagawa' => 'Purok 1 Manggagawa', 'ministerial_id' => 'MIN-1', 'grupo_start' => 1, 'grupo_end' => 24, 'full_purok' => true],
            // Inserted 2b before 2a on purpose: ordering must follow purok_number, grupo_start, id.
            ['id' => 4, 'purok_number' => 2, 'purok_name' => 'Purok 2b', 'manggagawa' => 'Purok 2b Manggagawa', 'ministerial_id' => 'MIN-2B', 'grupo_start' => 7, 'grupo_end' => 13, 'full_purok' => false],
            ['id' => 3, 'purok_number' => 2, 'purok_name' => 'Purok 2a', 'manggagawa' => 'Purok 2a Manggagawa', 'ministerial_id' => 'MIN-2A', 'grupo_start' => 1, 'grupo_end' => 6, 'full_purok' => false],
        ]);
        // Names interleave across segments so a flat, merged list would number them differently.
        foreach ([
            ['Ana Segment', '2', '3'],
            ['Ben Segment', '2', '8'],
            ['Carla Segment', '2', '3'],
            ['Dan Segment', '2', '8'],
            ['Uno Member', '1', '5'],
            ['Outside Range Member', '2', '20'],
        ] as $index => [$name, $purok, $grupo]) {
            Record::query()->create([
                'full_name' => $name,
                'registry' => sprintf('%013d', $index + 1),
                'gender' => 'Male',
                'purok' => $purok,
                'grupo' => $grupo,
                'kapisanan' => 'Kadiwa',
                'status' => 'MS',
                'monthly_code' => 'MS: A',
            ]);
        }
        GlobalVariable::query()->create([
            'locale' => 'Sample Local',
            'lcode' => 'ABC-12',
            'district' => 'Sample District',
            'dcode' => 'D-7',
            'kalihim_ms' => 'Global MS Kalihim',
            'kalihim_uwp' => 'Global UWP Kalihim',
            'ksp_pd' => 'KSP Name',
            'pastor_destinado' => 'Pastor Name',
        ]);
    }

    public function test_r401_segment_token_shows_only_that_segment(): void
    {
        $response = $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => '2:3', 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('Ana Segment')
            ->assertSee('Carla Segment')
            ->assertSee('PANGALAN: Purok 2a Manggagawa')
            ->assertDontSee('Ben Segment')
            ->assertDontSee('Dan Segment')
            ->assertDontSee('Purok 2b Manggagawa')
            ->assertDontSee('Uno Member');
        $this->assertSame([3], array_column($response->viewData('reportPages'), 'purok_id'));

        $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => '2:4', 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('Ben Segment')
            ->assertSee('Dan Segment')
            ->assertSee('Purok 2b Manggagawa')
            ->assertDontSee('Ana Segment')
            ->assertDontSee('Carla Segment')
            ->assertDontSee('Purok 2a Manggagawa');
    }

    public function test_r401_filter_menu_renders_segment_tokens_in_purok_order(): void
    {
        $content = $this->asSecretary()
            ->get(route('forms.monthly.r4-01'))
            ->assertOk()
            ->assertSee('data-purok="1"', false)
            ->assertSee('data-purok="2:3"', false)
            ->assertSee('data-purok="2:4"', false)
            ->assertDontSee('data-purok="2"', false)
            ->getContent();

        $this->assertLessThan(strpos($content, 'data-purok="2:4"'), strpos($content, 'data-purok="2:3"'));
    }

    public function test_legacy_purok_number_still_returns_every_segment_with_its_own_pages(): void
    {
        $response = $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => 2, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('Ana Segment')
            ->assertSee('Ben Segment')
            ->assertSee('Purok 2a Manggagawa')
            ->assertSee('Purok 2b Manggagawa');
        $pages = $response->viewData('reportPages');

        $this->assertSame([3, 4], array_column($pages, 'purok_id'));
        $this->assertSame([1, 1], array_column($pages, 'page_number'));
        $this->assertSame(['MIN-2A', 'MIN-2B'], array_column($pages, 'assigned_number'));
        $this->assertSame(['Ana Segment', 'Carla Segment'], array_column($pages[0]['rows'], 'name'));
        $this->assertSame(['Ben Segment', 'Dan Segment'], array_column($pages[1]['rows'], 'name'));
    }

    public function test_multi_select_tokens_combine_segments_and_whole_puroks(): void
    {
        $response = $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => '1,2:4', 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('Uno Member')
            ->assertSee('Ben Segment')
            ->assertSee('Dan Segment')
            ->assertSee('2 selected')
            ->assertDontSee('Ana Segment')
            ->assertDontSee('Carla Segment');
        $this->assertSame([1, 4], array_column($response->viewData('reportPages'), 'purok_id'));

        $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => 'all', 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('Uno Member')
            ->assertSee('Ana Segment')
            ->assertSee('Ben Segment');
    }

    public function test_invalid_purok_tokens_are_rejected(): void
    {
        foreach (['1:3', '2:999', '9', 'abc', '2a', 'all,1', '2:', ':3', '2:3:4', str_repeat('1,', 101).'1'] as $token) {
            $this->asSecretary()
                ->from(route('forms.monthly.r4-01'))
                ->get(route('forms.monthly.r4-01', ['purok' => $token, 'kalagayan' => 'MS']))
                ->assertRedirect(route('forms.monthly.r4-01'))
                ->assertSessionHasErrors('purok');
            $this->asSecretary()
                ->get(route('forms.monthly.r4-01.download', ['purok' => $token, 'kalagayan' => 'MS']))
                ->assertSessionHasErrors('purok');
            $this->asSecretary()
                ->get(route('forms.weekly.r4-02', ['purok' => $token, 'kalagayan' => 'MS']))
                ->assertSessionHasErrors('purok');
        }

        $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => ['2'], 'kalagayan' => 'MS']))
            ->assertSessionHasErrors('purok');
    }

    public function test_r401_download_contains_only_the_selected_segment(): void
    {
        $storageDirectory = storage_path('app/monthly-forms');
        $before = glob($storageDirectory.'/*.docx') ?: [];

        $download = $this->asSecretary()
            ->get(route('forms.monthly.r4-01.download', ['purok' => '2:4', 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertDownload('R4-01 (MS) '.now()->format('M Y').'.docx');
        $path = $download->baseResponse->getFile()->getPathname();
        try {
            $this->assertStringStartsWith(ExportTempFiles::directory(), $path);
            $archive = new \ZipArchive;
            $this->assertTrue($archive->open($path));
            $document = (string) $archive->getFromName('word/document.xml');
            $archive->close();
            $this->assertStringContainsString('Ben Segment', $document);
            $this->assertStringContainsString('Dan Segment', $document);
            $this->assertStringNotContainsString('Ana Segment', $document);
            $this->assertStringNotContainsString('Carla Segment', $document);
        } finally {
            @unlink($path);
        }
        $this->assertSame($before, glob($storageDirectory.'/*.docx') ?: []);
    }

    public function test_temp_directory_is_outside_storage_while_testing(): void
    {
        $directory = ExportTempFiles::directory();

        $this->assertDirectoryExists($directory);
        $this->assertStringStartsWith(rtrim(sys_get_temp_dir(), '\\/'), $directory);
        $this->assertStringNotContainsString(str_replace('\\', '/', storage_path()), str_replace('\\', '/', $directory));
        $this->assertMatchesRegularExpression('/R4-01-[0-9a-f]{24}\.docx$/', ExportTempFiles::path('R4-01'));
    }

    public function test_outside_range_records_raise_a_non_blocking_warning_without_changing_rows(): void
    {
        $warning = '1 record in Purok 2 has a grupo outside the Purok 2a / Purok 2b grupo ranges and is not shown.';

        $response = $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => 2, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee($warning)
            ->assertDontSee('Outside Range Member');
        $this->assertCount(4, $response->viewData('reportRows'));

        $this->asSecretary()
            ->get(route('forms.weekly.r4-02', ['purok' => '2:3', 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee($warning)
            ->assertDontSee('Outside Range Member');

        $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => 1, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertDontSee('grupo outside the');
    }

    public function test_r402_groups_rows_per_segment_numbered_from_one_with_own_tokens(): void
    {
        $response = $this->asSecretary()
            ->get(route('forms.weekly.r4-02', ['purok' => 2, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('name="purok" value="2:3"', false)
            ->assertSee('name="purok" value="2:4"', false)
            ->assertSeeInOrder(['Purok 2a', 'Ana Segment', 'Carla Segment', 'Purok 2b', 'Ben Segment', 'Dan Segment']);
        $groups = $response->viewData('purokGroups');

        $this->assertSame(['2:3', '2:4'], array_column($groups, 'number'));
        $this->assertSame(['Purok 2a', 'Purok 2b'], array_column($groups, 'name'));
        $this->assertSame(['Ana Segment', 'Carla Segment'], array_column($groups[0]['rows'], 'name'));
        $this->assertSame(['Ben Segment', 'Dan Segment'], array_column($groups[1]['rows'], 'name'));

        $allGroups = $this->asSecretary()
            ->get(route('forms.weekly.r4-02', ['purok' => 'all', 'kalagayan' => 'MS']))
            ->assertOk()
            ->viewData('purokGroups');
        $this->assertSame(['1', '2:3', '2:4'], array_column($allGroups, 'number'));

        $filtered = $this->asSecretary()
            ->get(route('forms.weekly.r4-02', ['purok' => '2:4', 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertDontSee('Ana Segment')
            ->viewData('purokGroups');
        $this->assertSame(['2:4'], array_column($filtered, 'number'));
    }

    public function test_store_r402_maps_row_numbers_inside_the_posted_segment(): void
    {
        R402Hakbang::query()->create(['status' => 'MS', 'code' => '1-7', 'description' => 'Dalaw']);

        $this->asSecretary()
            ->post(route('forms.weekly.r4-02.store'), [
                'purok' => '2:4',
                'kalagayan' => 'MS',
                'selection' => '2',
                'hakbang_code' => '1-7',
            ])
            ->assertSessionHasNoErrors();
        $form = R402GeneratedForm::query()->with('formRecords')->sole();

        $this->assertSame(4, $form->purok_id);
        $this->assertSame(2, (int) $form->purok_number);
        $this->assertSame(['Dan Segment'], $form->formRecords->pluck('full_name')->all());
        $this->assertSame([2], $form->formRecords->pluck('source_row_number')->map(fn ($n): int => (int) $n)->all());

        $this->asSecretary()
            ->post(route('forms.weekly.r4-02.store'), [
                'purok' => '2:3',
                'kalagayan' => 'MS',
                'selection' => '1-2',
                'hakbang_code' => '1-7',
            ])
            ->assertSessionHasNoErrors();
        $formA = R402GeneratedForm::query()->with('formRecords')->latest('id')->firstOrFail();
        $this->assertSame(3, $formA->purok_id);
        $this->assertSame(['Ana Segment', 'Carla Segment'], $formA->formRecords->pluck('full_name')->all());

        // Selecting past the end of the segment fails even though the merged Purok 2 list has four rows.
        $this->asSecretary()
            ->post(route('forms.weekly.r4-02.store'), [
                'purok' => '2:3',
                'kalagayan' => 'MS',
                'selection' => '3',
                'hakbang_code' => '1-7',
            ])
            ->assertSessionHasErrors('selection');

        // A single-segment purok still accepts its plain number and records its segment id.
        $this->asSecretary()
            ->post(route('forms.weekly.r4-02.store'), [
                'purok' => 1,
                'kalagayan' => 'MS',
                'selection' => '1',
                'hakbang_code' => '1-7',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, R402GeneratedForm::query()->latest('id')->firstOrFail()->purok_id);
        $this->assertDatabaseCount('r4_02_generated_forms', 3);
    }

    public function test_store_r402_rejects_ambiguous_or_mismatched_purok_tokens(): void
    {
        R402Hakbang::query()->create(['status' => 'MS', 'code' => '1-7', 'description' => 'Dalaw']);

        foreach (['2', 'all', '1,2:3', '1:3', '2:1', '2:999', 'x'] as $token) {
            $this->asSecretary()
                ->post(route('forms.weekly.r4-02.store'), [
                    'purok' => $token,
                    'kalagayan' => 'MS',
                    'selection' => '1',
                    'hakbang_code' => '1-7',
                ])
                ->assertSessionHasErrors('purok');
        }
        $this->assertDatabaseCount('r4_02_generated_forms', 0);
    }

    public function test_print_and_r403_use_the_saved_segment_and_keep_legacy_forms(): void
    {
        $formB = R402GeneratedForm::query()->create(['purok_number' => 2, 'purok_id' => 4, 'kalagayan' => 'MS', 'created_by' => 'Tester']);
        $formB->formRecords()->create(['source_row_number' => 1, 'hakbang_code' => '1-7', 'full_name' => 'Ben Segment']);
        $formA = R402GeneratedForm::query()->create(['purok_number' => 2, 'purok_id' => 3, 'kalagayan' => 'MS', 'created_by' => 'Tester']);
        $formA->formRecords()->create(['source_row_number' => 1, 'hakbang_code' => '1-7', 'full_name' => 'Ana Segment']);
        // Forms saved before purok_id existed (or whose purok configuration was removed) still group by number.
        $legacy = R402GeneratedForm::query()->create(['purok_number' => 2, 'kalagayan' => 'MS', 'created_by' => 'Tester']);
        $legacy->formRecords()->create(['source_row_number' => 1, 'hakbang_code' => '1-7', 'full_name' => 'Legacy Member']);
        $orphan = R402GeneratedForm::query()->create(['purok_number' => 7, 'kalagayan' => 'MS', 'created_by' => 'Tester']);
        $orphan->formRecords()->create(['source_row_number' => 1, 'hakbang_code' => '1-7', 'full_name' => 'Orphan Member']);

        $this->asSecretary()
            ->get(route('forms.weekly.r4-02.print', $formB))
            ->assertOk()
            ->assertSee('Purok 2b')
            ->assertDontSee('Purok 2a')
            ->assertSee(e(route('forms.weekly.r4-02', ['purok' => '2:4', 'kalagayan' => 'MS'])), false);
        $this->asSecretary()
            ->get(route('forms.weekly.r4-02.print', $legacy))
            ->assertOk()
            ->assertSee('Legacy Member')
            ->assertSee(e(route('forms.weekly.r4-02', ['purok' => 2, 'kalagayan' => 'MS'])), false);
        $this->asSecretary()
            ->get(route('forms.weekly.r4-02.print', $orphan))
            ->assertOk()
            ->assertSee('Purok 7');

        $groups = $this->asSecretary()
            ->get(route('forms.weekly.r4-03'))
            ->assertOk()
            ->viewData('groups');
        $this->assertSame(['Purok 2a', 'Purok 2b', 'Purok 2a / Purok 2b', 'Purok 7'], $groups->pluck('name')->all());
        $this->assertSame([3, 4, null, null], $groups->pluck('purok_id')->all());
        $this->assertSame([$formA->id], $groups[0]['forms']->pluck('id')->all());
        $this->assertSame([$formB->id], $groups[1]['forms']->pluck('id')->all());

        $this->asSecretary()
            ->get(route('forms.weekly.r4-03.print', ['kalagayan' => 'MS']))
            ->assertOk()
            ->assertSeeInOrder(['Purok 2a', 'Ana Segment', 'Purok 2b', 'Ben Segment', 'Purok 2a / Purok 2b', 'Legacy Member', 'Purok 7', 'Orphan Member']);
    }

    public function test_purok_secretary_mode_falls_back_to_global_kalihim_for_segments_without_secretary(): void
    {
        $purokSecretary = User::query()->create([
            'gmail' => 'purok-2a-secretary@example.test',
            'password' => 'password',
            'first_name' => 'Twoa',
            'last_name' => 'Secretary',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
        DB::table('puroks')->where('id', 3)->update(['secretary' => $purokSecretary->id]);
        GlobalVariable::query()->update(['r401_use_purok_secretary' => true]);

        $pages = $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => 2, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertSee('TWOA SECRETARY')
            ->assertSee('GLOBAL MS KALIHIM')
            ->viewData('reportPages');
        $this->assertSame(['Twoa Secretary', 'Global MS Kalihim'], array_column($pages, 'kalihim_name'));

        $this->assertSame(
            ['Global UWP Kalihim'],
            array_column(app(R401ReportBuilder::class)->withKalihimNames(
                [['secretary_name' => '   ']],
                GlobalVariable::query()->first(),
                'UWP'
            ), 'kalihim_name')
        );

        $download = $this->asSecretary()
            ->get(route('forms.monthly.r4-01.download', ['purok' => '2:4', 'kalagayan' => 'MS']))
            ->assertOk();
        $path = $download->baseResponse->getFile()->getPathname();
        try {
            $archive = new \ZipArchive;
            $this->assertTrue($archive->open($path));
            $document = (string) $archive->getFromName('word/document.xml');
            $archive->close();
            $this->assertStringContainsString('GLOBAL MS KALIHIM', mb_strtoupper($document));
        } finally {
            @unlink($path);
        }

        // With the setting off, every page uses the global Kalihim exactly as before.
        GlobalVariable::query()->update(['r401_use_purok_secretary' => false]);
        $pages = $this->asSecretary()
            ->get(route('forms.monthly.r4-01', ['purok' => 2, 'kalagayan' => 'MS']))
            ->assertOk()
            ->assertDontSee('TWOA SECRETARY')
            ->viewData('reportPages');
        $this->assertSame(['Global MS Kalihim', 'Global MS Kalihim'], array_column($pages, 'kalihim_name'));
    }

    public function test_monthly_snapshot_export_uses_segment_secretary_fallback_and_temp_directory(): void
    {
        $purokSecretary = User::query()->create([
            'gmail' => 'purok-2a-snapshot@example.test',
            'password' => 'password',
            'first_name' => 'Snapa',
            'last_name' => 'Secretary',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
        DB::table('puroks')->where('id', 3)->update(['secretary' => $purokSecretary->id]);
        GlobalVariable::query()->update(['r401_use_purok_secretary' => true]);
        $session = MonthlyMovementSession::query()->forceCreate([
            'active_key' => 'active',
            'status' => 'active',
            'period_month' => '2026-10-01',
            'started_by' => 'Secretary Example',
            'started_at' => '2026-10-01 09:00:00',
        ]);
        $storageDirectory = storage_path('app/monthly-forms');
        $before = glob($storageDirectory.'/*.docx') ?: [];
        $tempBefore = glob(ExportTempFiles::directory().'/*.docx') ?: [];

        $export = app(MonthlySessionSnapshotExporter::class)->generate($session);

        $this->assertNotSame('', $export['contents']);
        $this->assertStringContainsString('SNAPA SECRETARY', $export['preview']);
        $this->assertStringContainsString('GLOBAL MS KALIHIM', $export['preview']);
        $this->assertSame($before, glob($storageDirectory.'/*.docx') ?: []);
        $this->assertSame($tempBefore, glob(ExportTempFiles::directory().'/*.docx') ?: [], 'Snapshot exporter must clean up its temp files.');
    }

    public function test_builder_accepts_legacy_strings_and_selection_objects(): void
    {
        $builder = app(R401ReportBuilder::class);

        $legacy = $builder->build('2', 'MS');
        $this->assertSame(['2:3', '2:4'], array_column($legacy['segments'], 'token'));
        $this->assertSame([['purok_number' => 2, 'count' => 1, 'segment_names' => ['Purok 2a', 'Purok 2b']]], $legacy['outside_range']);

        $all = $builder->build('all', 'MS');
        $this->assertSame(['1', '2:3', '2:4'], array_column($all['segments'], 'token'));
        $this->assertCount(5, $all['rows']);

        $segment = $builder->build(PurokSelection::fromRequestValue('2:3'), 'MS');
        $this->assertSame(['Ana Segment', 'Carla Segment'], array_column($segment['rows'], 'name'));

        $this->expectException(ValidationException::class);
        PurokSelection::fromRequestValue('1:4');
    }

    private function asSecretary(): static
    {
        return $this->withSession(['login_user' => ['id' => $this->secretary->id]]);
    }
}
