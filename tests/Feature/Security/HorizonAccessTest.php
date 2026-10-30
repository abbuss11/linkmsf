<?php

use App\Enums\OrganizationRole;

it('opens the queue dashboard to superadmins only', function () {
    $this->get('/horizon')->assertForbidden();
    $this->actingAs(makeCandidate())->get('/horizon')->assertForbidden();
    $this->actingAs(memberOf(makeOrganization(), OrganizationRole::OrgAdmin))->get('/horizon')->assertForbidden();
    $this->actingAs(makeSuperadmin())->get('/horizon')->assertOk();
});
