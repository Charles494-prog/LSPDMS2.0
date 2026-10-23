<?php

namespace Tests\Feature;

use App\Models\GlobalVariable;
use App\Models\Purok;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LspBotRecordQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-24 10:00', 'Asia/Manila'));

        Purok::query()->create(['purok_number' => 1, 'purok_name' => 'Purok 1', 'grupo_start' => 1, 'grupo_end' => 24, 'full_purok' => true]);
        Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Purok 2a', 'grupo_start' => 1, 'grupo_end' => 6, 'full_purok' => false]);
        Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Purok 2b', 'grupo_start' => 7, 'grupo_end' => 13, 'full_purok' => false]);

        $this->record('ANA', 'MS', '1', '3', ['kapisanan' => 'B', 'gender' => 'Male', 'is_married' => true, 'date_of_baptism' => '2010-05-01', 'date_of_birth' => '1980-01-01']);
        $this->record('BEN', 'MS', '1', '3', ['kapisanan' => 'K', 'gender' => 'Female', 'date_of_baptism' => '2020-05-01', 'date_of_birth' => '2000-01-01']);
        $this->record('CARL', 'MS', '1', '5', ['kapisanan' => 'binhi', 'gender' => 'Male', 'date_of_baptism' => '2024-02-01', 'date_of_birth' => '2012-03-03']);
        $this->record('DINA', 'UWP', '1', '7', ['kapisanan' => 'K', 'gender' => 'Female', 'date_of_birth' => null]);
        $this->record('ELI', 'MS', '2', '2', ['kapisanan' => 'B', 'gender' => 'Male', 'date_of_birth' => '1950-01-01']);
        $this->record('FAYE', 'MS', '2', '9', ['kapisanan' => 'K', 'gender' => 'Female', 'registry' => 'RFID']);
        $this->record('GIL', 'MK', '2', '10', ['kapisanan' => 'B', 'gender' => 'Male', 'r2_10_endorsement' => 'ENDORSED']);
        $this->record('HAL', 'INACTIVE', '1', '3', ['kapisanan' => 'K', 'gender' => 'Male']);
    }

    public function test_counts_combine_status_purok_grupo_and_member_details(): void
    {
        $this->ask('How many MS are there?', 'There are 5 MS records.');
        $this->ask('how many ms in purok 1', 'There are 3 MS records in Purok 1.');
        $this->ask('Ilan ang MS sa purok 1?', 'There are 3 MS records in Purok 1.');
        $this->ask('how many ms in purok 1 grupo 3', 'There are 2 MS records in Purok 1, Grupo 3.');
        $this->ask('how many male buklod in purok 1 grupo 3', 'There is 1 active Buklod record in Purok 1, Grupo 3 (male).');
        $this->ask('how many married ms', 'There is 1 MS record (married).');
        $this->ask('how many female members', 'There are 3 active records (female).');
        $this->ask('ilang babae ang uwp', 'There is 1 UWP record (female).');
    }

    public function test_split_puroks_are_counted_separately(): void
    {
        $this->ask('how many ms in purok 2a', 'There is 1 MS record in Purok 2A.');
        $this->ask('how many ms in purok 2b', 'There is 1 MS record in Purok 2B.');
        $this->ask('how many ms in purok 2', 'There are 2 MS records in Purok 2.');
    }

    public function test_inactive_and_all_records_and_multiple_statuses(): void
    {
        $this->ask('how many inactive records', 'There is 1 INACTIVE record.');
        $this->ask('how many ms and uwp in purok 1', 'There are 4 MS and UWP records in Purok 1.');
        $this->ask('how many records including inactive', 'There are 8 records.');
    }

    public function test_dates_ages_and_missing_information(): void
    {
        $this->ask('how many ms baptized before 2015', 'There is 1 MS record (baptized before 2015).');
        $this->ask('how many members baptized since 2020', 'There are 2 active records (baptized since 2020).');
        $this->ask('how many ms baptized in 2024', 'There is 1 MS record (baptized in 2024).');
        $this->ask('how many members under 18', 'There is 1 active record (under 18).');
        $this->ask('how many seniors', 'There is 1 active record (60 or older).');
        $this->ask('how many uwp without birthday', 'There is 1 UWP record (no birth date).');
        $this->ask('how many members with rfid', 'There is 1 active record (with RFID).');
    }

    public function test_breakdowns_by_purok_kapisanan_and_gender(): void
    {
        $reply = $this->reply('MS per purok');
        $this->assertStringContainsString('by Purok (total 5)', $reply);
        $this->assertStringContainsString('• Purok 1: 3', $reply);
        $this->assertStringContainsString('• Purok 2a: 1', $reply);
        $this->assertStringContainsString('• Purok 2b: 1', $reply);

        $reply = $this->reply('how many active records by kapisanan in purok 1');
        $this->assertStringContainsString('• Buklod: 1', $reply);
        $this->assertStringContainsString('• Kadiwa: 2', $reply);
        $this->assertStringContainsString('• Binhi: 1', $reply);

        $this->assertStringContainsString('• Female: 2', $this->reply('ms by gender'));
    }

    public function test_lists_names_with_a_limit_and_keeps_mk_names_confidential(): void
    {
        $reply = $this->reply('list the ms in purok 1 grupo 3');
        $this->assertStringContainsString('I found 2 MS records in Purok 1, Grupo 3:', $reply);
        $this->assertStringContainsString('• ANA — MS, Purok 1-3', $reply);
        $this->assertStringNotContainsString('HAL', $reply);

        $this->ask('list the mk', 'MK member names are confidential. I can tell you how many there are, but listing names needs De-Listment access.');
        $this->ask('how many mk are there', 'There is 1 MK record.');
        $this->assertStringContainsString('GIL', $this->reply('list the mk', ['Delistment_allow' => true]));
    }

    public function test_follow_up_questions_keep_the_previous_conditions(): void
    {
        $this->ask('how many ms in purok 1', 'There are 3 MS records in Purok 1.');
        $this->ask('how about purok 2a?', 'There is 1 MS record in Purok 2A.');
        $this->ask('ilan naman sa purok 2b', 'There is 1 MS record in Purok 2B.');
    }

    public function test_r210_conditions_still_need_delistment_access(): void
    {
        $this->ask('how many endorsed mk in purok 2', 'Your account cannot view R2-10 endorsement information. Ask your Head Secretary to enable De-Listment access.');
        GlobalVariable::query()->create(['entry_years_requirement' => 1]);
        $this->assertSame(
            'There is 1 MK record in Purok 2 that is endorsed for R2-10.',
            $this->reply('how many endorsed mk in purok 2', ['Delistment_allow' => true])
        );
    }

    public function test_no_matches_and_help_questions_are_not_mistaken_for_counts(): void
    {
        $this->ask('how many uwp in purok 2', 'I found no UWP records in Purok 2.');

        $help = $this->reply('How many times can I submit my monthly?');
        $this->assertStringNotContainsString('records', $help);
    }

    public function test_sql_looking_text_is_only_ever_treated_as_words(): void
    {
        $this->ask("how many ms in purok 1'; DROP TABLE records; --", 'There are 3 MS records in Purok 1.');
        $this->assertSame(8, Record::query()->count());
    }

    public function test_date_and_time_questions_get_live_answers(): void
    {
        $this->ask('what time is it?', 'It is 10:00 AM in the Philippines right now.');
        $this->ask('anong petsa ngayon', 'Today is Saturday, October 24, 2026.');
    }

    private function ask(string $question, string $expected): void
    {
        $this->assertSame($expected, $this->reply($question));
    }

    private function reply(string $question, array $permissions = []): string
    {
        $user = User::query()->where('gmail', 'bot@example.test')->first()
            ?? User::query()->create([
                'gmail' => 'bot@example.test', 'password' => 'secret-password', 'tungkulin' => 'Secretary', 'is_verified' => true,
            ]);
        $user->forceFill(array_merge(['chatbot_records_allow' => true, 'Delistment_allow' => false], $permissions))->save();

        return (string) $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => $question])
            ->assertOk()
            ->json('reply');
    }

    private function record(string $name, string $status, string $purok, string $grupo, array $attributes = []): Record
    {
        return Record::query()->create($attributes + [
            'full_name' => $name,
            'status' => $status,
            'purok' => $purok,
            'grupo' => $grupo,
            'registry' => strtoupper(substr(md5($name), 0, 13)),
        ]);
    }
}
