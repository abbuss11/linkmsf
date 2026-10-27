<?php

use App\Enums\OrganizationRole;

/*
 * Cahier des charges §11.2 "Permissions": each role on each page it may and
 * may not open.
 */

beforeEach(function () {
    $this->org = makeOrganization(['slug' => 'sahel']);
});

dataset('organisation pages', [
    'dashboard' => ['', [200, 200, 200]],
    'members' => ['/members', [200, 403, 403]],
    'invitations' => ['/invitations', [200, 403, 403]],
    'activity' => ['/activity', [200, 403, 403]],
    'organisation profile' => ['/profile', [200, 404, 404]],
]);

it('applies the role matrix to organisation pages', function (string $path, array $expected) {
    $roles = [OrganizationRole::OrgAdmin, OrganizationRole::Recruiter, OrganizationRole::Jury];

    foreach ($roles as $index => $role) {
        $this->actingAs(memberOf($this->org, $role))
            ->get("/app/sahel{$path}")
            ->assertStatus($expected[$index]);
    }
})->with('organisation pages');

it('hides organisation management from the navigation of recruiters and jury members', function (OrganizationRole $role) {
    $this->actingAs(memberOf($this->org, $role))
        ->get('/app/sahel')
        ->assertOk()
        ->assertDontSee('/app/sahel/members', false)
        ->assertDontSee('/app/sahel/invitations', false)
        ->assertDontSee('/app/sahel/activity', false);
})->with([OrganizationRole::Recruiter, OrganizationRole::Jury]);

it('shows organisation management to orgadmins', function () {
    $this->actingAs(memberOf($this->org, OrganizationRole::OrgAdmin))
        ->get('/app/sahel')
        ->assertOk()
        ->assertSee('/app/sahel/members', false)
        ->assertSee('/app/sahel/invitations', false);
});
