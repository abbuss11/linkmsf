<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Platform-level settings, superadmin only: identity, slug and limits.
 */
class UpdateOrganization
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Organization $organization, array $data): Organization
    {
        Gate::forUser($actor)->authorize('update', $organization);

        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => OrganizationSlug::rules($organization),
            'timezone' => ['required', 'timezone:all'],
            'country' => ['required', 'string', 'size:2'],
            'listed_on_job_board' => ['boolean'],
            'limits.max_active_job_postings' => ['required', 'integer', 'min:0', 'max:10000'],
            'limits.storage_quota_mb' => ['required', 'integer', 'min:0', 'max:1048576'],
        ])->validate();

        return DB::transaction(function () use ($actor, $organization, $data) {
            $settings = $organization->settings ?? [];
            $settings['limits'] = [
                'max_active_job_postings' => (int) $data['limits']['max_active_job_postings'],
                'storage_quota_mb' => (int) $data['limits']['storage_quota_mb'],
            ];

            $organization->fill([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'timezone' => $data['timezone'],
                'country' => strtoupper($data['country']),
                'listed_on_job_board' => (bool) ($data['listed_on_job_board'] ?? $organization->listed_on_job_board),
                'settings' => $settings,
            ]);

            $changes = array_keys($organization->getDirty());
            $organization->save();

            $this->audit->handle(
                AuditEvent::OrganizationUpdated,
                subject: $organization,
                causer: $actor,
                organization: $organization,
                properties: ['changed' => $changes],
            );

            return $organization;
        });
    }
}
