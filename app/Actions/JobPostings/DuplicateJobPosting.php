<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\JobPostingAssignment;
use App\Models\JobRequirement;
use App\Models\Organization;
use App\Models\User;
use App\Support\JobPostings\JobPostingReference;
use App\Support\JobPostings\JobPostingSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Copies an offer with its criteria and form into a new draft, to relaunch
 * a call (§3.1). Dates are cleared; no published version is carried over.
 */
class DuplicateJobPosting
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, JobPosting $source): JobPosting
    {
        $organization = Organization::query()->findOrFail($source->organization_id);

        Gate::forUser($actor)->authorize('duplicate', $source);
        Gate::forUser($actor)->authorize('create', [JobPosting::class, $organization]);

        return DB::transaction(function () use ($actor, $source, $organization) {
            $copy = $source->replicate(['ulid', 'slug', 'reference', 'status', 'opens_at', 'closes_at', 'published_at', 'closed_at', 'created_by', 'wizard_step']);
            $copy->title = __('job_postings.duplicate_title', ['title' => $source->title]);
            $copy->slug = JobPostingSlug::generate($copy->title, $organization);
            $copy->reference = JobPostingReference::next($organization);
            $copy->status = JobPostingStatus::Draft;
            $copy->created_by = $actor->getKey();
            $copy->wizard_step = 1;
            $copy->save();

            $form = ApplicationForm::queryForOrganization($organization)->where('job_posting_id', $source->getKey())->firstOrFail();
            $formCopy = new ApplicationForm(['schema' => $form->schema, 'updated_by' => $actor->getKey()]);
            $formCopy->organization_id = $organization->getKey();
            $formCopy->job_posting_id = $copy->getKey();
            $formCopy->save();

            foreach (JobRequirement::queryForOrganization($organization)->where('job_posting_id', $source->getKey())->get() as $requirement) {
                $requirementCopy = $requirement->replicate();
                $requirementCopy->job_posting_id = $copy->getKey();
                $requirementCopy->save();
            }

            if ($actor->roleIn($organization) === OrganizationRole::Recruiter) {
                JobPostingAssignment::create([
                    'organization_id' => $organization->getKey(),
                    'job_posting_id' => $copy->getKey(),
                    'user_id' => $actor->getKey(),
                    'assigned_by' => $actor->getKey(),
                ]);
            }

            $this->audit->handle(
                AuditEvent::JobPostingDuplicated,
                subject: $copy,
                causer: $actor,
                organization: $organization,
                properties: ['source_reference' => $source->reference, 'reference' => $copy->reference],
            );

            return $copy;
        });
    }
}
