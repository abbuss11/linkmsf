<?php

namespace Database\Factories;

use App\Enums\RequirementUsage;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobRequirement>
 */
class JobRequirementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'organization_id' => fn (array $attributes) => JobPosting::queryAcrossOrganizations()->find($attributes['job_posting_id'])?->organization_id,
            'label' => fake()->sentence(3),
            'usage' => RequirementUsage::Desired,
            'position' => 0,
        ];
    }
}
