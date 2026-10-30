<?php

namespace App\Policies;

use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Enums\Permission;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;

/**
 * Offers (§2.1): orgadmins see and manage all offers; recruiters see every
 * offer, or only those assigned to them when the organisation chooses so;
 * jury members have no access to offer management.
 */
class JobPostingPolicy
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionIn($this->context->id(), Permission::ManageJobPostings);
    }

    public function view(User $user, JobPosting $posting): bool
    {
        $role = $user->roleIn($posting->organization_id);

        if ($role === null || ! $role->can(Permission::ManageJobPostings)) {
            return false;
        }

        if ($role === OrganizationRole::OrgAdmin) {
            return true;
        }

        $organization = Organization::query()->find($posting->organization_id);

        return $organization !== null
            && ($organization->recruitersSeeAllJobPostings() || $posting->isAssignedTo($user));
    }

    public function create(User $user, ?Organization $organization = null): bool
    {
        return $user->hasPermissionIn($organization ?? $this->context->id(), Permission::ManageJobPostings);
    }

    /**
     * Details, criteria, settings and form.
     */
    public function update(User $user, JobPosting $posting): bool
    {
        return $posting->status->isEditable() && $this->view($user, $posting);
    }

    public function publish(User $user, JobPosting $posting): bool
    {
        return $posting->status === JobPostingStatus::Draft && $this->view($user, $posting);
    }

    public function approve(User $user, JobPosting $posting): bool
    {
        return $posting->status === JobPostingStatus::PendingApproval
            && $user->hasPermissionIn($posting->organization_id, Permission::ApproveJobPostings);
    }

    public function changeStatus(User $user, JobPosting $posting): bool
    {
        return $this->view($user, $posting);
    }

    public function duplicate(User $user, JobPosting $posting): bool
    {
        return $this->view($user, $posting);
    }

    public function assign(User $user, JobPosting $posting): bool
    {
        return $user->hasPermissionIn($posting->organization_id, Permission::AssignRecruiters);
    }

    public function delete(User $user, JobPosting $posting): bool
    {
        // Offers are archived, never deleted (history and future applications).
        return false;
    }
}
