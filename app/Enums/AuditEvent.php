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

    // Job postings
    case JobPostingCreated = 'job_posting.created';
    case JobPostingUpdated = 'job_posting.updated';
    case JobRequirementsUpdated = 'job_posting.requirements_updated';
    case ApplicationFormUpdated = 'job_posting.form_updated';
    case ApplicationFormVersionPublished = 'job_posting.form_version_published';
    case JobPostingSubmitted = 'job_posting.submitted';
    case JobPostingApproved = 'job_posting.approved';
    case JobPostingReturnedToDraft = 'job_posting.returned_to_draft';
    case JobPostingPublished = 'job_posting.published';
    case JobPostingSuspended = 'job_posting.suspended';
    case JobPostingResumed = 'job_posting.resumed';
    case JobPostingClosed = 'job_posting.closed';
    case JobPostingReopened = 'job_posting.reopened';
    case JobPostingArchived = 'job_posting.archived';
    case JobPostingDuplicated = 'job_posting.duplicated';
    case RecruiterAssigned = 'job_posting.recruiter_assigned';
    case RecruiterUnassigned = 'job_posting.recruiter_unassigned';

    // Applications
    case ApplicationSubmitted = 'application.submitted';
    case ApplicationUpdated = 'application.updated';
    case ApplicationWithdrawn = 'application.withdrawn';
    case ApplicationMoved = 'application.moved';
    case ApplicationReinstated = 'application.reinstated';
    case ApplicationEligibilityEvaluated = 'application.eligibility_evaluated';
    case ShortlistCreated = 'shortlist.created';
    case ShortlistItemAdded = 'shortlist.item_added';
    case ShortlistItemRemoved = 'shortlist.item_removed';
    case ShortlistReordered = 'shortlist.reordered';
    case PipelineUpdated = 'pipeline.updated';
    case ExportRequested = 'export.requested';
    case ExportDownloaded = 'export.downloaded';

    public function getLabel(): string
    {
        return __('enums.audit_event.'.str_replace('.', '_', $this->value));
    }
}
