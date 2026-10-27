<?php

namespace App\Actions\Members;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\MembershipStatus;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Suspends or reactivates a member. A suspended member keeps their account
 * but can no longer enter the organisation.
 */
class ChangeMemberStatus
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly EnsureOrganizationKeepsAnAdmin $ensureAdmin,
    ) {}

    public function handle(User $actor, OrganizationMembership $membership, MembershipStatus $status): OrganizationMembership
    {
        Gate::forUser($actor)->authorize('update', $membership);

        if ($membership->status === $status) {
            return $membership;
        }

        return DB::transaction(function () use ($actor, $membership, $status) {
            if ($status === MembershipStatus::Suspended) {
                $this->ensureAdmin->handle($membership);
            }

            $membership->status = $status;
            $membership->save();

            $this->audit->handle(
                $status === MembershipStatus::Suspended ? AuditEvent::MemberSuspended : AuditEvent::MemberReactivated,
                subject: $membership,
                causer: $actor,
                organization: $membership->organization_id,
                properties: ['user_id' => $membership->user_id],
            );

            return $membership;
        });
    }
}
