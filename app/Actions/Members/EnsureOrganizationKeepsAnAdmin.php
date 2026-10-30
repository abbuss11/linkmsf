<?php

namespace App\Actions\Members;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Exceptions\ActionFailed;
use App\Models\OrganizationMembership;

/**
 * An organisation must always keep at least one active orgadmin. Must be
 * called inside the transaction that changes the membership.
 */
class EnsureOrganizationKeepsAnAdmin
{
    public function handle(OrganizationMembership $membership): void
    {
        if ($membership->role !== OrganizationRole::OrgAdmin || $membership->status !== MembershipStatus::Active) {
            return;
        }

        $activeAdmins = OrganizationMembership::queryAcrossOrganizations()
            ->where('organization_id', $membership->organization_id)
            ->where('role', OrganizationRole::OrgAdmin->value)
            ->where('status', MembershipStatus::Active->value)
            ->lockForUpdate()
            ->count();

        if ($activeAdmins <= 1) {
            throw ActionFailed::because('members.errors.last_admin');
        }
    }
}
