<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'organization_id' => fn (array $attributes) => JobPosting::queryAcrossOrganizations()->find($attributes['job_posting_id'])?->organization_id,
            'candidate_user_id' => User::factory()->candidate(),
            'status' => ApplicationStatus::Draft,
        ];
    }
}
