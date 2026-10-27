<?php

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Support\Tenancy\OrganizationContext;
use Tests\Support\CountMembersJob;

it('runs a queued job inside the organisation of the model it processes', function () {
    $orgA = makeOrganization();
    $orgB = makeOrganization();
    memberOf($orgA, OrganizationRole::Recruiter);
    memberOf($orgB, OrganizationRole::Recruiter);
    memberOf($orgB, OrganizationRole::Jury);

    $invitation = Invitation::factory()->create(['organization_id' => $orgB->id]);

    // Dispatched while organisation A is active: the job must still use B, from the model.
    inOrganization($orgA, fn () => CountMembersJob::dispatch($invitation));

    expect(CountMembersJob::$seen)->toBe(['organization_id' => $orgB->id, 'members' => 2])
        ->and(app(OrganizationContext::class)->has())->toBeFalse();
});

it('carries the organisation id in the job payload', function () {
    $invitation = Invitation::factory()->create();

    $job = new CountMembersJob($invitation);

    expect($job->organizationId)->toBe($invitation->organization_id);
});
