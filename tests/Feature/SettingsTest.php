<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_and_save_their_theme_preference(): void
    {
        $user = User::query()->create([
            'gmail' => 'settings-user@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);
        $session = ['login_user' => ['id' => $user->id, 'dark_mode' => false]];

        $this->withSession($session)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('User Settings')
            ->assertSee('name="dark_mode" value="0"', false)
            ->assertSee('name="dark_mode" value="1"', false)
            ->assertSee('data-theme="light"', false);

        $this->withSession($session)
            ->put(route('settings.update'), ['dark_mode' => '1'])
            ->assertRedirect()
            ->assertSessionHas('login_user.dark_mode', true);

        $this->assertDatabaseHas('login_user', [
            'id' => $user->id,
            'dark_mode' => 1,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id, 'dark_mode' => true]])
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false);

        $this->withSession(['login_user' => ['id' => $user->id, 'dark_mode' => true]])
            ->put(route('settings.update'), ['dark_mode' => '0'])
            ->assertRedirect()
            ->assertSessionHas('login_user.dark_mode', false);

        $this->assertDatabaseHas('login_user', [
            'id' => $user->id,
            'dark_mode' => 0,
        ]);
    }

    public function test_theme_switch_can_save_preference_as_json(): void
    {
        $user = User::query()->create([
            'gmail' => 'settings-ajax@example.test',
            'password' => 'password',
            'tungkulin' => 'Secretary',
            'is_verified' => true,
        ]);

        $this->withSession(['login_user' => ['id' => $user->id]])
            ->putJson(route('settings.update'), ['dark_mode' => true])
            ->assertOk()
            ->assertExactJson(['dark_mode' => true]);

        $this->assertDatabaseHas('login_user', [
            'id' => $user->id,
            'dark_mode' => 1,
        ]);
    }
}
