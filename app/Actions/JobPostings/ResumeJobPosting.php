<?php

namespace App\Actions\JobPostings;

use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Exceptions\ActionFailed;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Suspended → published. Refused once the deadline has passed: the offer
 * must then be reopened with a new date and a reason.
 */
class ResumeJobPosting
{
    public function __construct(private readonly TransitionJobPosting $transition) {}

    public function handle(User $actor, JobPosting $posting): JobPosting
    {
        Gate::forUser($actor)->authorize('changeStatus', $posting);

        if ($posting->status !== JobPostingStatus::Suspended) {
            throw ActionFailed::because('job_postings.errors.transition_not_allowed', [
                'from' => $posting->status->getLabel(),
                'to' => JobPostingStatus::Published->getLabel(),
            ]);
        }

        if ($posting->closes_at === null || $posting->closes_at->isPast()) {
            throw ActionFailed::because('job_postings.readiness.closing_date_past');
        }

        return DB::transaction(fn () => $this->transition->handle($posting, JobPostingStatus::Published, AuditEvent::JobPostingResumed, $actor));
    }
}
