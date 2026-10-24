<?php

namespace Tests\Feature;

use App\Broadcasting\ConversationChannel;
use App\Events\MessageSent;
use App\Http\Controllers\ProfilePictureController;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_search_lists_other_accounts_including_suspended_and_blocks_self_messages(): void
    {
        $sender = $this->makeUser('sender@example.test');
        $other = $this->makeUser('other@example.test');
        $suspended = $this->makeUser('suspended@example.test', false);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->getJson(route('messages.accounts').'?q=example.test')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $other->id, 'can_message' => true, 'status' => 'Active'])
            ->assertJsonFragment(['id' => $suspended->id, 'can_message' => false, 'status' => 'Suspended']);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->postJson(route('messages.conversations.create'), ['target_user_id' => $sender->id])
            ->assertUnprocessable();

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->postJson(route('messages.conversations.create'), ['target_user_id' => $suspended->id])
            ->assertForbidden()
            ->assertJsonPath('message', "Can't message this user, this account has been suspended.");
    }

    public function test_account_search_matches_full_names_and_secretary_role(): void
    {
        $sender = $this->makeUser('sender@example.test');
        $secretary = User::query()->create([
            'gmail' => 'taylor.secretary@example.test',
            'password' => 'password',
            'first_name' => 'Taylor',
            'last_name' => 'Secretary',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->getJson(route('messages.accounts').'?q=Taylor%20Secretary')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $secretary->id)
            ->assertJsonPath('0.role', 'Secretary');

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->getJson(route('messages.accounts').'?q=Secretary')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $secretary->id);
    }

    public function test_account_and_conversation_payloads_include_public_profile_photo_urls(): void
    {
        Storage::fake('public');
        $sender = $this->makeUser('sender@example.test');
        $recipient = $this->makeUser('recipient@example.test');
        $photoPath = $this->pngUpload('profile.png')->store('profile-pictures', 'public');
        $recipient->forceFill(['profile_picture' => $photoPath])->save();
        $this->conversation($sender, $recipient);
        // Served by the authenticated avatar route (no public/storage symlink needed).
        $photoUrl = ProfilePictureController::urlFor($recipient->id, $photoPath);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->getJson(route('messages.accounts').'?q=recipient')
            ->assertOk()
            ->assertJsonPath('0.avatar_url', $photoUrl);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->getJson(route('messages.conversations'))
            ->assertOk()
            ->assertJsonPath('0.user.avatar_url', $photoUrl);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->get($photoUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $this->flushSession();
        $this->get($photoUrl)->assertRedirect('/login');
    }

    public function test_existing_conversation_shows_suspended_account_status_without_allowing_access(): void
    {
        $active = $this->makeUser('active@example.test');
        $suspended = $this->makeUser('suspended@example.test', false);
        $conversation = $this->conversation($active, $suspended);

        $this->withSession(['login_user' => ['id' => $active->id]])
            ->getJson(route('messages.conversations'))
            ->assertOk()
            ->assertJsonPath('0.user.id', $suspended->id)
            ->assertJsonPath('0.user.can_message', false)
            ->assertJsonPath('0.user.status', 'Suspended');

        $this->withSession(['login_user' => ['id' => $active->id]])
            ->getJson(route('messages.list', $conversation))
            ->assertForbidden()
            ->assertJsonPath('message', "Can't message this user, this account has been suspended.");
    }

    public function test_messages_open_in_a_minimizable_floating_chat_not_the_sidebar_navigation(): void
    {
        $user = $this->makeUser('sender@example.test');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('class="chat-head-launcher"', false)
            ->assertSee('aria-label="Open messages"', false)
            ->assertSee('id="chatUnreadBadge"', false)
            ->assertSee('data-unread-url="'.route('messages.unread-count').'"', false)
            ->assertSee('id="chatWindow"', false)
            ->assertSee('aria-label="Minimize chat"', false)
            ->assertSee('Direct messages', false)
            ->assertDontSee('<span class="label">Messages</span>', false);
    }

    public function test_chat_widget_shows_a_conversation_placeholder_and_visible_error_region(): void
    {
        $user = $this->makeUser('sender@example.test');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('messages.index', ['widget' => 1]))
            ->assertOk()
            ->assertSee('id="messagesApp"', false)
            ->assertSee('id="accountSearch"', false)
            ->assertSee('id="messagesSidebarAlert"', false)
            ->assertSee('Loading your conversations…')
            ->assertSee('Search registered accounts to start a private conversation.');
    }

    public function test_unread_count_only_includes_unread_unexpired_messages_received_by_current_user(): void
    {
        $recipient = $this->makeUser('recipient@example.test');
        $sender = $this->makeUser('sender@example.test');
        $other = $this->makeUser('other@example.test');
        $conversation = $this->conversation($recipient, $sender);
        $otherConversation = $this->conversation($recipient, $other);

        $unread = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'Unread message',
            'expires_at' => now()->addDay(),
        ]);
        $unreadChannels = (new MessageSent($unread))->broadcastOn();
        $this->assertSame('private-user.'.$recipient->id, $unreadChannels[1]->name);

        $reverseDirectionMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $recipient->id,
            'body' => 'Reply',
            'expires_at' => now()->addDay(),
        ]);
        $reverseDirectionChannels = (new MessageSent($reverseDirectionMessage))->broadcastOn();
        $this->assertSame('private-user.'.$sender->id, $reverseDirectionChannels[1]->name);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'Read message',
            'read_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $recipient->id,
            'body' => 'Sent by me',
            'expires_at' => now()->addDay(),
        ]);
        Message::query()->create([
            'conversation_id' => $otherConversation->id,
            'sender_id' => $other->id,
            'body' => 'Expired message',
            'expires_at' => now()->subSecond(),
        ]);

        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->getJson(route('messages.unread-count'))
            ->assertOk()
            ->assertExactJson(['unread_count' => 1]);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->getJson(route('messages.unread-count'))
            ->assertOk()
            ->assertExactJson(['unread_count' => 2]);

        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->postJson(route('messages.read', $unread))
            ->assertOk();

        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->getJson(route('messages.unread-count'))
            ->assertOk()
            ->assertExactJson(['unread_count' => 0]);
    }

    public function test_floating_chat_loads_messages_without_the_application_shell(): void
    {
        $user = $this->makeUser('sender@example.test');

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get(route('messages.index', ['widget' => 1]))
            ->assertOk()
            ->assertSee('class="chat-frame-page"', false)
            ->assertSee('id="messagesApp"', false)
            ->assertDontSee('id="chatWidget"', false);
    }

    public function test_messages_support_private_images_receipts_and_participant_authorization(): void
    {
        Storage::fake('local');
        $sender = $this->makeUser('sender@example.test');
        $recipient = $this->makeUser('recipient@example.test');
        $outsider = $this->makeUser('outsider@example.test');
        $conversation = $this->conversation($sender, $recipient);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->postJson(route('messages.send', $conversation), ['body' => 'Hello 👋'])
            ->assertCreated()
            ->assertJsonPath('body', 'Hello 👋');
        $sentMessage = Message::query()->where('conversation_id', $conversation->id)->firstOrFail();
        $broadcastChannels = (new MessageSent($sentMessage))->broadcastOn();
        $this->assertSame('private-conversation.'.$conversation->id, $broadcastChannels[0]->name);
        $this->assertSame('private-user.'.$recipient->id, $broadcastChannels[1]->name);

        $image = $this->pngUpload('private.png');
        $response = $this->withSession(['login_user' => ['id' => $sender->id]])
            ->post(route('messages.send', $conversation), ['attachment' => $image]);
        $response->assertCreated();
        $imageMessage = Message::query()->findOrFail($response->json('id'));
        Storage::disk('local')->assertExists($imageMessage->attachment_path);

        $this->withSession(['login_user' => ['id' => $outsider->id]])
            ->getJson(route('messages.list', $conversation))
            ->assertNotFound();
        $this->withSession(['login_user' => ['id' => $outsider->id]])
            ->postJson(route('messages.send', $conversation), ['body' => 'Unauthorized'])
            ->assertNotFound();
        $this->withSession(['login_user' => ['id' => $outsider->id]])
            ->get(route('messages.attachment', $imageMessage))
            ->assertNotFound();

        $textMessage = Message::query()->where('conversation_id', $conversation->id)
            ->whereNotNull('body')->firstOrFail();
        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->postJson(route('messages.delivered', $textMessage))
            ->assertOk();
        $this->assertNotNull($textMessage->fresh()->delivered_at);

        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->getJson(route('messages.list', $conversation))
            ->assertOk()->assertJsonCount(2);
        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->postJson(route('messages.conversation-read', $conversation))
            ->assertOk();
        $this->assertNotNull($textMessage->fresh()->read_at);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->get(route('messages.attachment', $imageMessage))
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('content-disposition', 'inline; filename="private.png"');

        // The image survives losing the disk copy: it is served from the database copy.
        Storage::disk('local')->delete($imageMessage->attachment_path);
        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->get(route('messages.attachment', $imageMessage))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        Storage::disk('local')->assertExists($imageMessage->attachment_path);
    }

    public function test_upload_rejects_non_image_files_and_cleanup_removes_expired_messages_and_files(): void
    {
        Storage::fake('local');
        $sender = $this->makeUser('sender@example.test');
        $recipient = $this->makeUser('recipient@example.test');
        $conversation = $this->conversation($sender, $recipient);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->postJson(route('messages.send', $conversation), [
                'attachment' => UploadedFile::fake()->create('payload.pdf', 10, 'application/pdf'),
            ])
            ->assertUnprocessable();

        $response = $this->withSession(['login_user' => ['id' => $sender->id]])
            ->post(route('messages.send', $conversation), ['attachment' => $this->pngUpload('expired.png')]);
        $message = Message::query()->findOrFail($response->json('id'));
        $path = $message->attachment_path;
        Storage::disk('local')->assertExists($path);
        $message->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->artisan('messages:cleanup-expired')->assertSuccessful();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    public function test_private_channel_authorization_uses_the_custom_login_session(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'messaging-test-key',
            'broadcasting.connections.reverb.secret' => 'messaging-test-secret',
            'broadcasting.connections.reverb.app_id' => 'messaging-test-app',
        ]);
        $sender = $this->makeUser('sender@example.test');
        $recipient = $this->makeUser('recipient@example.test');
        $outsider = $this->makeUser('outsider@example.test');
        $conversation = $this->conversation($sender, $recipient);
        $this->assertTrue((new ConversationChannel)->join($recipient, (int) $conversation->id));
        $channel = 'private-conversation.'.$conversation->id;

        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->postJson(route('broadcasting.auth'), ['socket_id' => '123.456', 'channel_name' => $channel])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->postJson(route('broadcasting.auth'), ['socket_id' => '123.457', 'channel_name' => 'private-user.'.$recipient->id])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->withSession(['login_user' => ['id' => $outsider->id]])
            ->postJson(route('broadcasting.auth'), ['socket_id' => '123.789', 'channel_name' => $channel])
            ->assertForbidden();

        $this->withSession(['login_user' => ['id' => $outsider->id]])
            ->postJson(route('broadcasting.auth'), ['socket_id' => '123.790', 'channel_name' => 'private-user.'.$recipient->id])
            ->assertForbidden();
    }

    public function test_loading_a_conversation_is_read_only_and_marking_read_needs_a_post(): void
    {
        $sender = $this->makeUser('sender@example.test');
        $recipient = $this->makeUser('recipient@example.test');
        $outsider = $this->makeUser('outsider@example.test');
        $conversation = $this->conversation($sender, $recipient);
        $seen = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'Seen',
            'expires_at' => now()->addDay(),
        ]);

        // A cross-site navigation can only issue a GET, which must not change anything.
        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->getJson(route('messages.list', $conversation))
            ->assertOk()->assertJsonPath('0.read_at', null);
        $this->assertNull($seen->fresh()->read_at);
        $this->assertNull($seen->fresh()->delivered_at);

        $arrivedLater = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => 'Arrived after the list loaded',
            'expires_at' => now()->addDay(),
        ]);

        $this->withSession(['login_user' => ['id' => $outsider->id]])
            ->postJson(route('messages.conversation-read', $conversation), ['up_to_id' => $seen->id])
            ->assertNotFound();
        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->postJson(route('messages.conversation-read', $conversation), ['up_to_id' => $seen->id])
            ->assertOk()
            ->assertExactJson(['read_ids' => [$seen->id]]);

        $this->assertNotNull($seen->fresh()->read_at);
        $this->assertNotNull($seen->fresh()->delivered_at);
        $this->assertNull($arrivedLater->fresh()->read_at);
        $this->withSession(['login_user' => ['id' => $recipient->id]])
            ->getJson(route('messages.unread-count'))
            ->assertExactJson(['unread_count' => 1]);

        // The sender's own messages are never marked read on the sender's behalf.
        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->postJson(route('messages.conversation-read', $conversation))
            ->assertOk()
            ->assertExactJson(['read_ids' => []]);
        $this->assertNull($arrivedLater->fresh()->read_at);
    }

    public function test_conversation_list_uses_a_fixed_number_of_queries(): void
    {
        $user = $this->makeUser('owner@example.test');
        $countQueries = function () use ($user): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->withSession(['login_user' => ['id' => $user->id]])
                ->getJson(route('messages.conversations'))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $addConversation = function (int $index) use ($user): void {
            $contact = $this->makeUser("contact{$index}@example.test");
            $conversation = $this->conversation($user, $contact);
            foreach (['First', 'Latest'] as $body) {
                Message::query()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $contact->id,
                    'body' => "{$body} from {$index}",
                    'expires_at' => now()->addDay(),
                ]);
            }
        };

        $addConversation(1);
        $queriesForOne = $countQueries();
        foreach (range(2, 6) as $index) {
            $addConversation($index);
        }
        $this->assertSame($queriesForOne, $countQueries(), 'Each extra conversation must not add queries.');

        $response = $this->withSession(['login_user' => ['id' => $user->id]])
            ->getJson(route('messages.conversations'))
            ->assertOk()
            ->assertJsonCount(6);
        foreach ($response->json() as $row) {
            $this->assertSame(2, $row['unread_count']);
            $this->assertStringStartsWith('Latest from ', $row['last_message']['body']);
            $this->assertSame('Contact'.substr($row['last_message']['body'], 12).' Test', $row['last_message']['sender_name']);
        }
    }

    public function test_unread_badge_does_not_poll_fast_while_hidden_or_while_the_socket_is_live(): void
    {
        $script = file_get_contents(resource_path('js/chat-notifications.js'));

        // The old unconditional 5 s interval kept every tab, even hidden ones, polling forever.
        $this->assertDoesNotMatchRegularExpression('/setInterval\(\s*refreshUnreadCount/', $script);
        $this->assertMatchesRegularExpression('/function schedulePoll\(\)\s*\{[^}]*if \(document\.hidden\) return;/', $script);
        $this->assertMatchesRegularExpression("/realtimeState === 'connected' \? CONNECTED_POLL_MS : FALLBACK_POLL_MS/", $script);
        $this->assertMatchesRegularExpression('/const CONNECTED_POLL_MS = (6|7|8|9)\d{4}|const CONNECTED_POLL_MS = 1[0-2]\d{4}/', $script);
        $this->assertMatchesRegularExpression('/const FALLBACK_POLL_MS = 5000;/', $script);
    }

    private function makeUser(string $email, bool $verified = true): User
    {
        return User::query()->create([
            'gmail' => $email,
            'password' => 'password',
            'first_name' => ucfirst(strstr($email, '@', true)),
            'last_name' => 'Test',
            'is_verified' => $verified,
        ]);
    }

    private function conversation(User $one, User $two): Conversation
    {
        return Conversation::query()->create([
            'user_one_id' => min($one->id, $two->id),
            'user_two_id' => max($one->id, $two->id),
        ]);
    }

    private function pngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pWQAAAAASUVORK5CYII='),
        );
    }
}
