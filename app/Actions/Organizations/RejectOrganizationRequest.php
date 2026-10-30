<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRequestStatus;
use App\Exceptions\ActionFailed;
use App\Models\OrganizationRequest;
use App\Models\User;
use App\Notifications\OrganizationRequestRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class RejectOrganizationRequest
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(User $actor, OrganizationRequest $request, string $reason): OrganizationRequest
    {
        Gate::forUser($actor)->authorize('review', $request);

        $reason = trim($reason);

        if ($reason === '') {
            throw ActionFailed::because('organization_requests.errors.reason_required');
        }

        $request = DB::transaction(function () use ($actor, $request, $reason) {
            /** @var OrganizationRequest $request */
            $request = OrganizationRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if (! $request->isPending()) {
                throw ActionFailed::because('organization_requests.errors.already_reviewed');
            }

            $request->status = OrganizationRequestStatus::Rejected;
            $request->reviewed_at = now();
            $request->setAttribute('reviewed_by', $actor->getKey());
            $request->rejection_reason = mb_substr($reason, 0, 2000);
            $request->save();

            $this->audit->handle(AuditEvent::OrganizationRequestRejected, subject: $request, causer: $actor);

            return $request;
        });

        DB::afterCommit(fn () => Notification::route('mail', $request->email)
            ->notify(new OrganizationRequestRejected($request->organization_name, $reason)));

        return $request;
    }
}
