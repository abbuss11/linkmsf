<?php

use App\Enums\OrganizationRole;
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

function inOrganization(Organization $organization, Closure $callback): mixed
{
    return app(OrganizationContext::class)->runFor($organization, $callback);
}
