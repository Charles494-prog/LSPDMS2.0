<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_pending_secretary_with_a_hashed_password(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create your account')
            ->assertSee('Head Secretary must grant access')
            ->assertSee('At least one special character');

        $this->post(route('register.store'), [
            'first_name' => '  Alex ',
            'last_name' => 'Member  ',
            'email' => '  Alex.Member@Example.Test ',
            'password' => 'Secure-pass-123!',
            'password_confirmation' => 'Secure-pass-123!',
        ])
            ->assertRedirect(route('login.form'))
            ->assertSessionHas('status');

        $user = User::query()->where('gmail', 'alex.member@example.test')->firstOrFail();
        $this->assertSame('Alex', $user->first_name);
        $this->assertSame('Member', $user->last_name);
        $this->assertSame('Secretary', $user->tungkulin);
        $this->assertFalse($user->is_verified);
        $this->assertTrue(Hash::check('Secure-pass-123!', $user->password));
        $this->assertNotSame('Secure-pass-123!', $user->password);
        $this->assertFalse($user->dashboard_allow);
        $this->assertFalse($user->form_generator_allow);
        $this->assertFalse($user->Delistment_allow);
        $this->assertFalse($user->Inquire_Allow);
        $this->assertFalse($user->tarheta_status_allow);
        $this->assertFalse($user->filter_bnh_helper_allow);
        $this->assertFalse($user->chatbot_records_allow);

        $this->post(route('login'), [
            'email' => 'alex.member@example.test',
            'password' => 'secure-pass-123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('login');
    }

    public function test_registration_rejects_case_insensitive_duplicate_email(): void
    {
        User::query()->create([
            'gmail' => 'existing@example.test',
            'password' => 'existing-password',
            'tungkulin' => 'Secretary',
            'is_verified' => false,
        ]);

        $this->post(route('register.store'), [
            'first_name' => 'Another',
            'last_name' => 'User',
            'email' => ' Existing@Example.Test ',
            'password' => 'Secure-pass-123!',
            'password_confirmation' => 'Secure-pass-123!',
        ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('login_user', 1);
    }

    public function test_registration_requires_a_confirmed_password_with_letters_numbers_and_symbols(): void
    {
        $this->post(route('register.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'short@example.test',
            'password' => 'passwordonly',
            'password_confirmation' => 'passwordonly',
        ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('login_user', 0);

        $this->post(route('register.store'), [
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'no-symbol@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('login_user', 0);
    }
}
