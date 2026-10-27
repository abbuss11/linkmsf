<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Profile and career page settings, editable by the orgadmin. The slug and
 * limits are not editable here.
 */
class UpdateOrganizationProfile
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Organization $organization, array $data): Organization
    {
        Gate::forUser($actor)->authorize('updateProfile', $organization);

        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'listed_on_job_board' => ['boolean'],
            'logo_path' => ['nullable', 'string', 'max:255', 'regex:/^organization-logos\/[A-Za-z0-9\-]+\.(png|jpg|jpeg|webp)$/'],
        ])->validate();

        return DB::transaction(function () use ($actor, $organization, $data) {
            $previousLogo = $organization->logo_path;

            $organization->fill([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'primary_color' => $data['primary_color'] ?? null,
                'secondary_color' => $data['secondary_color'] ?? null,
                'listed_on_job_board' => (bool) ($data['listed_on_job_board'] ?? false),
                'logo_path' => $data['logo_path'] ?? null,
            ]);

            $changes = array_keys($organization->getDirty());
            $organization->save();

            if ($previousLogo !== null && $previousLogo !== $organization->logo_path) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($previousLogo));
            }

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
