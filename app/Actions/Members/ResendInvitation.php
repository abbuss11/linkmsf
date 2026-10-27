<?php

namespace App\Actions\Members;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Exceptions\ActionFailed;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\MemberInvited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Issues a new token and a new expiry date: the previous link stops working.
 */
class ResendInvitation
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, Invitation $invitation): Invitation
    {
        Gate::forUser($actor)->authorize('resend', $invitation);

        $organization = $invitation->organization()->firstOrFail();

        if (! $organization->isActive()) {
            throw ActionFailed::because('invitations.errors.organization_inactive');
        }

        $plainToken = Str::random(64);

        DB::transaction(function () use ($actor, $invitation, $plainToken, $organization): void {
            $invitation->forceFill([
                'token_hash' => Invitation::hashToken($plainToken),
                'expires_at' => now()->addDays((int) config('link.invitations.validity_days')),
            ])->save();

            $this->audit->handle(
                AuditEvent::InvitationResent,
                subject: $invitation,
                causer: $actor,
                organization: $organization,
                properties: ['email' => $invitation->email],
            );
        });

        DB::afterCommit(fn () => Notification::route('mail', $invitation->email)->notify(
            new MemberInvited($invitation, $plainToken, $organization->name, $actor->name),
        ));

        return $invitation;
    }
}
