<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;

class AuditLogPolicy
{
    public function __construct(private readonly OrganizationContext $context) {}

    /**
     * Inside an organisation (context set): orgadmins of that organisation.
     * On the platform panel (no context): superadmins.
     */
    public function viewAny(User $user): bool
    {
        if ($this->context->has()) {
            return $user->hasPermissionIn($this->context->id(), Permission::ViewAuditLog);
        }

        return $user->isSuperadmin();
    }

    public function view(User $user, AuditLog $log): bool
    {
        if ($this->context->has()) {
            return $log->organization_id === $this->context->id()
                && $user->hasPermissionIn($log->organization_id, Permission::ViewAuditLog);
        }

        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
