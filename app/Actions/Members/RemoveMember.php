<?php

namespace App\Actions\Members;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Removes the membership only; the user account stays (it may belong to
 * other organisations).
 */
class RemoveMember
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly EnsureOrganizationKeepsAnAdmin $ensureAdmin,
    ) {}

    public function handle(User $actor, OrganizationMembership $membership): void
    {
        Gate::forUser($actor)->authorize('delete', $membership);

        DB::transaction(function () use ($actor, $membership): void {
            $this->ensureAdmin->handle($membership);

            $membership->delete();

            $this->audit->handle(
                AuditEvent::MemberRemoved,
                subject: $membership,
                causer: $actor,
                organization: $membership->organization_id,
                properties: ['user_id' => $membership->user_id, 'role' => $membership->role->value],
            );
        });
    }
}
