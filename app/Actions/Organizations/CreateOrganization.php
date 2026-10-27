<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Members\IssueInvitation;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Superadmin creates an active organisation, optionally inviting its first
 * orgadmin in the same transaction.
 */
class CreateOrganization
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly IssueInvitation $issueInvitation,
    ) {}

    /**
     * @param  array{name: string, slug: string, timezone?: string|null, country?: string|null, admin_email?: string|null}  $data
     */
    public function handle(User $actor, array $data): Organization
    {
        Gate::forUser($actor)->authorize('create', Organization::class);

        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => OrganizationSlug::rules(),
            'timezone' => ['nullable', 'timezone:all'],
            'country' => ['nullable', 'string', 'size:2'],
            'admin_email' => ['nullable', 'email:rfc', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($actor, $data) {
            $organization = new Organization([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'timezone' => $data['timezone'] ?? 'Africa/Niamey',
                'country' => $data['country'] ?? 'NE',
                'settings' => ['limits' => Organization::DEFAULT_LIMITS],
            ]);
            $organization->status = OrganizationStatus::Active;
            $organization->approved_at = now();
            $organization->setAttribute('approved_by', $actor->getKey());
            $organization->save();

            $this->audit->handle(
                AuditEvent::OrganizationCreated,
                subject: $organization,
                causer: $actor,
                organization: $organization,
                properties: ['name' => $organization->name, 'slug' => $organization->slug],
            );

            if (filled($data['admin_email'] ?? null)) {
                $this->issueInvitation->handle($organization, $data['admin_email'], OrganizationRole::OrgAdmin, $actor);
            }

            return $organization;
        });
    }
}
