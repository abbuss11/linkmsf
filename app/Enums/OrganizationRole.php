<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrganizationRole: string implements HasColor, HasLabel
{
    case OrgAdmin = 'orgadmin';
    case Recruiter = 'recruiter';
    case Jury = 'jury';

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::OrgAdmin => [
                Permission::UpdateOrganizationProfile,
                Permission::ViewMembers,
                Permission::ManageMembers,
                Permission::ManageInvitations,
                Permission::ViewAuditLog,
                Permission::ManageJobPostings,
                Permission::ApproveJobPostings,
                Permission::AssignRecruiters,
                Permission::ConfigureJobPostings,
                Permission::ProcessApplications,
                Permission::ExportApplications,
                Permission::ExportApplicationDocuments,
            ],
            // Exporting documents can be opened to recruiters by the
            // organisation (see Organization::recruitersCanExportDocuments).
            self::Recruiter => [
                Permission::ManageJobPostings,
                Permission::ProcessApplications,
                Permission::ExportApplications,
            ],
            self::Jury => [],
        };
    }

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Roles whose holders must enable multi-factor authentication (§10.1).
     */
    public function requiresMultiFactorAuthentication(): bool
    {
        return $this === self::OrgAdmin;
    }

    public function getLabel(): string
    {
        return __('enums.organization_role.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OrgAdmin => 'danger',
            self::Recruiter => 'primary',
            self::Jury => 'gray',
        };
    }
}
