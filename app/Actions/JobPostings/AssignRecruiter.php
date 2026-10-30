<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Exceptions\ActionFailed;
use App\Models\JobPosting;
use App\Models\JobPostingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssignRecruiter
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, JobPosting $posting, User $recruiter): void
    {
        Gate::forUser($actor)->authorize('assign', $posting);

        if ($recruiter->roleIn($posting->organization_id) !== OrganizationRole::Recruiter) {
            throw ActionFailed::because('job_postings.errors.not_a_recruiter');
        }

        DB::transaction(function () use ($actor, $posting, $recruiter): void {
            $assignment = JobPostingAssignment::queryAcrossOrganizations()->firstOrCreate(
                ['job_posting_id' => $posting->getKey(), 'user_id' => $recruiter->getKey()],
                ['organization_id' => $posting->organization_id, 'assigned_by' => $actor->getKey()],
            );

            if ($assignment->wasRecentlyCreated) {
                $this->audit->handle(
                    AuditEvent::RecruiterAssigned,
                    subject: $posting,
                    causer: $actor,
                    organization: $posting->organization_id,
                    properties: ['user_id' => $recruiter->getKey()],
                );
            }
        });
    }
}
