<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\JobPosting;
use App\Models\JobPostingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UnassignRecruiter
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, JobPosting $posting, User $recruiter): void
    {
        Gate::forUser($actor)->authorize('assign', $posting);

        DB::transaction(function () use ($actor, $posting, $recruiter): void {
            $deleted = JobPostingAssignment::queryAcrossOrganizations()
                ->where('organization_id', $posting->organization_id)
                ->where('job_posting_id', $posting->getKey())
                ->where('user_id', $recruiter->getKey())
                ->delete();

            if ($deleted > 0) {
                $this->audit->handle(
                    AuditEvent::RecruiterUnassigned,
                    subject: $posting,
                    causer: $actor,
                    organization: $posting->organization_id,
                    properties: ['user_id' => $recruiter->getKey()],
                );
            }
        });
    }
}
