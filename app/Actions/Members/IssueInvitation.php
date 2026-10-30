<?php

namespace App\Actions\Members;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Exceptions\ActionFailed;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\MemberInvited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Creates an invitation and emails it. Internal building block: callers are
 * responsible for authorisation (InviteMember, ApproveOrganizationRequest).
 */
class IssueInvitation
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(Organization $organization, string $email, OrganizationRole $role, ?User $inviter): Invitation
    {
        $email = Str::lower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ActionFailed::because('invitations.errors.invalid_email');
        }

        $plainToken = Str::random(64);

        $invitation = DB::transaction(function () use ($organization, $email, $role, $inviter, $plainToken) {
            // Serialise concurrent invitations to the same organisation.
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            $this->ensureCanBeInvited($organization, $email);

            $invitation = new Invitation([
                'email' => $email,
                'role' => $role,
                'token_hash' => Invitation::hashToken($plainToken),
                'invited_by' => $inviter?->getKey(),
                'expires_at' => now()->addDays((int) config('link.invitations.validity_days')),
            ]);
            $invitation->organization_id = $organization->getKey();
            $invitation->save();

            $this->audit->handle(
                AuditEvent::InvitationSent,
                subject: $invitation,
                causer: $inviter,
                organization: $organization,
                properties: ['email' => $email, 'role' => $role->value],
            );

            return $invitation;
        });

        DB::afterCommit(fn () => Notification::route('mail', $email)->notify(
            new MemberInvited($invitation, $plainToken, $organization->name, $inviter?->name),
        ));

        return $invitation;
    }

    private function ensureCanBeInvited(Organization $organization, string $email): void
    {
        if (! $organization->isActive()) {
            throw ActionFailed::because('invitations.errors.organization_inactive');
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser?->isCandidate()) {
            throw ActionFailed::because('invitations.errors.email_is_candidate');
        }

        if ($existingUser !== null && OrganizationMembership::queryForOrganization($organization)
            ->where('user_id', $existingUser->getKey())
            ->exists()) {
            throw ActionFailed::because('invitations.errors.already_member');
        }

        if (Invitation::queryForOrganization($organization)->pending()->where('email', $email)->exists()) {
            throw ActionFailed::because('invitations.errors.already_invited');
        }
    }
}
