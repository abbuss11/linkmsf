<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\OrganizationMembership;
use App\Models\User;

beforeEach(function () {
    $this->org = makeOrganization(['slug' => 'sahel']);
});

it('keeps candidates out of both back-offices', function () {
    $candidate = makeCandidate();

    $this->actingAs($candidate)->get('/admin')->assertForbidden();
    $this->actingAs($candidate)->get('/app/sahel')->assertForbidden();
});

it('keeps organisation members out of the platform panel', function (OrganizationRole $role) {
    $member = memberOf($this->org, $role);

    $this->actingAs($member)->get('/admin')->assertForbidden();
})->with(OrganizationRole::cases());

it('opens the platform panel to superadmins', function () {
    $this->actingAs(makeSuperadmin())->get('/admin')->assertOk();
});

it('sends a superadmin without MFA to the MFA set-up page', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)->get('/admin')->assertRedirectContains('/admin/multi-factor-authentication/set-up');
});

it('sends an orgadmin without MFA to the MFA set-up page', function () {
    $admin = memberOf($this->org, OrganizationRole::OrgAdmin);
    $admin->has_email_authentication = false;
    $admin->save();

    $this->actingAs($admin)->get('/app/sahel')->assertRedirectContains('/app/multi-factor-authentication/set-up');
});

it('lets recruiters and jury members in without MFA', function (OrganizationRole $role) {
    $this->actingAs(memberOf($this->org, $role))->get('/app/sahel')->assertOk();
})->with([OrganizationRole::Recruiter, OrganizationRole::Jury]);

it('closes a suspended organisation to all its members', function () {
    $admin = memberOf($this->org, OrganizationRole::OrgAdmin);
    $this->org->update(['status' => OrganizationStatus::Suspended]);

    $this->actingAs($admin)->get('/app/sahel')->assertNotFound();
});

it('closes a pending organisation to its members', function () {
    $recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->org->update(['status' => OrganizationStatus::Pending]);

    $this->actingAs($recruiter)->get('/app/sahel')->assertNotFound();
});

it('closes the organisation to a suspended member', function () {
    $recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    OrganizationMembership::queryForOrganization($this->org)
        ->where('user_id', $recruiter->id)
        ->update(['status' => MembershipStatus::Suspended->value]);

    $this->actingAs($recruiter)->get('/app/sahel')->assertNotFound();
});

it('gives a person a different role in each organisation', function () {
    $other = makeOrganization(['slug' => 'kawtal']);
    $person = memberOf($this->org, OrganizationRole::Recruiter);
    OrganizationMembership::factory()->create([
        'organization_id' => $other->id,
        'user_id' => $person->id,
        'role' => OrganizationRole::Jury,
    ]);

    expect($person->roleIn($this->org))->toBe(OrganizationRole::Recruiter)
        ->and($person->roleIn($other))->toBe(OrganizationRole::Jury)
        ->and($person->getTenants(filament()->getPanel('app'))->pluck('slug')->sort()->values()->all())->toBe(['kawtal', 'sahel']);

    $this->actingAs($person)->get('/app/sahel/members')->assertForbidden();
    $this->actingAs($person)->get('/app/kawtal')->assertOk();
});

it('redirects guests to the panel login', function () {
    $this->get('/app/sahel')->assertRedirect('/app/login');
    $this->get('/admin')->assertRedirect('/admin/login');
});
