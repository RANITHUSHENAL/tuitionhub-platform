<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Fictional data only. Phones match users_phone_format_chk; no SMS is ever sent from tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'phone' => '+947'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => null,
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }
}
