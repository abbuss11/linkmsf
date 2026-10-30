<?php

use App\Enums\OrganizationRole;
use Illuminate\Support\Facades\Storage;

/*
 * Full-text search needs committed rows (InnoDB indexes them at commit):
 * this suite truncates tables instead of wrapping tests in a transaction.
 */
it('finds offers by words of their title and description', function () {
    Storage::fake('public');
    $org = makeOrganization(['slug' => 'sahel']);
    $recruiter = memberOf($org, OrganizationRole::Recruiter);

    openPosting($recruiter, $org, ['title' => 'Comptable principal', 'description' => '<p>Gestion de la trésorerie et des rapprochements bancaires.</p>']);
    openPosting($recruiter, $org, ['title' => 'Chauffeur', 'description' => '<p>Conduite de véhicules tout-terrain.</p>']);

    $this->get(route('job-postings.index', ['q' => 'trésorerie']))
        ->assertOk()
        ->assertSee('Comptable principal')
        ->assertDontSee('Chauffeur');

    $this->get(route('job-postings.index', ['q' => 'comptable']))
        ->assertSee('Comptable principal')
        ->assertDontSee('Chauffeur');
});
