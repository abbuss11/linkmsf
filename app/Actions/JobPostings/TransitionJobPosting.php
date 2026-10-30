<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Exceptions\ActionFailed;
use App\Jobs\GenerateShareImage;
use App\Models\JobPosting;
use App\Models\User;

/**
 * Applies one status change after checking it is allowed by the lifecycle
 * (§3.2). Internal building block: callers authorise and open the transaction.
 */
class TransitionJobPosting
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(JobPosting $posting, JobPostingStatus $target, AuditEvent $event, ?User $actor, array $properties = []): JobPosting
    {
        /** @var JobPosting $locked */
        $locked = JobPosting::queryAcrossOrganizations()->lockForUpdate()->findOrFail($posting->getKey());

        if (! $locked->status->canTransitionTo($target)) {
            throw ActionFailed::because('job_postings.errors.transition_not_allowed', [
                'from' => $locked->status->getLabel(),
                'to' => $target->getLabel(),
            ]);
        }

        $previous = $locked->status;
        $posting->status = $target;

        if ($target === JobPostingStatus::Published) {
            $posting->published_at ??= now();
            $posting->closed_at = null;
        }

        if ($target === JobPostingStatus::Closed) {
            $posting->closed_at = now();
        }

        $posting->save();

        if ($target === JobPostingStatus::Published) {
            GenerateShareImage::dispatch($posting)->afterCommit();
        }

        $this->audit->handle(
            $event,
            subject: $posting,
            causer: $actor,
            organization: $posting->organization_id,
            properties: ['from' => $previous->value, 'to' => $target->value, ...$properties],
        );

        return $posting;
    }
}
