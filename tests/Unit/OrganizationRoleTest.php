<?php

use App\Enums\OrganizationRole;
use App\Enums\Permission;

it('grants every organisation permission to orgadmins', function (Permission $permission) {
    expect(OrganizationRole::OrgAdmin->can($permission))->toBeTrue();
})->with(Permission::cases());

it('lets recruiters manage offers and process and export applications, nothing else', function (Permission $permission) {
    expect(OrganizationRole::Recruiter->can($permission))->toBe(in_array($permission, [Permission::ManageJobPostings, Permission::ProcessApplications, Permission::ExportApplications], true));
})->with(Permission::cases());

it('grants no organisation permission to jury members', function (Permission $permission) {
    expect(OrganizationRole::Jury->can($permission))->toBeFalse();
})->with(Permission::cases());

it('requires multi-factor authentication for orgadmins only', function () {
    expect(OrganizationRole::OrgAdmin->requiresMultiFactorAuthentication())->toBeTrue()
        ->and(OrganizationRole::Recruiter->requiresMultiFactorAuthentication())->toBeFalse()
        ->and(OrganizationRole::Jury->requiresMultiFactorAuthentication())->toBeFalse();
});

it('has a French label for every role', function (OrganizationRole $role) {
    expect($role->getLabel())->not->toStartWith('enums.');
})->with(OrganizationRole::cases());
