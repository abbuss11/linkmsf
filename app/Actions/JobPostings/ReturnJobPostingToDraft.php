<?php

namespace App\Actions\JobPostings;

use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Exceptions\ActionFailed;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\JobPostingReturnedToDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * An orgadmin sends a submitted offer back to its author, with a reason.
 */
class ReturnJobPostingToDraft
{
    public function __construct(private readonly TransitionJobPosting $transition) {}

    public function handle(User $actor, JobPosting $posting, string $reason): JobPosting
    {
        Gate::forUser($actor)->authorize('approve', $posting);

        $reason = trim($reason);

        if ($reason === '') {
            throw ActionFailed::because('job_postings.errors.reason_required');
        }

        DB::transaction(fn () => $this->transition->handle(
            $posting,
            JobPostingStatus::Draft,
            AuditEvent::JobPostingReturnedToDraft,
            $actor,
            ['reason' => mb_substr($reason, 0, 2000)],
        ));

        $creator = $posting->creator()->first();
        $organization = Organization::query()->findOrFail($posting->organization_id);

        if ($creator !== null && ! $creator->is($actor)) {
            DB::afterCommit(fn () => $creator->notify(new JobPostingReturnedToDraft($posting->title, $reason, $organization->slug, $posting->ulid)));
        }

        return $posting;
    }
}
