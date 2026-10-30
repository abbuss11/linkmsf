<?php

namespace App\Actions\Members;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChangeMemberRole
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly EnsureOrganizationKeepsAnAdmin $ensureAdmin,
    ) {}

    public function handle(User $actor, OrganizationMembership $membership, OrganizationRole $role): OrganizationMembership
    {
        Gate::forUser($actor)->authorize('update', $membership);

        if ($membership->role === $role) {
            return $membership;
        }

        return DB::transaction(function () use ($actor, $membership, $role) {
            if ($role !== OrganizationRole::OrgAdmin) {
                $this->ensureAdmin->handle($membership);
            }

            $previous = $membership->role;
            $membership->role = $role;
            $membership->save();

            $this->audit->handle(
                AuditEvent::MemberRoleChanged,
                subject: $membership,
                causer: $actor,
                organization: $membership->organization_id,
                properties: ['from' => $previous->value, 'to' => $role->value, 'user_id' => $membership->user_id],
            );

            return $membership;
        });
    }
}
