<?php

use App\Actions\JobPostings\CreateJobPostingDraft;
use App\Actions\JobPostings\PublishJobPosting;
use App\Actions\JobPostings\SaveApplicationForm;
use App\Actions\JobPostings\UpdateApplicationSettings;
use App\Enums\OrganizationRole;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

// FULLTEXT indexes only see committed rows: these tests truncate instead.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Integration');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function makeOrganization(array $attributes = []): Organization
{
    return Organization::factory()->create($attributes);
}

/**
 * Creates an internal user who is an active member of the organisation.
 * Orgadmins get email MFA enabled so they pass the MFA requirement.
 */
function memberOf(Organization $organization, OrganizationRole $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    if ($role === OrganizationRole::OrgAdmin) {
        $user->has_email_authentication = true;
        $user->save();
    }

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return $user;
}

function makeSuperadmin(): User
{
    return User::factory()->superadmin()->withEmailAuthentication()->create();
}

function makeCandidate(array $attributes = []): User
{
    return User::factory()->candidate()->create($attributes);
}

/**
 * Prepares Filament and the organisation context the way the /app panel
 * middleware does, for Livewire component tests.
 */
function actingInOrganization(User $user, Organization $organization): User
{
    test()->actingAs($user);

    Filament::setCurrentPanel('app');
    Filament::setTenant($organization);
    Filament::bootCurrentPanel();

    app(OrganizationContext::class)->set($organization);

    return $user;
}

function actingOnPlatform(User $user): User
{
    test()->actingAs($user);

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    return $user;
}

/**
 * Runs code as an /app request for this organisation would: our context and
 * Filament's tenant both point to it (Filament attaches created records of
 * panel resources to its current tenant).
 */
function inOrganization(Organization $organization, Closure $callback): mixed
{
    $previousTenant = Filament::getTenant();
    Filament::setTenant($organization, isQuiet: true);

    try {
        return app(OrganizationContext::class)->runFor($organization, $callback);
    } finally {
        Filament::setTenant($previousTenant, isQuiet: true);
    }
}

/**
 * Builds an offer through the real Actions: draft, form, optional criteria.
 *
 * @param  list<array<string, mixed>>|null  $fields
 */
function draftPosting(User $actor, Organization $organization, array $details = [], ?array $fields = null): JobPosting
{
    return inOrganization($organization, function () use ($actor, $organization, $details, $fields) {
        $posting = app(CreateJobPostingDraft::class)->handle($actor, $organization, array_merge([
            'type' => 'employment',
            'title' => 'Chargé de suivi-évaluation',
            'closes_at' => now()->addDays(10)->toDateTimeString(),
        ], $details));

        app(SaveApplicationForm::class)->handle($actor, $posting, $fields ?? [
            ['type' => 'short_text', 'label' => 'Nom complet', 'required' => true, 'profile_field' => 'full_name'],
            ['type' => 'select', 'label' => 'Niveau d’études', 'required' => true, 'profile_field' => 'education_level'],
            ['type' => 'yes_no', 'label' => 'Disponible immédiatement ?', 'required' => true],
        ]);

        return $posting->fresh();
    });
}

/**
 * A published offer, open to applications, built through the real Actions.
 *
 * @param  list<array<string, mixed>>  $documents  requested documents (§3.1 step 5)
 * @param  array<string, mixed>  $settings
 */
function openPosting(User $actor, Organization $organization, array $details = [], array $documents = [], array $settings = [], ?array $fields = null): JobPosting
{
    $posting = draftPosting($actor, $organization, $details, $fields);

    return inOrganization($organization, function () use ($actor, $posting, $documents, $settings) {
        app(UpdateApplicationSettings::class)->handle($actor, $posting, array_merge([
            'edit_after_submit' => 'never',
            'duplicate_policy' => 'single',
            'notify_new_applications' => 'none',
            'documents' => $documents,
        ], $settings));

        app(PublishJobPosting::class)->handle($actor, $posting->fresh());

        return $posting->fresh();
    });
}

/**
 * Minimal but real PDF bytes: detected as application/pdf from content.
 */
function pdfBytes(int $padding = 0): string
{
    return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n".str_repeat('%', $padding)."\ntrailer << /Root 1 0 R >>\n%%EOF\n";
}

/**
 * Sends a whole file through the chunked upload endpoints, as the browser does.
 */
function uploadInChunks(Illuminate\Foundation\Testing\TestCase $test, string $applicationUlid, string $field, string $name, string $bytes, int $chunkSize = 512 * 1024): TestResponse
{
    $init = $test->postJson(route('candidate.applications.documents.init', $applicationUlid), [
        'field' => $field,
        'name' => $name,
        'size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes),
    ]);

    if ($init->status() !== 200) {
        return $init;
    }

    $response = $init;
    for ($offset = 0; $offset < strlen($bytes); $offset += $chunkSize) {
        $response = $test->call('PUT', $init->json('chunk_url'), [], [], [], [
            'HTTP_X_UPLOAD_OFFSET' => (string) $offset,
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_ACCEPT' => 'application/json',
        ], substr($bytes, $offset, $chunkSize));

        if ($response->status() !== 200) {
            return $response;
        }
    }

    return $response;
}
