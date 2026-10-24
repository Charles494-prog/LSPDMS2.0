<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each "throttle:N,M" limit is counted per route and per signed-in user, not one shared counter per IP
 * (the shared counter made background polling use up LSPBot's limit: "Too Many Attempts.").
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_background_polling_does_not_use_up_the_lspbot_limit(): void
    {
        $user = $this->secretary('ana@example.test');

        // 40 throttled background requests (the new-task reminder polls this route)…
        for ($i = 0; $i < 40; $i++) {
            $this->withSession(['login_user' => ['id' => $user->id]])->getJson(route('assigned-tasks.unseen'))->assertOk();
        }

        // …still leave LSPBot its own budget.
        $this->withSession(['login_user' => ['id' => $user->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'hello'])
            ->assertOk();
    }

    public function test_people_on_the_same_computer_have_their_own_limits(): void
    {
        $ana = $this->secretary('ana@example.test');
        $ben = $this->secretary('ben@example.test');

        for ($i = 0; $i < 30; $i++) {
            $this->withSession(['login_user' => ['id' => $ana->id]])
                ->postJson(route('messages.assistant.reply'), ['message' => 'hello'])
                ->assertOk();
        }
        // Ana reached her limit…
        $this->withSession(['login_user' => ['id' => $ana->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'hello'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
        // …Ben (same IP) is not affected.
        $this->withSession(['login_user' => ['id' => $ben->id]])
            ->postJson(route('messages.assistant.reply'), ['message' => 'hello'])
            ->assertOk();
    }

    public function test_signed_out_routes_are_still_limited_by_ip(): void
    {
        // Registration (throttle:5,1) before anyone is signed in: counted per IP.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.store'), [])->assertStatus(302);
        }
        $this->post(route('register.store'), [])->assertStatus(429);
    }

    private function secretary(string $email): User
    {
        return User::query()->create([
            'gmail' => $email,
            'password' => 'password',
            'first_name' => 'Test',
            'last_name' => 'User',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
    }
}
