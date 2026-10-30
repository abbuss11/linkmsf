<?php

use App\Enums\OrganizationRole;
use App\Exceptions\MissingOrganizationContextException;
use App\Exceptions\OrganizationReassignmentException;
use App\Models\Invitation;
use App\Models\OrganizationMembership;
use App\Support\Tenancy\OrganizationContext;

beforeEach(function () {
    $this->orgA = makeOrganization();
    $this->orgB = makeOrganization();
    memberOf($this->orgA, OrganizationRole::Recruiter);
    memberOf($this->orgB, OrganizationRole::Recruiter);
    memberOf($this->orgB, OrganizationRole::Jury);
});

it('returns nothing when no organisation is active (fail closed)', function () {
    expect(OrganizationMembership::query()->count())->toBe(0)
        ->and(OrganizationMembership::queryAcrossOrganizations()->count())->toBe(3);
});

it('only returns rows of the active organisation', function () {
    inOrganization($this->orgA, function () {
        expect(OrganizationMembership::query()->pluck('organization_id')->unique()->all())->toBe([$this->orgA->id]);
    });

    inOrganization($this->orgB, function () {
        expect(OrganizationMembership::query()->count())->toBe(2);
    });
});

it('does not find a row of another organisation by its id', function () {
    $foreign = OrganizationMembership::queryForOrganization($this->orgB)->firstOrFail();

    inOrganization($this->orgA, function () use ($foreign) {
        expect(OrganizationMembership::query()->find($foreign->id))->toBeNull();
    });
});

it('fills organization_id from the active organisation on creation', function () {
    $invitation = inOrganization($this->orgA, fn () => Invitation::factory()->create(['organization_id' => null]));

    expect($invitation->organization_id)->toBe($this->orgA->id);
});

it('refuses to create a row without organisation', function () {
    Invitation::factory()->create(['organization_id' => null]);
})->throws(MissingOrganizationContextException::class);

it('keeps an explicit organization_id', function () {
    $invitation = Invitation::factory()->create(['organization_id' => $this->orgB->id]);

    expect($invitation->organization_id)->toBe($this->orgB->id);
});

it('refuses to move a row to another organisation', function () {
    $membership = OrganizationMembership::queryForOrganization($this->orgA)->firstOrFail();

    $membership->organization_id = $this->orgB->id;
    $membership->save();
})->throws(OrganizationReassignmentException::class);

it('restores the previous organisation after runFor', function () {
    $context = app(OrganizationContext::class);
    $context->set($this->orgA);

    $context->runFor($this->orgB, fn () => expect($context->id())->toBe($this->orgB->id));

    expect($context->id())->toBe($this->orgA->id);
});

it('restores the previous organisation even when the callback throws', function () {
    $context = app(OrganizationContext::class);

    try {
        $context->runFor($this->orgB, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect($context->has())->toBeFalse();
});
