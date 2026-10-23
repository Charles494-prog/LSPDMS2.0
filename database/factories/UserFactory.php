<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Accounts in login_user for tests. Every account gets a random password, so
 * a factory row can never be signed into with a guessable one.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gmail' => fake()->unique()->safeEmail(),
            'password' => Str::password(24),
            'tungkulin' => 'Secretary',
            'is_verified' => true,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'dashboard_allow' => false,
            'form_generator_allow' => false,
            'Delistment_allow' => false,
            'Inquire_Allow' => false,
            'tarheta_status_allow' => false,
            'filter_bnh_helper_allow' => false,
            'chatbot_records_allow' => false,
        ];
    }

    /** A registration still waiting for Head Secretary approval. */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['is_verified' => false]);
    }

    public function headSecretary(): static
    {
        return $this->state(fn (array $attributes) => ['tungkulin' => 'Head Secretary']);
    }
}
