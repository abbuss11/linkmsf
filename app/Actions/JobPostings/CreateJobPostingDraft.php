<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Exceptions\ActionFailed;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\JobPostingAssignment;
use App\Models\Organization;
use App\Models\User;
use App\Support\HtmlSanitizer;
use App\Support\JobPostings\JobPostingReference;
use App\Support\JobPostings\JobPostingRules;
use App\Support\JobPostings\JobPostingSlug;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Wizard step 1: creates the offer as a draft, with an empty form. The
 * creator is assigned to the offer when they are a recruiter.
 */
class CreateJobPostingDraft
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Organization $organization, array $data): JobPosting
    {
        Gate::forUser($actor)->authorize('create', [JobPosting::class, $organization]);

        $data = Validator::make($data, JobPostingRules::details())->validate();

        if (filled($data['reference'] ?? null) && JobPostingReference::exists($organization, $data['reference'])) {
            throw ActionFailed::because('job_postings.errors.reference_taken');
        }

        return DB::transaction(function () use ($actor, $organization, $data) {
            $posting = new JobPosting(Arr::except($data, ['reference', 'description']));
            $posting->description = $this->sanitizer->clean($data['description'] ?? null);
            $posting->organization_id = $organization->getKey();
            $posting->status = JobPostingStatus::Draft;
            $posting->slug = JobPostingSlug::generate($data['title'], $organization);
            $posting->reference = $data['reference'] ?? JobPostingReference::next($organization);
            $posting->settings = JobPosting::DEFAULT_SETTINGS;
            $posting->created_by = $actor->getKey();
            $posting->wizard_step = 2;
            $posting->save();

            $form = new ApplicationForm(['schema' => ['fields' => []], 'updated_by' => $actor->getKey()]);
            $form->organization_id = $organization->getKey();
            $form->job_posting_id = $posting->getKey();
            $form->save();

            if ($actor->roleIn($organization) === OrganizationRole::Recruiter) {
                JobPostingAssignment::create([
                    'organization_id' => $organization->getKey(),
                    'job_posting_id' => $posting->getKey(),
                    'user_id' => $actor->getKey(),
                    'assigned_by' => $actor->getKey(),
                ]);
            }

            $this->audit->handle(
                AuditEvent::JobPostingCreated,
                subject: $posting,
                causer: $actor,
                organization: $organization,
                properties: ['reference' => $posting->reference, 'title' => $posting->title],
            );

            return $posting;
        });
    }
}
