<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Record;
use App\Models\GlobalVariable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuiltInHelpChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_chatbot_is_linked_from_the_floating_messages_interface_without_external_setup(): void
    {
        $user = $this->makeUser();

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('messages.index', ['widget' => 1]))
            ->assertOk()
            ->assertSee('LSPBot')
            ->assertSee('data-bot-avatar=', false)
            ->assertSee('Hi! I’m LSPBot! Your LSPDMS Guide and AI Assistant!');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('messages.assistant', ['widget' => 1]))
            ->assertOk()
            ->assertSee('Built-in LSPDMS guide — no external AI service.')
            ->assertSee('Hi! I’m LSPBot! Your LSPDMS Guide and AI Assistant!')
            ->assertDontSee('GEMINI_API_KEY');
    }

    public function test_chatbot_links_to_a_feature_only_when_the_secretary_has_permission(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['Delistment_allow' => true])->save();

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'Where can I mark someone as delisted?'])
            ->assertOk()
            ->assertJsonPath('link.url', route('de-listment'))
            ->assertJsonPath('link.label', 'Open De-Listment and R2-10');

        $user->forceFill(['Delistment_allow' => false])->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How do I endorse R2-10?'])
            ->assertOk()
            ->assertJsonMissingPath('link.url')
            ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'do not currently have permission')
                && str_contains($reply, 'Ask your Head Secretary'));
    }

    public function test_chatbot_guides_secretaries_to_their_permitted_form_and_record_tools(): void
    {
        $user = $this->makeUser();

        $user->forceFill(['form_generator_allow' => true])->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'Where can I print R4-01?'])
            ->assertOk()
            ->assertJsonPath('link.url', route('form-generator'))
            ->assertJsonPath('link.label', 'Open Generating and printing forms');

        $user->forceFill(['Inquire_Allow' => true])->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How can I edit member data?'])
            ->assertOk()
            ->assertJsonPath('link.url', route('inquire-modify'));
    }

    public function test_chatbot_tells_secretaries_to_request_permission_and_recognizes_head_secretary_access(): void
    {
        $user = $this->makeUser();

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'Where is the BNH helper?'])
            ->assertOk()
            ->assertJsonMissingPath('link')
            ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Ask your Head Secretary'));

        $user->forceFill(['tungkulin' => 'Head Secretary'])->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'Where is the BNH helper?'])
            ->assertOk()
            ->assertJsonPath('link.url', route('filter-bnh-helper'));
    }

    public function test_chatbot_uses_a_helpful_fallback_for_unrecognized_questions(): void
    {
        $user = $this->makeUser();

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'Tell me something'])
            ->assertOk()
            ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'De-Listment')
                && str_contains($reply, 'form printing')
                && str_contains($reply, 'I cannot edit or change records'));
    }

    public function test_record_count_queries_require_the_chatbot_lookup_permission(): void
    {
        $user = $this->makeUser();
        $this->record(['status' => 'MS']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How many MS are there?'])
            ->assertOk()
            ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'do not have permission')
                && str_contains($reply, 'record lookups'));
    }

    public function test_chatbot_answers_status_counts_and_only_counts_active_records(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['chatbot_records_allow' => true])->save();
        $this->record(['status' => 'MS']);
        $this->record(['status' => 'ms']);
        $this->record(['status' => 'MK']);
        $this->record(['status' => 'INACTIVE']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How many MS are there?'])
            ->assertOk()
            ->assertJsonPath('reply', 'There are 2 MS record(s).');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How many active records are there?'])
            ->assertOk()
            ->assertJsonPath('reply', 'There are 3 active record(s).');
    }

    public function test_r210_aggregate_lookups_require_delistment_permission_and_exclude_already_endorsed_records(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['chatbot_records_allow' => true])->save();
        $this->record(['status' => 'MK', 'r2_10_endorsement' => null]);
        $this->record(['status' => 'MK', 'r2_10_endorsement' => 'PENDING']);
        $this->record(['status' => 'MK', 'r2_10_endorsement' => 'ENDORSED']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How many MK are not yet endorsed to R2-10?'])
            ->assertOk()
            ->assertJsonPath('reply', 'Your account cannot view R2-10 endorsement information. Ask your Head Secretary to enable De-Listment access.');

        $user->forceFill(['Delistment_allow' => true])->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How many MK are not yet endorsed to R2-10?'])
            ->assertOk()
            ->assertJsonPath('reply', 'There are 2 MK record(s) that are not yet endorsed for R2-10.');
    }

    public function test_chatbot_counts_eligible_r210_records_not_yet_endorsed_using_shared_eligibility_rules(): void
    {
        $user = $this->makeUser();
        $user->forceFill([
            'chatbot_records_allow' => true,
            'Delistment_allow' => true,
        ])->save();
        GlobalVariable::query()->create([
            'entry_years_requirement' => 1,
            'dateofbaptism_requirement_boolean' => false,
        ]);
        $this->record([
            'status' => 'MS',
            'date_of_entry' => now()->subYears(2)->toDateString(),
            'r2_10_endorsement' => null,
        ]);
        $this->record([
            'status' => 'MS',
            'date_of_entry' => now()->subYears(2)->toDateString(),
            'r2_10_endorsement' => 'PENDING',
        ]);
        $this->record([
            'status' => 'MS',
            'date_of_entry' => now()->subYears(2)->toDateString(),
            'r2_10_endorsement' => 'ENDORSED',
        ]);
        $this->record([
            'status' => 'MS',
            'date_of_entry' => now()->subMonths(6)->toDateString(),
            'r2_10_endorsement' => null,
        ]);
        $this->record([
            'status' => 'UWP',
            'date_of_entry' => now()->subYears(2)->toDateString(),
            'r2_10_endorsement' => null,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), [
                'message' => 'How many MS do we have that are eligible in R2-10 but not yet endorsed?',
            ])
            ->assertOk()
            ->assertJsonPath('reply', 'There are 2 MS record(s) eligible for R2-10 that are not yet endorsed.');
    }

    public function test_chatbot_counts_status_and_kapisanan_together(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['chatbot_records_allow' => true])->save();
        $this->record(['status' => 'MS', 'kapisanan' => 'Binhi']);
        $this->record(['status' => 'MS', 'kapisanan' => 'binhi']);
        $this->record(['status' => 'MS', 'kapisanan' => 'K']);
        $this->record(['status' => 'UWP', 'kapisanan' => 'Binhi']);
        $this->record(['status' => 'INACTIVE', 'kapisanan' => 'Binhi']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How many MS are Binhi?'])
            ->assertOk()
            ->assertJsonPath('reply', 'There are 2 MS Binhi record(s).');
    }

    public function test_name_lookup_returns_only_bounded_record_details_without_registry_numbers(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['chatbot_records_allow' => true])->save();
        $this->record([
            'full_name' => 'Jordan Sample',
            'registry' => 'PRIVATE123456',
            'status' => 'UWP',
            'purok' => '4',
            'kapisanan' => 'K',
            'r2_10_endorsement' => 'ENDORSED',
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'Find Jordan Sample in records'])
            ->assertOk()
            ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Jordan Sample')
                && str_contains($reply, 'UWP')
                && str_contains($reply, 'Purok 4')
                && ! str_contains($reply, 'PRIVATE123456')
                && ! str_contains($reply, 'R2-10 endorsed'));

        $user->forceFill(['Delistment_allow' => true])->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'Is Jordan Sample in our records?'])
            ->assertOk()
            ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'R2-10 endorsed')
                && ! str_contains($reply, 'PRIVATE123456'));
    }

    public function test_edit_requests_only_link_to_permitted_workflows_and_never_change_records(): void
    {
        $user = $this->makeUser();
        $record = $this->record(['full_name' => 'Original Name', 'status' => 'MS']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How can I edit member data?'])
            ->assertOk()
            ->assertJsonMissingPath('link.url')
            ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'do not currently have permission'));

        $user->forceFill(['Inquire_Allow' => true])->save();
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How can I edit member data?'])
            ->assertOk()
            ->assertJsonPath('link.url', route('inquire-modify'));

        $this->assertDatabaseHas('records', ['id' => $record->id, 'full_name' => 'Original Name', 'status' => 'MS']);
    }

    public function test_head_secretary_can_use_record_queries_without_the_optional_permission_flag(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['tungkulin' => 'Head Secretary'])->save();
        $this->record(['status' => 'MK']);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'How many MK are there?'])
            ->assertOk()
            ->assertJsonPath('reply', 'There are 1 MK record(s).');
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'gmail' => 'help-chatbot@example.test',
            'password' => 'password',
            'first_name' => 'Help',
            'last_name' => 'Tester',
            'is_verified' => true,
        ]);
    }

    private function record(array $attributes): Record
    {
        return Record::query()->create($attributes + [
            'full_name' => 'Sample Record',
            'status' => 'MS',
        ]);
    }
}
