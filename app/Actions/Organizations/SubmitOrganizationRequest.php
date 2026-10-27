<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRequestStatus;
use App\Exceptions\ActionFailed;
use App\Models\OrganizationRequest;
use App\Models\User;
use App\Notifications\NewOrganizationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Public request for an organisation space (§12: "sur demande, validée par
 * le superadmin"). Input is validated by the calling FormRequest.
 */
class SubmitOrganizationRequest
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array{organization_name: string, contact_name: string, email: string, phone?: string|null, country?: string|null, message?: string|null}  $data
     */
    public function handle(array $data): OrganizationRequest
    {
        $email = Str::lower(trim($data['email']));

        $alreadyPending = OrganizationRequest::query()
            ->where('email', $email)
            ->where('status', OrganizationRequestStatus::Pending->value)
            ->exists();

        if ($alreadyPending) {
            throw ActionFailed::because('organization_requests.errors.already_pending');
        }

        $request = DB::transaction(function () use ($data, $email) {
            $request = OrganizationRequest::create([
                'organization_name' => trim($data['organization_name']),
                'contact_name' => trim($data['contact_name']),
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? 'NE',
                'message' => $data['message'] ?? null,
                'status' => OrganizationRequestStatus::Pending,
            ]);

            $this->audit->handle(
                AuditEvent::OrganizationRequestSubmitted,
                subject: $request,
                properties: ['organization_name' => $request->organization_name],
            );

            return $request;
        });

        DB::afterCommit(function () use ($request): void {
            $superadmins = User::query()->where('is_superadmin', true)->get();

            if ($superadmins->isNotEmpty()) {
                Notification::send($superadmins, new NewOrganizationRequest($request));
            }
        });

        return $request;
    }
}
