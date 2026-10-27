<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'type' => UserType::Internal,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function candidate(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Candidate,
        ]);
    }

    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Internal,
        ])->afterMaking(function (User $user): void {
            $user->is_superadmin = true;
        });
    }

    public function withEmailAuthentication(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->has_email_authentication = true;
        });
    }
}
