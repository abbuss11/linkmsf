<?php

namespace App\Policies;

use App\Models\OrganizationRequest;
use App\Models\User;

/**
 * Requests are filed from the public site (no policy involved) and reviewed
 * by superadmins only.
 */
class OrganizationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function view(User $user, OrganizationRequest $request): bool
    {
        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function review(User $user, OrganizationRequest $request): bool
    {
        return $user->isSuperadmin() && $request->isPending();
    }

    public function update(User $user, OrganizationRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, OrganizationRequest $request): bool
    {
        return false;
    }
}
