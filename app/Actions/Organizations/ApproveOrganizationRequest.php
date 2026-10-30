<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRequestStatus;
use App\Exceptions\ActionFailed;
use App\Models\Organization;
use App\Models\OrganizationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Creates the organisation and invites the requester as its orgadmin, all or
 * nothing.
 */
class ApproveOrganizationRequest
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly CreateOrganization $createOrganization,
    ) {}

    public function handle(User $actor, OrganizationRequest $request, string $slug): Organization
    {
        Gate::forUser($actor)->authorize('review', $request);

        return DB::transaction(function () use ($actor, $request, $slug) {
            /** @var OrganizationRequest $request */
            $request = OrganizationRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if (! $request->isPending()) {
                throw ActionFailed::because('organization_requests.errors.already_reviewed');
            }

            $organization = $this->createOrganization->handle($actor, [
                'name' => $request->organization_name,
                'slug' => $slug,
                'country' => $request->country,
                'admin_email' => $request->email,
            ]);

            $request->status = OrganizationRequestStatus::Approved;
            $request->reviewed_at = now();
            $request->setAttribute('reviewed_by', $actor->getKey());
            $request->organization_id = $organization->getKey();
            $request->save();

            $this->audit->handle(
                AuditEvent::OrganizationRequestApproved,
                subject: $request,
                causer: $actor,
                organization: $organization,
            );

            return $organization;
        });
    }
}
