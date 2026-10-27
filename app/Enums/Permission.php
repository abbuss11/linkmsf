<?php

namespace App\Enums;

/**
 * Permissions granted inside an organisation. Each OrganizationRole maps to a
 * fixed set of permissions; policies check permissions, never role names.
 */
enum Permission: string
{
    case UpdateOrganizationProfile = 'organization.update';
    case ViewMembers = 'members.view';
    case ManageMembers = 'members.manage';
    case ManageInvitations = 'invitations.manage';
    case ViewAuditLog = 'audit.view';
}
