<?php

namespace App\Actions\JobPostings;

use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Exceptions\ActionFailed;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApproveJobPosting
{
    public function __construct(
        private readonly CheckJobPostingReadiness $readiness,
        private readonly PublishFormVersion $publishFormVersion,
        private readonly TransitionJobPosting $transition,
    ) {}

    public function handle(User $actor, JobPosting $posting): JobPosting
    {
        Gate::forUser($actor)->authorize('approve', $posting);

        $report = $this->readiness->handle($posting);

        if (! $report->isReady()) {
            throw ActionFailed::because('job_postings.errors.not_ready', ['problems' => implode(' ', $report->blocking)]);
        }

        return DB::transaction(function () use ($actor, $posting) {
            $version = $this->publishFormVersion->handle($posting, $actor);
            $this->transition->handle($posting, JobPostingStatus::Published, AuditEvent::JobPostingApproved, $actor, [
                'form_version' => $version->version,
            ]);

            return $posting;
        });
    }
}
