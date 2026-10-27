<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AuditEvent: string implements HasLabel
{
    // Authentication
    case LoginSucceeded = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case PasswordReset = 'auth.password_reset';
    case CandidateRegistered = 'auth.candidate_registered';

    // Platform
    case OrganizationRequestSubmitted = 'organization_request.submitted';
    case OrganizationRequestApproved = 'organization_request.approved';
    case OrganizationRequestRejected = 'organization_request.rejected';
    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';
    case OrganizationSuspended = 'organization.suspended';
    case OrganizationActivated = 'organization.activated';

    // Members
    case InvitationSent = 'invitation.sent';
    case InvitationResent = 'invitation.resent';
    case InvitationRevoked = 'invitation.revoked';
    case InvitationAccepted = 'invitation.accepted';
    case MemberRoleChanged = 'member.role_changed';
    case MemberSuspended = 'member.suspended';
    case MemberReactivated = 'member.reactivated';
    case MemberRemoved = 'member.removed';

    public function getLabel(): string
    {
        return __('enums.audit_event.'.str_replace('.', '_', $this->value));
    }
}
