<?php

namespace App\Actions\Members;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevokeInvitation
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, Invitation $invitation): Invitation
    {
        Gate::forUser($actor)->authorize('revoke', $invitation);

        return DB::transaction(function () use ($actor, $invitation) {
            $invitation->forceFill(['revoked_at' => now()])->save();

            $this->audit->handle(
                AuditEvent::InvitationRevoked,
                subject: $invitation,
                causer: $actor,
                organization: $invitation->organization_id,
                properties: ['email' => $invitation->email],
            );

            return $invitation;
        });
    }
}
