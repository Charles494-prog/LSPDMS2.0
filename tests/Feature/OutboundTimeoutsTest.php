<?php

namespace Tests\Feature;

use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\User;
use App\Services\SystemSmtpConfiguration;
use App\Support\SafeBroadcast;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * Broadcasts and emails are sent inside the request on a single-threaded server,
 * so a stalled Reverb or SMTP server must not hold every user's pages for long.
 */
class OutboundTimeoutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverb_client_gives_up_within_a_few_seconds(): void
    {
        $reverb = config('broadcasting.connections.reverb');

        // The Pusher client passes options.timeout on every request, so both must be bounded.
        $this->assertLessThanOrEqual(5, $reverb['options']['timeout']);
        $this->assertLessThanOrEqual(5, $reverb['client_options']['timeout']);
        $this->assertLessThanOrEqual(5, $reverb['client_options']['connect_timeout']);
    }

    public function test_a_failed_broadcast_pauses_broadcasting_instead_of_retrying_every_request(): void
    {
        $attempts = $this->failingReverb();
        Exceptions::fake();
        $event = new UserTyping(1, 1, 'Tester');

        SafeBroadcast::send($event);
        SafeBroadcast::send($event);
        SafeBroadcast::send($event);

        $this->assertSame(1, $attempts->count);
        Exceptions::assertReportedCount(1);
        $this->assertTrue(Cache::has(SafeBroadcast::PAUSE_KEY));

        $this->travel(SafeBroadcast::PAUSE_SECONDS + 1)->seconds();
        SafeBroadcast::send($event);

        $this->assertSame(2, $attempts->count);
    }

    public function test_typing_and_sending_still_succeed_while_reverb_is_failing(): void
    {
        $attempts = $this->failingReverb();
        Exceptions::fake();
        $sender = $this->makeUser('sender@example.test');
        $recipient = $this->makeUser('recipient@example.test');
        $conversation = Conversation::query()->create([
            'user_one_id' => min($sender->id, $recipient->id),
            'user_two_id' => max($sender->id, $recipient->id),
        ]);

        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->postJson(route('messages.typing', $conversation))->assertOk();
        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->postJson(route('messages.typing', $conversation))->assertOk();
        $this->withSession(['login_user' => ['id' => $sender->id]])
            ->postJson(route('messages.send', $conversation), ['body' => 'Still delivered'])->assertSuccessful();

        $this->assertSame(1, $attempts->count);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'Still delivered']);
    }

    public function test_smtp_mailers_have_a_bounded_timeout(): void
    {
        $this->assertSame(10.0, config('mail.mailers.smtp.timeout'));

        config(['mail.mailers.smtp.timeout' => null]);
        app(SystemSmtpConfiguration::class)->applyCredentials('office@example.test', 'app-password');

        $this->assertSame((float) SystemSmtpConfiguration::TIMEOUT_SECONDS, config('mail.mailers.smtp.timeout'));
    }

    /** Points the reverb connection at a broadcaster that always fails, and counts its attempts. */
    private function failingReverb(): object
    {
        $attempts = new class
        {
            public int $count = 0;
        };

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'timeouts-test-key',
            'broadcasting.connections.reverb.secret' => 'timeouts-test-secret',
            'broadcasting.connections.reverb.app_id' => 'timeouts-test-app',
        ]);
        Broadcast::extend('reverb', fn () => new class($attempts) extends Broadcaster
        {
            public function __construct(private object $attempts) {}

            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                $this->attempts->count++;

                throw new BroadcastException('Pusher error: cURL error 28: Operation timed out after 3000 milliseconds.');
            }
        });
        Broadcast::purge('reverb');

        return $attempts;
    }

    private function makeUser(string $email): User
    {
        return User::query()->create([
            'gmail' => $email,
            'password' => 'password',
            'first_name' => ucfirst(strstr($email, '@', true)),
            'last_name' => 'Test',
            'is_verified' => true,
        ]);
    }
}
