<?php

namespace App\Actions\Applications;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ApplicationStatus;
use App\Enums\AuditEvent;
use App\Enums\PublicApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The candidate withdraws a submitted application (§4.5). The decision
 * workflow (phase 5) will forbid it once a decision is taken.
 */
class WithdrawApplication
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $candidate, Application $application): Application
    {
        Gate::forUser($candidate)->authorize('withdraw', $application);

        return DB::transaction(function () use ($candidate, $application) {
            $application->status = ApplicationStatus::Withdrawn;
            $application->public_status = PublicApplicationStatus::Withdrawn;
            $application->withdrawn_at = now();
            $application->save();

            $this->audit->handle(
                AuditEvent::ApplicationWithdrawn,
                subject: $application,
                causer: $candidate,
                organization: $application->organization_id,
                properties: ['reference' => $application->reference],
            );

            return $application;
        });
    }
}
