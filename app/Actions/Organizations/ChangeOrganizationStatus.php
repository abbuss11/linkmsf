<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Activate (validate) or suspend an organisation. A suspended organisation
 * is closed to all its members; its data is kept.
 */
class ChangeOrganizationStatus
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, Organization $organization, OrganizationStatus $status): Organization
    {
        Gate::forUser($actor)->authorize('changeStatus', $organization);

        if ($organization->status === $status) {
            return $organization;
        }

        return DB::transaction(function () use ($actor, $organization, $status) {
            $previous = $organization->status;
            $organization->status = $status;

            if ($status === OrganizationStatus::Active && $organization->approved_at === null) {
                $organization->approved_at = now();
                $organization->setAttribute('approved_by', $actor->getKey());
            }

            $organization->save();

            $this->audit->handle(
                $status === OrganizationStatus::Suspended ? AuditEvent::OrganizationSuspended : AuditEvent::OrganizationActivated,
                subject: $organization,
                causer: $actor,
                organization: $organization,
                properties: ['from' => $previous->value, 'to' => $status->value],
            );

            return $organization;
        });
    }
}
