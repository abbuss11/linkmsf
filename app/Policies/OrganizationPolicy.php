<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->isSuperadmin() || $user->roleIn($organization) !== null;
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Platform-level edit (slug, limits): superadmin only.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $user->isSuperadmin();
    }

    /**
     * Organisation profile and career page settings.
     */
    public function updateProfile(User $user, Organization $organization): bool
    {
        return $user->isSuperadmin()
            || $user->hasPermissionIn($organization, Permission::UpdateOrganizationProfile);
    }

    public function changeStatus(User $user, Organization $organization): bool
    {
        return $user->isSuperadmin();
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }
}
