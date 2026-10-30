<?php

namespace App\Actions\Members;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\MembershipStatus;
use App\Enums\UserType;
use App\Exceptions\ActionFailed;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\InvitationAccepted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Turns a valid invitation into a membership. Either the invited person is
 * already logged in with the invited email, or a new internal account is
 * created; an existing account must log in first.
 */
class AcceptInvitation
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array{name: string, password: string}|null  $newAccount
     */
    public function handle(Invitation $invitation, string $plainToken, ?User $currentUser, ?array $newAccount = null): User
    {
        [$user, $organization] = DB::transaction(function () use ($invitation, $plainToken, $currentUser, $newAccount) {
            /** @var Invitation $invitation */
            $invitation = Invitation::queryAcrossOrganizations()->lockForUpdate()->findOrFail($invitation->getKey());
            $organization = Organization::query()->findOrFail($invitation->organization_id);

            if (! $invitation->matchesToken($plainToken) || ! $invitation->isPending()) {
                throw ActionFailed::because('invitations.errors.invalid_or_expired');
            }

            if (! $organization->isActive()) {
                throw ActionFailed::because('invitations.errors.organization_inactive');
            }

            $user = $this->resolveUser($invitation, $currentUser, $newAccount);

            if (OrganizationMembership::queryForOrganization($organization)->where('user_id', $user->getKey())->exists()) {
                throw ActionFailed::because('invitations.errors.already_member');
            }

            $membership = new OrganizationMembership([
                'user_id' => $user->getKey(),
                'role' => $invitation->role,
                'status' => MembershipStatus::Active,
                'invited_by' => $invitation->invited_by,
                'joined_at' => now(),
            ]);
            $membership->organization_id = $organization->getKey();
            $membership->save();

            $invitation->forceFill(['accepted_at' => now(), 'accepted_by' => $user->getKey()])->save();

            $this->audit->handle(
                AuditEvent::InvitationAccepted,
                subject: $membership,
                causer: $user,
                organization: $organization,
                properties: ['role' => $invitation->role->value],
            );

            return [$user, $organization];
        });

        $roleLabel = $invitation->role->getLabel();

        DB::afterCommit(function () use ($user, $organization, $roleLabel): void {
            $admins = $organization->activeAdmins()->reject(fn (User $admin) => $admin->is($user));

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new InvitationAccepted($user->name, $roleLabel, $organization->name));
            }
        });

        return $user;
    }

    /**
     * @param  array{name: string, password: string}|null  $newAccount
     */
    private function resolveUser(Invitation $invitation, ?User $currentUser, ?array $newAccount): User
    {
        if ($currentUser !== null) {
            if (! $currentUser->isInternal()) {
                throw ActionFailed::because('invitations.errors.email_is_candidate');
            }

            if (strcasecmp($currentUser->email, $invitation->email) !== 0) {
                throw ActionFailed::because('invitations.errors.wrong_account');
            }

            return $currentUser;
        }

        $existing = User::query()->where('email', $invitation->email)->first();

        if ($existing !== null) {
            throw ActionFailed::because($existing->isCandidate()
                ? 'invitations.errors.email_is_candidate'
                : 'invitations.errors.login_required');
        }

        if ($newAccount === null) {
            throw ActionFailed::because('invitations.errors.login_required');
        }

        $user = new User([
            'name' => $newAccount['name'],
            'email' => $invitation->email,
            'password' => $newAccount['password'],
            'type' => UserType::Internal,
        ]);
        // Receiving the invitation proves the person controls this address.
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
