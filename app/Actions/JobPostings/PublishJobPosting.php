<?php

namespace App\Actions\JobPostings;

use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Enums\Permission;
use App\Exceptions\ActionFailed;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\JobPostingSubmittedForApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Wizard step 5: publishes a draft, or submits it for approval when the
 * organisation requires it and the actor cannot approve (§3.2, §12).
 */
class PublishJobPosting
{
    public function __construct(
        private readonly CheckJobPostingReadiness $readiness,
        private readonly PublishFormVersion $publishFormVersion,
        private readonly TransitionJobPosting $transition,
    ) {}

    public function handle(User $actor, JobPosting $posting): JobPosting
    {
        Gate::forUser($actor)->authorize('publish', $posting);

        $organization = Organization::query()->findOrFail($posting->organization_id);
        $needsApproval = $organization->requiresJobPostingApproval()
            && ! $actor->hasPermissionIn($organization, Permission::ApproveJobPostings);

        $report = $this->readiness->handle($posting, checkActiveLimit: ! $needsApproval);

        if (! $report->isReady()) {
            throw ActionFailed::because('job_postings.errors.not_ready', ['problems' => implode(' ', $report->blocking)]);
        }

        if ($needsApproval) {
            DB::transaction(fn () => $this->transition->handle($posting, JobPostingStatus::PendingApproval, AuditEvent::JobPostingSubmitted, $actor));

            DB::afterCommit(function () use ($organization, $posting, $actor): void {
                Notification::send($organization->activeAdmins(), new JobPostingSubmittedForApproval($posting->title, $actor->name, $organization->slug, $posting->ulid));
            });

            return $posting;
        }

        return DB::transaction(function () use ($actor, $posting) {
            $version = $this->publishFormVersion->handle($posting, $actor);

            return $this->transition->handle($posting, JobPostingStatus::Published, AuditEvent::JobPostingPublished, $actor, [
                'form_version' => $version->version,
            ]);
        });
    }
}
