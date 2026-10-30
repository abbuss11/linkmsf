<?php

namespace Database\Factories;

use App\Enums\JobPostingStatus;
use App\Enums\JobPostingType;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'organization_id' => Organization::factory(),
            'type' => JobPostingType::Employment,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'reference' => 'REF-'.Str::upper(Str::random(6)),
            'description' => '<p>'.fake()->sentence().'</p>',
            'location' => 'Niamey',
            'status' => JobPostingStatus::Draft,
            'settings' => JobPosting::DEFAULT_SETTINGS,
            'closes_at' => now()->addMonth(),
            'wizard_step' => 1,
        ];
    }

    /**
     * Creates the application form alongside, with the given fields.
     *
     * @param  list<array<string, mixed>>  $fields
     */
    public function withForm(array $fields = []): static
    {
        return $this->afterCreating(function (JobPosting $posting) use ($fields): void {
            $form = new ApplicationForm(['schema' => ['fields' => $fields]]);
            $form->organization_id = $posting->organization_id;
            $form->job_posting_id = $posting->getKey();
            $form->save();
        });
    }

    public function status(JobPostingStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'published_at' => $status === JobPostingStatus::Draft ? null : now(),
        ]);
    }
}
