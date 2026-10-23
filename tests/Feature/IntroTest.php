<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntroTest extends TestCase
{
    use RefreshDatabase;

    public function test_intro_page_has_a_skip_link_to_login(): void
    {
        $this->get('/intro')
            ->assertOk()
            ->assertSee('Skip intro')
            ->assertSee('href="'.url('/login').'"', false);
    }

    public function test_login_displays_session_success_as_a_centered_dismissible_message(): void
    {
        $this->withSession(['status' => 'Registration submitted.'])
            ->get('/login')
            ->assertOk()
            ->assertSee('id="appNotice"', false)
            ->assertSee('Registration submitted.')
            ->assertSee('role="alertdialog"', false)
            ->assertSee('app-notice-ok', false);
    }

    public function test_authenticated_user_visiting_intro_or_login_is_redirected_to_home(): void
    {
        $user = User::query()->create([
            'gmail' => 'authenticated.guest@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
        $session = ['login_user' => ['id' => $user->id]];

        $this->withSession($session)
            ->get('/intro')
            ->assertRedirect('/home');
        $this->withSession($session)
            ->get('/login')
            ->assertRedirect('/home');
    }

    public function test_unverified_session_user_is_not_redirected_away_from_login(): void
    {
        $user = User::query()->create([
            'gmail' => 'unverified.guest@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => false,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->get('/login')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login')
            ->assertSessionMissing('login_user');
    }
}
