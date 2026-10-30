<?php

use App\Actions\JobPostings\CreateJobPostingDraft;
use App\Actions\JobPostings\SaveApplicationForm;
use App\Enums\OrganizationRole;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

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
