<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Models\AssignedTask;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Purok;
use App\Models\Record;
use App\Models\SecretaryTodo;
use App\Models\User;
use App\Services\LspBotLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LSPBot's language layer (normalization, typos, number words, negation, multi-part questions),
 * conditional record lookups built on it, live system answers and the new knowledge.
 */
class LspBotNlpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-24 10:00', 'Asia/Manila'));
        // These tests ask far more than the 30-per-minute limit a person gets.
        $this->withoutMiddleware([ThrottleRequestsPerRoute::class, ThrottleRequests::class]);

        Purok::query()->create(['purok_number' => 1, 'purok_name' => 'Purok 1', 'grupo_start' => 1, 'grupo_end' => 24, 'full_purok' => true]);
        Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Purok 2a', 'grupo_start' => 1, 'grupo_end' => 6, 'full_purok' => false]);
        Purok::query()->create(['purok_number' => 2, 'purok_name' => 'Purok 2b', 'grupo_start' => 7, 'grupo_end' => 13, 'full_purok' => false]);

        $this->record('ANA', 'MS', '1', '3', ['kapisanan' => 'B', 'gender' => 'Male', 'is_married' => true, 'date_of_birth' => '1980-01-01']);
        $this->record('BEN', 'MS', '1', '3', ['kapisanan' => 'K', 'gender' => 'Female', 'date_of_birth' => '2000-01-01', 'date_of_baptism' => '2020-05-01']);
        $this->record('CARL', 'MS', '1', '5', ['kapisanan' => 'binhi', 'gender' => 'Male', 'date_of_birth' => '2012-03-03']);
        $this->record('DINA', 'UWP', '1', '7', ['kapisanan' => 'K', 'gender' => 'Female']);
        $this->record('ELI', 'MS', '2', '2', ['kapisanan' => 'B', 'gender' => 'Male', 'date_of_birth' => '1950-01-01']);
        $this->record('FAYE', 'MS', '2', '9', ['kapisanan' => 'K', 'gender' => 'Female', 'date_of_birth' => '1996-10-30']);
        $this->record('GIL', 'MK', '2', '10', ['kapisanan' => 'B', 'gender' => 'Male']);
        $this->record('HAL', 'INACTIVE', '1', '3', ['kapisanan' => 'K', 'gender' => 'Male']);

        $this->user = User::query()->create([
            'gmail' => 'nlp@example.test', 'password' => 'secret-password', 'first_name' => 'Nora', 'last_name' => 'Lopez',
            'tungkulin' => 'Secretary', 'is_verified' => true, 'chatbot_records_allow' => true,
        ]);
    }

    // ---------------------------------------------------------------- language layer

    public function test_normalization_handles_case_accents_shorthand_stretching_and_number_words(): void
    {
        $this->assertSame('hello lspbot!', LspBotLanguage::normalize('Héllooo LSPBot!!!'));
        $this->assertSame('how many ms in purok 1?', LspBotLanguage::normalize('How many MS in purok one?'));
        $this->assertSame('ilan ang ms sa purok 2', LspBotLanguage::normalize('Ilan ang MS sa ikalawang purok'));
        $this->assertSame('ms sa purok 3', LspBotLanguage::normalize('MS sa ika-3 purok'));
        $this->assertSame('top 5 oldest', LspBotLanguage::normalize('top five oldest'));
        $this->assertSame('members in purok 2', LspBotLanguage::normalize('members in purok dos'));
        $this->assertSame('what is the r2-10 status without birthday', LspBotLanguage::normalize("What's the R2-10 status w/o bday"));
        // A number word that is not a Purok, Grupo or amount stays a word.
        $this->assertSame('no one knows', LspBotLanguage::normalize('No one knows'));
    }

    public function test_typo_tolerance_uses_damerau_distance_and_leaves_real_words_alone(): void
    {
        $this->assertSame(1, LspBotLanguage::distance('pruok', 'purok'));
        $this->assertSame(1, LspBotLanguage::distance('deliberaton', 'deliberation'));
        $this->assertSame('members in purok', LspBotLanguage::fixTypos('membrs in pruok'));
        $this->assertSame('deliberation', LspBotLanguage::fixTypos('deliberaton'));
        $this->assertSame('mark as done made a backup', LspBotLanguage::fixTypos('mark as done made a backup'));
        $this->assertTrue(LspBotLanguage::similar('secretray', 'secretary'));
        $this->assertFalse(LspBotLanguage::similar('dates', 'males'));
    }

    public function test_multi_part_questions_are_split_but_single_questions_stay_whole(): void
    {
        $this->assertSame(['how many ms in purok 1?', 'how about uwp?'], LspBotLanguage::splitQuestions('how many ms in purok 1? how about uwp?'));
        $this->assertSame(['count the ms', 'list the uwp'], LspBotLanguage::splitQuestions('count the ms; list the uwp'));
        $this->assertSame(['how many ms in purok 1 and 2'], LspBotLanguage::splitQuestions('how many ms in purok 1 and 2'));
        $this->assertSame(["how many ms in purok 1'; DROP TABLE records; --"], LspBotLanguage::splitQuestions("how many ms in purok 1'; DROP TABLE records; --"));
        $this->assertTrue(LspBotLanguage::isTagalog('ilan ang ms sa purok 1'));
        $this->assertFalse(LspBotLanguage::isTagalog('how many ms in purok 1'));
    }

    // ---------------------------------------------------------------- conditional lookups

    public function test_typos_number_words_and_tagalog_reach_the_same_count(): void
    {
        foreach (['how many ms in purok 1', 'hw many ms in pruok one', 'Ilan ang MS sa unang purok?', 'ms purok 1', 'HOW MANY MS IN PUROK 1???'] as $question) {
            $this->assertSame('There are 3 MS records in Purok 1.', $this->reply($question), $question);
        }
        $this->assertSame('There are 2 MS records in Purok 2.', $this->reply('ilan ang ms sa ikalawang purok'));
    }

    public function test_several_puroks_or_grupos_show_the_total_and_each_part(): void
    {
        $this->assertSame("There are 5 MS records in Purok 1, 2.\n• Purok 1: 3\n• Purok 2: 2", $this->reply('how many ms in purok 1 and 2'));
        $this->assertSame("There is 1 active Buklod record in Purok 1, Grupo 3, 5.\n• Grupo 3: 1\n• Grupo 5: 0", $this->reply('how many buklod in purok 1 grupo 3 and 5'));
    }

    public function test_negation_excludes_puroks_statuses_and_kapisanan(): void
    {
        $this->assertSame('There are 2 MS records (not in Purok 1).', $this->reply('how many MS not in purok 1'));
        $this->assertSame('There are 2 MS records (not in Purok 1).', $this->reply('ilan ang ms maliban sa purok 1'));
        $this->assertSame('There are 2 UWP and MK records.', $this->reply('how many non-ms members'));
        $this->assertSame('There are 6 active records (not Binhi).', $this->reply('how many members except binhi'));
        $this->assertSame('There are 2 MS records (not in Purok 1, single).', $this->reply('how many single ms not in purok 1'));
    }

    public function test_age_phrases_in_english_and_tagalog(): void
    {
        $this->assertSame('There are 2 active records (aged 18–30).', $this->reply('how many members 18 to 30 years old'));
        $this->assertSame('There is 1 active record (over 60).', $this->reply('how many members above 60'));
        $this->assertSame('There are 4 active records (18 or older).', $this->reply('ilan ang members edad 18 pataas'));
        $this->assertSame('There is 1 active record (under 18).', $this->reply('how many minors'));
    }

    public function test_both_genders_become_a_breakdown_and_birthdays_list_names(): void
    {
        $reply = $this->reply('how many males and females');
        $this->assertStringContainsString('by gender (total 7)', $reply);
        $this->assertStringContainsString('• Female: 3', $reply);
        $this->assertStringContainsString('• Male: 4', $reply);

        $reply = $this->reply('birthdays this month');
        $this->assertStringContainsString('(birthday in October)', $reply);
        $this->assertStringContainsString('FAYE', $reply);
    }

    public function test_follow_ups_reuse_conditions_until_a_new_set_is_named(): void
    {
        $this->reply('how many ms in purok 1');
        $this->assertSame('There are 2 MS records in Purok 2.', $this->reply('how about purok 2?'));
        $this->assertSame('There is 1 MS record in Purok 2 (female).', $this->reply('and females?'));
        $this->assertStringContainsString('• FAYE — MS, Purok 2-9', $this->reply('list them'));
        $this->assertStringContainsString('FAYE', $this->reply('who is the oldest?'));

        // "oldest member" names its own set, so the Purok 2 / female conditions are dropped.
        $this->assertStringContainsString('ELI', $this->reply('who is the oldest member?'));
    }

    public function test_a_fresh_chat_forgets_the_previous_question(): void
    {
        $this->reply('how many ms in purok 1');
        $this->assertSame('There is 1 MS record in Purok 1 (female).', $this->reply('and females?'));

        $reply = $this->ask('and females?', ['fresh' => true])['reply'];
        $this->assertSame('There are 3 active records (female).', $reply);
    }

    public function test_two_questions_in_one_message_are_answered_in_order(): void
    {
        $this->assertSame(
            "“how many ms?”\nThere are 5 MS records.\n\n“how about uwp?”\nThere is 1 UWP record.",
            $this->reply('how many ms? how about uwp?')
        );
    }

    public function test_answers_carry_quick_reply_suggestions(): void
    {
        $this->assertSame(['List them', 'Per grupo', 'What percentage is that?'], $this->ask('how many ms in purok 1')['suggestions']);

        $unsure = $this->ask('something about the members');
        $this->assertStringContainsString('Did you mean one of these?', $unsure['reply']);
        $this->assertNotEmpty($unsure['suggestions']);
        $this->assertArrayHasKey('label', $unsure['suggestions'][0]);
        $this->assertArrayHasKey('message', $unsure['suggestions'][0]);

        $this->assertSame(['What can you do?', 'Give me examples', 'What is new?'], $this->ask('blorp zzz')['suggestions']);
    }

    // ---------------------------------------------------------------- permissions

    public function test_mk_names_and_endorsement_stay_hidden_without_delistment_access(): void
    {
        $reply = $this->reply('list the members in purok 2');
        $this->assertStringContainsString('ELI', $reply);
        $this->assertStringNotContainsString('GIL', $reply);
        $this->assertStringContainsString('MK names are confidential', $reply);

        $this->assertStringContainsString('couldn\'t find a record', $this->reply('find gil'));
        $this->user->forceFill(['Delistment_allow' => true])->save();
        $this->assertStringContainsString('GIL — MK', $this->reply('find gil'));
        $this->assertStringContainsString('GIL', $this->reply('list the members in purok 2'));
    }

    public function test_tarheta_conditions_and_record_lookups_need_their_permissions(): void
    {
        $this->assertStringContainsString('Tarheta Status Checker access', $this->reply('how many ms without tarheta'));

        $this->user->forceFill(['chatbot_records_allow' => false])->save();
        $this->assertStringContainsString('do not have permission to look up member records', $this->reply('ms purok 1'));
        $this->assertStringContainsString('do not have permission to look up member records', $this->reply('how many ms not in purok 1'));
    }

    // ---------------------------------------------------------------- live system answers

    public function test_unread_messages_and_the_personal_summary(): void
    {
        $other = User::query()->create(['gmail' => 'other@example.test', 'password' => 'secret-password', 'first_name' => 'Oli', 'last_name' => 'Reyes', 'tungkulin' => 'Secretary', 'is_verified' => true]);
        $conversation = Conversation::query()->create(['user_one_id' => min($this->user->id, $other->id), 'user_two_id' => max($this->user->id, $other->id)]);
        foreach (['Hi', 'Are you there?'] as $body) {
            Message::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id, 'body' => $body, 'expires_at' => now()->addYear()]);
        }
        Message::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $this->user->id, 'body' => 'Mine', 'expires_at' => now()->addYear()]);

        $unread = $this->ask('do I have unread messages?');
        $this->assertSame('You have 2 unread messages. Open the chat button at the bottom-right to read them.', $unread['reply']);
        $this->assertSame(route('messages.index'), $unread['link']['url']);

        $task = AssignedTask::query()->create(['title' => 'Submit R4-02', 'due_date' => '2026-10-25', 'visibility' => 'assigned']);
        $task->members()->create(['user_id' => $this->user->id, 'can_comment' => true, 'can_complete' => true]);
        SecretaryTodo::query()->create(['user_id' => $this->user->id, 'scope' => 'personal', 'title' => 'Call Purok 1', 'deadline' => '2026-10-24']);

        $summary = $this->reply('what is new?');
        $this->assertStringContainsString('Here is your summary for Saturday, October 24:', $summary);
        $this->assertStringContainsString('• 2 unread messages', $summary);
        $this->assertStringContainsString('• 1 task waiting for your “Mark as done” (1 due within 2 days)', $summary);
        $this->assertStringContainsString('• 1 unfinished to-do (1 due today or overdue)', $summary);
        $this->assertSame($summary, $this->reply('ano ang bago?'));
    }

    public function test_clock_answers_and_greetings_follow_manila_time_and_language(): void
    {
        $this->assertSame('Tomorrow is Sunday, October 25, 2026.', $this->reply('what day is tomorrow?'));
        $this->assertSame('It is 2026.', $this->reply('what year is it'));
        $this->assertSame('It is October 2026.', $this->reply('anong buwan ngayon'));
        $this->assertStringStartsWith('Good morning!', $this->reply('hellooo'));
        $this->assertStringStartsWith('Magandang umaga!', $this->reply('magandang umaga po'));
    }

    // ---------------------------------------------------------------- knowledge

    public function test_new_knowledge_about_tasks_pages_and_monthly_session(): void
    {
        $tasks = $this->ask('how do tasks work?');
        $this->assertStringContainsString('Every assignee presses “Mark as done” for their own part.', $tasks['reply']);
        $this->assertSame(['label' => 'Open Tasks', 'url' => route('tasks')], $tasks['link']);

        $this->assertStringContainsString('due-date calendar', $this->reply('where is the task calendar?'));
        $this->assertStringContainsString('Tools: Note Checker', $this->reply('what pages are there?'));
        $this->assertStringContainsString('ilan ang MS sa Purok 1', $this->reply('do you speak tagalog'));
        $this->assertStringContainsString('password-only sign-in', $this->reply('what is IT recovery?'));

        // Typos still reach the right answer.
        $this->assertStringContainsString('@', $this->reply('how do i mentoin someone'));

        // Monthly Session help needs Form Generator access.
        $this->assertStringContainsString('do not currently have permission', $this->reply('explain the monthly session'));
        $this->user->forceFill(['form_generator_allow' => true])->save();
        $this->assertStringContainsString('step by step', $this->reply('explain the monthly session'));
        $this->assertStringContainsString('A is the starting count', $this->reply('what do the monthly codes mean?'));
    }

    // ---------------------------------------------------------------- helpers

    private function reply(string $question): string
    {
        return (string) $this->ask($question)['reply'];
    }

    private function ask(string $question, array $extra = []): array
    {
        return $this->withSession(['login_user' => ['id' => $this->user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => $question] + $extra)
            ->assertOk()
            ->json();
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
