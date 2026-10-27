<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => OrganizationRole::Recruiter,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function role(OrganizationRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MembershipStatus::Suspended]);
    }
}
