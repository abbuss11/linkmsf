<?php

namespace App\Actions\JobPostings;

use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Exceptions\ActionFailed;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Reopens a closed offer: reason and a new future deadline are mandatory,
 * and the reason is kept in the audit log (§3.2).
 */
class ReopenJobPosting
{
    public function __construct(private readonly TransitionJobPosting $transition) {}

    public function handle(User $actor, JobPosting $posting, string $reason, CarbonInterface $closesAt): JobPosting
    {
        Gate::forUser($actor)->authorize('changeStatus', $posting);

        $reason = trim($reason);

        if ($reason === '') {
            throw ActionFailed::because('job_postings.errors.reason_required');
        }

        if ($closesAt->isPast()) {
            throw ActionFailed::because('job_postings.readiness.closing_date_past');
        }

        $organization = Organization::query()->findOrFail($posting->organization_id);
        $active = JobPosting::queryForOrganization($organization)
            ->whereIn('status', [JobPostingStatus::Published->value, JobPostingStatus::Suspended->value])
            ->count();

        if ($active >= $organization->limit('max_active_job_postings')) {
            throw ActionFailed::because('job_postings.readiness.active_limit', ['max' => $organization->limit('max_active_job_postings')]);
        }

        return DB::transaction(function () use ($actor, $posting, $reason, $closesAt) {
            $previousDeadline = $posting->closes_at?->toIso8601String();
            $posting->closes_at = Carbon::instance($closesAt);

            return $this->transition->handle($posting, JobPostingStatus::Published, AuditEvent::JobPostingReopened, $actor, [
                'reason' => mb_substr($reason, 0, 2000),
                'previous_closes_at' => $previousDeadline,
                'closes_at' => $closesAt->toIso8601String(),
            ]);
        });
    }
}
