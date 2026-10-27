<?php

use App\Enums\OrganizationRole;
use App\Filament\App\Pages\EditOrganizationProfile;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = makeOrganization(['slug' => 'sahel', 'name' => 'Sahel']);
});

it('lets the orgadmin edit the profile but not the slug', function () {
    actingInOrganization(memberOf($this->org, OrganizationRole::OrgAdmin), $this->org);

    Livewire::test(EditOrganizationProfile::class)
        ->fillForm([
            'name' => 'Sahel Avenir',
            'slug' => 'pirate',
            'timezone' => 'Africa/Niamey',
            'primary_color' => '#0f766e',
            'listed_on_job_board' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $org = $this->org->fresh();
    expect($org->name)->toBe('Sahel Avenir')
        ->and($org->slug)->toBe('sahel')
        ->and($org->primary_color)->toBe('#0f766e')
        ->and($org->listed_on_job_board)->toBeFalse();
});

it('rejects invalid colours on the server', function () {
    actingInOrganization(memberOf($this->org, OrganizationRole::OrgAdmin), $this->org);

    Livewire::test(EditOrganizationProfile::class)
        ->fillForm(['name' => 'Sahel', 'timezone' => 'Africa/Niamey', 'primary_color' => 'red;}body{'])
        ->call('save')
        ->assertHasFormErrors(['primary_color']);

    expect($this->org->fresh()->primary_color)->toBeNull();
});

it('refuses the profile page to recruiters and jury members', function (OrganizationRole $role) {
    actingInOrganization(memberOf($this->org, $role), $this->org);

    Livewire::test(EditOrganizationProfile::class)->assertStatus(404);
})->with([OrganizationRole::Recruiter, OrganizationRole::Jury]);
