<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->phoneNumber(),
            'role' => fake()->randomElement(['admin', 'moderator', 'user']),
            'status' => 'active',
            'banned_until' => null,
            'avatar' => null,
            'preferences' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Admin state
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'email' => 'admin@hotel.com',
        ]);
    }

    /**
     * Moderator state
     */
    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'moderator',
        ]);
    }

    /**
     * Banned state
     */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'banned',
            'banned_until' => now()->addDays(30),
        ]);
    }

    /**
     * Permanently banned
     */
    public function permanentlyBanned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'banned',
            'banned_until' => null, // null означает навсегда
        ]);
    }
}
