<?php

namespace App\Actions\JobPostings;

use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SuspendJobPosting
{
    public function __construct(private readonly TransitionJobPosting $transition) {}

    public function handle(User $actor, JobPosting $posting): JobPosting
    {
        Gate::forUser($actor)->authorize('changeStatus', $posting);

        return DB::transaction(fn () => $this->transition->handle($posting, JobPostingStatus::Suspended, AuditEvent::JobPostingSuspended, $actor));
    }
}
