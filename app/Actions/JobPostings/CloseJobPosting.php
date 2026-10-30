<?php

namespace App\Actions\JobPostings;

use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Closes an offer, by hand or automatically at the deadline (actor null).
 */
class CloseJobPosting
{
    public function __construct(private readonly TransitionJobPosting $transition) {}

    public function handle(?User $actor, JobPosting $posting): JobPosting
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('changeStatus', $posting);
        }

        return DB::transaction(fn () => $this->transition->handle(
            $posting,
            JobPostingStatus::Closed,
            AuditEvent::JobPostingClosed,
            $actor,
            ['automatic' => $actor === null],
        ));
    }
}
