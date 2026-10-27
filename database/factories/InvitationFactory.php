<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Plain token of the last invitation made by this factory, for tests.
     */
    public static ?string $lastPlainToken = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static::$lastPlainToken = Str::random(64);

        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => OrganizationRole::Recruiter,
            'token_hash' => Invitation::hashToken(static::$lastPlainToken),
            'expires_at' => now()->addDays(Invitation::VALIDITY_DAYS),
        ];
    }

    public function withToken(string $plainToken): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => Invitation::hashToken($plainToken),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->afterMaking(fn (Invitation $invitation) => $invitation->revoked_at = now());
    }
}
