<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;

class OrganizationMembershipPolicy
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionIn($this->context->id(), Permission::ViewMembers);
    }

    public function view(User $user, OrganizationMembership $membership): bool
    {
        return $user->hasPermissionIn($membership->organization_id, Permission::ViewMembers);
    }

    public function create(User $user): bool
    {
        // Members only join through an accepted invitation.
        return false;
    }

    /**
     * Change role or status. Nobody manages their own membership.
     */
    public function update(User $user, OrganizationMembership $membership): bool
    {
        return $membership->user_id !== $user->getKey()
            && $user->hasPermissionIn($membership->organization_id, Permission::ManageMembers);
    }

    public function delete(User $user, OrganizationMembership $membership): bool
    {
        return $this->update($user, $membership);
    }
}
