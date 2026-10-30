<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use App\Support\HtmlSanitizer;
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
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

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
            'about' => ['nullable', 'string', 'max:20000'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255', 'regex:/^organization-logos\/[A-Za-z0-9\-]+\.(png|jpg|jpeg|webp)$/'],
            'job_postings_requires_approval' => ['sometimes', 'boolean'],
            'job_postings_recruiters_see_all' => ['sometimes', 'boolean'],
        ])->validate();

        $configuresJobPostings = array_key_exists('job_postings_requires_approval', $data)
            || array_key_exists('job_postings_recruiters_see_all', $data);

        if ($configuresJobPostings) {
            Gate::forUser($actor)->authorize('configureJobPostings', $organization);
        }

        return DB::transaction(function () use ($actor, $organization, $data, $configuresJobPostings) {
            $previousLogo = $organization->logo_path;

            if ($configuresJobPostings) {
                $settings = $organization->settings ?? [];
                $settings['job_postings'] = [
                    'requires_approval' => (bool) ($data['job_postings_requires_approval'] ?? $organization->requiresJobPostingApproval()),
                    'recruiters_see_all' => (bool) ($data['job_postings_recruiters_see_all'] ?? $organization->recruitersSeeAllJobPostings()),
                ];
                $organization->settings = $settings;
            }

            $organization->fill([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'primary_color' => $data['primary_color'] ?? null,
                'secondary_color' => $data['secondary_color'] ?? null,
                'listed_on_job_board' => (bool) ($data['listed_on_job_board'] ?? false),
                'logo_path' => $data['logo_path'] ?? null,
                'about' => $this->sanitizer->clean($data['about'] ?? null),
                'website' => $data['website'] ?? null,
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
