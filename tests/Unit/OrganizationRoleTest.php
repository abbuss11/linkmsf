<?php

use App\Enums\OrganizationRole;
use App\Enums\Permission;

it('grants every organisation permission to orgadmins only', function (Permission $permission) {
    expect(OrganizationRole::OrgAdmin->can($permission))->toBeTrue()
        ->and(OrganizationRole::Recruiter->can($permission))->toBeFalse()
        ->and(OrganizationRole::Jury->can($permission))->toBeFalse();
})->with(Permission::cases());

it('requires multi-factor authentication for orgadmins only', function () {
    expect(OrganizationRole::OrgAdmin->requiresMultiFactorAuthentication())->toBeTrue()
        ->and(OrganizationRole::Recruiter->requiresMultiFactorAuthentication())->toBeFalse()
        ->and(OrganizationRole::Jury->requiresMultiFactorAuthentication())->toBeFalse();
});

it('has a French label for every role', function (OrganizationRole $role) {
    expect($role->getLabel())->not->toStartWith('enums.');
})->with(OrganizationRole::cases());
