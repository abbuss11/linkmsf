<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;

class InvitationPolicy
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionIn($this->context->id(), Permission::ManageInvitations);
    }

    public function view(User $user, Invitation $invitation): bool
    {
        return $user->hasPermissionIn($invitation->organization_id, Permission::ManageInvitations);
    }

    public function create(User $user, ?Organization $organization = null): bool
    {
        return $user->hasPermissionIn($organization ?? $this->context->id(), Permission::ManageInvitations);
    }

    public function resend(User $user, Invitation $invitation): bool
    {
        return $invitation->accepted_at === null
            && $invitation->revoked_at === null
            && $this->view($user, $invitation);
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $invitation->isPending() && $this->view($user, $invitation);
    }

    public function update(User $user, Invitation $invitation): bool
    {
        return false;
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return false;
    }
}
