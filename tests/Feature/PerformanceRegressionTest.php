<?php

namespace Tests\Feature;

use App\Models\GlobalVariable;
use App\Models\Purok;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the speed fixes on the dashboard, Smart Helper, Filter & BNH Helper, monthly generators,
 * Tarheta status and task list: the results must stay exactly what the slower code produced,
 * and the repeated queries must not come back.
 */
class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_smart_helper_finds_exactly_the_pairs_a_full_pairwise_scan_finds(): void
    {
        mt_srand(20261026);
        $firstNames = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Mark', 'Ella', 'Niño', 'Peña', 'Lito', 'Jo'];
        $lastNames = ['Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Lausing', 'Ibañez', 'Cruz', 'Lim'];
        $names = [];
        for ($i = 0; $i < 90; $i++) {
            $name = $firstNames[mt_rand(0, count($firstNames) - 1)].' '.$lastNames[mt_rand(0, count($lastNames) - 1)];
            if (mt_rand(0, 3) === 0) {
                // A one-letter typo, a dropped letter or an extra letter: the near matches the helper looks for.
                $position = mt_rand(1, mb_strlen($name) - 1);
                $name = match (mt_rand(0, 2)) {
                    0 => mb_substr($name, 0, $position).'x'.mb_substr($name, $position + 1),
                    1 => mb_substr($name, 0, $position).mb_substr($name, $position + 1),
                    default => mb_substr($name, 0, $position).'a'.mb_substr($name, $position),
                };
            }
            $name .= ['', '', '', ' Jr.', ' Sr', ' JR'][mt_rand(0, 5)];
            $names[] = $name;
        }
        $names[] = 'Ñora Peñalosa';
        $names[] = 'Nora Penalosa';
        // One letter apart, but two bytes apart: must be compared letter by letter (92%).
        $names[] = 'Maria Ibañez Santos';
        $names[] = 'Maria Ibanez Santos';
        $names[] = '123';
        $names[] = '';
        foreach ($names as $index => $name) {
            Record::query()->create([
                'full_name' => $name,
                'registry' => $index % 17 === 0 ? 'REG-DUP' : ($index % 11 === 0 ? 'RFID' : 'REG-'.$index),
                'status' => ['MS', 'UWP', 'MK', 'INACTIVE'][$index % 4],
                'kapisanan' => ['B', 'K', 'binhi', null][$index % 4],
                'purok' => (string) ($index % 5 + 1),
                'grupo' => (string) ($index % 9 + 1),
            ]);
        }

        $response = $this->withSession(['login_user' => ['id' => 1]])
            ->getJson(route('records.smart-helper'))
            ->assertOk();

        $expected = $this->referenceSmartHelper();
        $this->assertGreaterThanOrEqual(5, collect($expected)->where('type', 'similar_name')->count());
        $this->assertContains(['Maria Ibanez Santos', 'Maria Ibañez Santos'], collect($expected)->where('type', 'similar_name')->map(fn (array $finding): array => array_column($finding['records'], 'name'))->all());
        $this->assertSame($expected, $response->json('data'));
        $this->assertSame(count($names), $response->json('records_scanned'));
    }

    public function test_dashboard_counts_with_split_puroks_and_unusual_statuses(): void
    {
        Purok::query()->create(['purok_number' => 1, 'purok_name' => 'Purok 1', 'grupo_start' => 1, 'grupo_end' => 24, 'full_purok' => true]);
        Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Purok 2a', 'grupo_start' => 1, 'grupo_end' => 6]);
        Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Purok 2b', 'grupo_start' => 7, 'grupo_end' => 13]);
        $rows = [
            ['One', '1', '3', 'ms', 'B', 'K', 'REG-1'],
            ['Two', 'Purok 2', '4', ' MS ', 'L', 'binhi', 'RFID'],
            ['Three', '2', '9', 'UWP', 'B', 'B', null],
            ['Four', '2', 'x', 'MK', 'L', 'kadiwa', 'REG-4'],
            ['Five', '02', '12', 'INACTIVE', 'B', 'K', 'REG-5'],
            ['Six', '', '', 'UWP', '', null, ''],
            ['Seven', '2', '20', 'MS', 'B', 'buklod', 'REG-7'],
            ['Eight', '3', '1', '', 'B', 'K', 'REG-8'],
            ['Nine', '1', '2', 'OTHER', 'L', 'K', 'REG-9'],
        ];
        foreach ($rows as [$name, $purok, $grupo, $status, $gender, $kapisanan, $registry]) {
            Record::query()->create([
                'full_name' => $name, 'purok' => $purok, 'grupo' => $grupo, 'status' => $status,
                'gender' => $gender, 'kapisanan' => $kapisanan, 'registry' => $registry,
            ]);
        }

        $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                $this->assertSame(9, $metrics['recordCount']);
                $this->assertSame(7, $metrics['activeCount']);
                $this->assertSame(1, $metrics['inactiveCount']);
                $this->assertSame([
                    ['code' => 'MS', 'label' => 'MS', 'count' => 3],
                    ['code' => 'UWP', 'label' => 'UWP', 'count' => 2],
                    ['code' => 'MK', 'label' => 'MK', 'count' => 1],
                    ['code' => 'INACTIVE', 'label' => 'INACTIVE', 'count' => 1],
                    ['code' => 'OTHER', 'label' => 'OTHER', 'count' => 1],
                    ['code' => 'UNSPECIFIED', 'label' => 'Unspecified', 'count' => 1],
                ], $metrics['statuses']);
                $this->assertSame(['MS', 'UWP', 'MK', 'OTHER'], $metrics['activeStatuses']);

                $puroks = collect($metrics['puroks'])->keyBy('name');
                $this->assertSame(['MS' => 1, 'UWP' => 0, 'MK' => 0, 'OTHER' => 1], $puroks['Purok 1']['counts']);
                $this->assertSame(2, $puroks['Purok 1']['recordTotal']);
                // "Purok 2" grupo 4 is in 2a; grupo 9 in 2b; grupo "x" and 20 fit neither segment.
                $this->assertSame(['MS' => 1, 'UWP' => 0, 'MK' => 0, 'OTHER' => 0], $puroks['Purok 2a']['counts']);
                $this->assertSame(['MS' => 0, 'UWP' => 1, 'MK' => 0, 'OTHER' => 0], $puroks['Purok 2b']['counts']);

                $this->assertSame(['MS' => 2, 'UWP' => 1, 'MK' => 0, 'OTHER' => 0], $metrics['gender']['female']);
                $this->assertSame(['MS' => 1, 'UWP' => 0, 'MK' => 1, 'OTHER' => 1], $metrics['gender']['male']);
                $this->assertSame(['Two', 'Four', 'Nine'], array_column($metrics['genderRecords']['male'], 'name'));
                $this->assertSame(['One', 'Three', 'Seven'], array_column($metrics['genderRecords']['female'], 'name'));
                $this->assertSame(['Binhi' => 1, 'Kadiwa' => 1, 'Buklod' => 1], $metrics['groupCounts']['MS']);
                $this->assertSame(['Binhi' => 0, 'Kadiwa' => 0, 'Buklod' => 1], $metrics['groupCounts']['UWP']);
                $this->assertSame(['Binhi' => 0, 'Kadiwa' => 1, 'Buklod' => 0], $metrics['groupCounts']['MK']);

                $this->assertSame(['1', '2', 'Purok 2', 'Unassigned'], array_column($metrics['registryPuroks'], 'id'));
                $registry = collect($metrics['registryReport'])->firstWhere('label', 'Registry');
                $this->assertSame([0, 1, 1, 1], $registry['missing']);
                $this->assertSame(['Three'], array_column($registry['details'][1], 'name'));
                $this->assertSame([7, 13], [$registry['details'][1][0]['grupo_min'], $registry['details'][1][0]['grupo_max']]);
                $this->assertSame([1, 6], [$registry['details'][2][0]['grupo_min'], $registry['details'][2][0]['grupo_max']]);
                $this->assertSame([1, 24], [$registry['details'][3][0]['grupo_min'], $registry['details'][3][0]['grupo_max']]);
                $this->assertFalse($registry['details'][3][0]['can_edit_grupo']);
                $grupo = collect($metrics['registryReport'])->firstWhere('label', 'Grupo');
                $this->assertSame([0, 0, 0, 1], $grupo['missing']);
                // "Four" (grupo "x") falls back to the first Purok 2 segment.
                $birthday = collect($metrics['registryReport'])->firstWhere('label', 'Birthday');
                $this->assertSame([1, 3, 1, 1], $birthday['missing']);
                $this->assertSame(['Three', 'Four', 'Seven'], array_column($birthday['details'][1], 'name'));
                $this->assertSame([1, 6], [$birthday['details'][1][1]['grupo_min'], $birthday['details'][1][1]['grupo_max']]);

                return true;
            });
    }

    public function test_filter_bnh_helper_reads_the_purok_settings_once_and_counts_split_segments(): void
    {
        $user = User::query()->create([
            'gmail' => 'perf-helper@example.test', 'password' => 'password',
            'tungkulin' => 'Head Secretary', 'is_verified' => true,
        ]);
        $full = Purok::query()->create(['purok_number' => 1, 'purok_name' => 'Purok 1', 'grupo_start' => 1, 'grupo_end' => 3, 'full_purok' => true]);
        $twoA = Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Purok 2a', 'grupo_start' => 1, 'grupo_end' => 2]);
        $twoB = Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Purok 2b', 'grupo_start' => 3, 'grupo_end' => 4]);
        foreach ([
            ['A', '1', '2', 'L', 'MS', true, true],
            ['B', 'Purok 1', '2', 'B', 'UWP', true, false],
            ['C', '2', '1', 'B', 'MS', true, true],
            ['D', '2', '4', 'M', 'MK', false, false],
            ['E', '2', '4', 'babae', 'MS', true, true],
            ['F', '2', '9', 'L', 'MS', true, true],
            ['G', '3', '1', 'L', 'MS', true, true],
            ['H', '1', '1', 'L', 'INACTIVE', true, true],
        ] as [$name, $purok, $grupo, $gender, $status, $valid1, $valid2]) {
            Record::query()->create([
                'full_name' => $name, 'purok' => $purok, 'grupo' => $grupo, 'gender' => $gender,
                'status' => $status, 'is_valid1' => $valid1, 'is_valid2' => $valid2,
            ]);
        }

        $purokQueries = $this->countQueries('from "puroks"', function () use ($user, $full): void {
            $rows = collect($this->withSession(['login_user' => ['id' => $user->id]])
                ->getJson(route('filter-bnh-helper.matrix'))
                ->assertOk()
                ->assertJsonPath('totals', ['male' => 2, 'female' => 3, 'total' => 5])
                ->json('rows'))->keyBy('id');
            $this->assertSame(['male' => 1, 'female' => 1, 'total' => 2], array_intersect_key($rows[$full->id.'-2'], ['male' => 0, 'female' => 0, 'total' => 0]));
        });
        $this->assertSame(1, $purokQueries);

        $rows = collect($this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('filter-bnh-helper.matrix', ['purok_selection' => 'selected', 'purok_ids' => [$twoB->id, $twoA->id]]))
            ->assertOk()
            ->json('rows'));
        $this->assertSame(['group', 'group', 'purok_total', 'group', 'group', 'purok_total', 'grand_total'], $rows->pluck('type')->all());
        $this->assertSame(['2a-1', '2a-2', 'Total 2a', '2b-3', '2b-4', 'Total 2b', 'Overall Total'], $rows->pluck('label')->all());
        $this->assertSame([1, 0, 1, 0, 2, 2, 3], $rows->pluck('total')->all());

        $counts = $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('filter-bnh-helper.qualified-counts', ['purok_id' => (string) $twoB->id]))
            ->assertOk();
        $this->assertSame(['male' => 0, 'female' => 1, 'total' => 1], $counts->json('totals'));
        $this->assertSame(['male' => 1, 'female' => 0, 'total' => 1], $counts->json('minuses_totals'));

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('filter-bnh-helper.bnh-records', ['q' => 'e']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('records.0.location', '2b-4');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('filter-bnh-helper.matrix', ['purok_id' => 'abc']))
            ->assertStatus(422);
    }

    public function test_monthly_generators_read_the_settings_row_once(): void
    {
        GlobalVariable::query()->create(['locale' => 'Sample Locale', 'lcode' => 'L-1']);

        $settingsQueries = $this->countQueries('from "global_variables"', function (): void {
            $this->withSession(['login_user' => ['id' => 1]])
                ->get(route('forms.monthly.generators'))
                ->assertOk()
                ->assertViewHas('locale', 'Sample Locale')
                ->assertViewHas('lcode', 'L-1');
        });

        $this->assertSame(1, $settingsQueries);
    }

    public function test_tarheta_rows_are_written_without_indentation(): void
    {
        Record::query()->create(['full_name' => 'Needs Update', 'status' => 'MS', 'tarheta_status' => 'UWP']);
        Record::query()->create(['full_name' => 'Already Matching', 'status' => 'MS', 'tarheta_status' => 'MS', 'filing_status' => 'MS']);

        $html = $this->withSession(['login_user' => ['id' => 1]])
            ->get(route('tarheta-status'))
            ->assertOk()
            ->getContent();

        $start = strpos($html, '<tbody id="tarhetaRows">');
        $tbody = rtrim(substr($html, $start, strpos($html, '</tbody>', $start) - $start));
        $this->assertSame(2, substr_count($tbody, '<tr class="tk-row"'));
        $this->assertDoesNotMatchRegularExpression('/\n[ \t]+</', $tbody);
        $this->assertStringContainsString('<option value="MS" >MS</option><option value="UWP" selected>UWP</option>', $tbody);
        $this->assertStringContainsString('<option value="MS" selected>MS</option>', $tbody);
    }

    public function test_task_list_loads_every_related_person_in_one_query_and_matches_the_single_task_payload(): void
    {
        $head = $this->makeUser('Head Secretary', 'Head');
        $ana = $this->makeUser('Secretary', 'Ana');
        $ben = $this->makeUser('Secretary', 'Ben');
        $taskId = $this->withSession(['login_user' => ['id' => $head->id]])->postJson(route('assigned-tasks.store'), [
            'title' => 'Prepare the R4-02',
            'members' => [
                ['id' => $ana->id, 'can_comment' => true, 'can_complete' => true],
                ['id' => $ben->id, 'can_comment' => true, 'can_complete' => false],
            ],
        ])->assertCreated()->json('id');
        $this->withSession(['login_user' => ['id' => $ben->id]])->postJson(route('assigned-tasks.comment', $taskId), ['body' => 'Started'])->assertCreated();
        $this->withSession(['login_user' => ['id' => $head->id]])->postJson(route('assigned-tasks.react', $taskId), ['type' => 'like'])->assertOk();
        $single = $this->withSession(['login_user' => ['id' => $ana->id]])->postJson(route('assigned-tasks.complete', $taskId))->assertOk()->json();

        $listed = null;
        $userQueries = $this->countQueries('from "login_user" where "login_user"."id" in', function () use ($ana, &$listed): void {
            $listed = $this->withSession(['login_user' => ['id' => $ana->id]])
                ->getJson(route('assigned-tasks.index'))
                ->assertOk()
                ->json('tasks.0');
        });

        $this->assertSame(1, $userQueries);
        unset($listed['is_new'], $listed['mentions_me']);
        $this->assertSame($single, $listed);
        $this->assertSame('Ana Tester', $listed['done_by']['name']);
        $this->assertSame('Head Tester', $listed['author']['name']);
    }

    /**
     * Counts the queries whose SQL contains $needle while $callback runs.
     */
    private function countQueries(string $needle, callable $callback): int
    {
        $count = 0;
        DB::listen(function ($query) use (&$count, $needle): void {
            if (str_contains($query->sql, $needle)) {
                $count++;
            }
        });
        $callback();

        return $count;
    }

    private function makeUser(string $role, string $firstName): User
    {
        return User::query()->create([
            'gmail' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'first_name' => $firstName,
            'last_name' => 'Tester',
            'tungkulin' => $role,
            'is_verified' => true,
        ]);
    }

    /**
     * The Smart Helper as it was written before the speed fix: every pair of records is compared
     * with a letter-by-letter edit distance.
     *
     * @return list<array<string, mixed>>
     */
    private function referenceSmartHelper(): array
    {
        $records = Record::query()->select(['id', 'full_name', 'registry', 'status', 'kapisanan', 'purok', 'grupo'])
            ->orderBy('full_name')->orderBy('registry')->get();
        $present = fn (array $records): array => array_map(fn (Record $record): array => [
            'id' => $record->id,
            'name' => $record->full_name ?? '',
            'registry_number' => $record->registry ?? '',
            'status' => $record->status ?? '',
            'kapisanan' => match (mb_strtolower(trim($record->kapisanan ?? ''))) {
                'b' => 'Buklod', 'k' => 'Kadiwa', 'binhi' => 'binhi', default => $record->kapisanan ?? '',
            },
            'purok' => $record->purok ?? '',
            'grupo' => $record->grupo ?? '',
        ], $records);
        $nameParts = function (string $name): array {
            preg_match('/\s+(jr|sr)\.?\s*$/iu', trim($name), $matches);
            $suffix = mb_strtolower($matches[1] ?? '');
            $name = preg_replace('/\s+(?:jr|sr)\.?\s*$/iu', '', trim($name)) ?? $name;

            return ['letters' => preg_replace('/[^\p{L}]/u', '', mb_strtolower($name)) ?? '', 'suffix' => $suffix];
        };
        $similarity = function (string $left, string $right): int {
            $a = mb_str_split($left);
            $b = mb_str_split($right);
            $longest = max(count($a), count($b));
            $previous = range(0, count($b));
            for ($row = 1; $row <= count($a); $row++) {
                $current = [$row];
                for ($column = 1; $column <= count($b); $column++) {
                    $current[$column] = min($current[$column - 1] + 1, $previous[$column] + 1, $previous[$column - 1] + ($a[$row - 1] === $b[$column - 1] ? 0 : 1));
                }
                $previous = $current;
            }

            return (int) round((1 - $previous[count($b)] / $longest) * 100);
        };

        $findings = [];
        $registryGroups = [];
        $nameGroups = [];
        $named = [];
        foreach ($records as $record) {
            $registry = (string) ($record->registry ?? '');
            if (trim($registry) !== '' && mb_strtoupper(trim($registry)) !== 'RFID') {
                $registryGroups['registry:'.$registry][] = $record;
            }
            $name = $nameParts($record->full_name ?? '');
            if ($name['letters'] !== '') {
                $nameGroups['name:'.$name['letters'].':'.$name['suffix']][] = $record;
                $named[] = ['record' => $record, ...$name];
            }
        }
        foreach ($registryGroups as $group) {
            if (count($group) > 1) {
                $findings[] = ['type' => 'duplicate_registry', 'field' => 'registry', 'match_value' => $group[0]->registry, 'records' => $present($group)];
            }
        }
        foreach ($nameGroups as $group) {
            if (count($group) > 1) {
                $findings[] = ['type' => 'duplicate_name', 'field' => 'full_name', 'match_value' => $group[0]->full_name, 'similarity' => 100, 'records' => $present($group)];
            }
        }
        for ($left = 0; $left < count($named); $left++) {
            for ($right = $left + 1; $right < count($named); $right++) {
                [$l, $r] = [$named[$left], $named[$right]];
                if (($l['suffix'] !== '' && $r['suffix'] !== '' && $l['suffix'] !== $r['suffix']) || $l['letters'] === $r['letters']) {
                    continue;
                }
                $score = $similarity($l['letters'], $r['letters']);
                if ($score >= 90) {
                    $findings[] = ['type' => 'similar_name', 'field' => 'full_name', 'similarity' => $score, 'records' => $present([$l['record'], $r['record']])];
                }
            }
        }

        return $findings;
    }
}
