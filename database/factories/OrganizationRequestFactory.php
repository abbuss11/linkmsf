<?php

namespace Database\Factories;

use App\Enums\OrganizationRequestStatus;
use App\Models\OrganizationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationRequest>
 */
class OrganizationRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_name' => fake()->unique()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+227 90 00 00 00',
            'country' => 'NE',
            'message' => fake()->sentence(),
            'status' => OrganizationRequestStatus::Pending,
        ];
    }
}
