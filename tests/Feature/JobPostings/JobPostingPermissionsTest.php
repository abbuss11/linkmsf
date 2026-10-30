<?php

use App\Actions\JobPostings\AssignRecruiter;
use App\Actions\JobPostings\CreateJobPostingDraft;
use App\Actions\JobPostings\UpdateJobPostingDetails;
use App\Actions\Organizations\UpdateOrganizationProfile;
use App\Enums\OrganizationRole;
use App\Exceptions\ActionFailed;
use App\Filament\App\Resources\JobPostings\Pages\ListJobPostings;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = makeOrganization(['slug' => 'sahel']);
    $this->admin = memberOf($this->org, OrganizationRole::OrgAdmin);
    $this->recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->otherRecruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->jury = memberOf($this->org, OrganizationRole::Jury);
    $this->posting = draftPosting($this->otherRecruiter, $this->org);
});

it('opens offer management to orgadmins and recruiters, not to the jury', function () {
    foreach ([[$this->admin, 200], [$this->recruiter, 200], [$this->jury, 403]] as [$user, $status]) {
        $this->flushSession();
        $this->actingAs($user)->get('/app/sahel/job-postings')->assertStatus($status);
        $this->flushSession();
        $this->actingAs($user)->get("/app/sahel/job-postings/{$this->posting->ulid}/edit")->assertStatus($status);
    }

    $this->flushSession();
    $this->actingAs($this->jury)->get('/app/sahel/job-postings/create')->assertForbidden();
});

it('refuses offer actions to the jury', function () {
    actingInOrganization($this->jury, $this->org);

    app(CreateJobPostingDraft::class)->handle($this->jury, $this->org, ['type' => 'employment', 'title' => 'Non']);
})->throws(AuthorizationException::class);

it('shows every offer to recruiters by default', function () {
    actingInOrganization($this->recruiter, $this->org);

    Livewire::test(ListJobPostings::class)->assertCanSeeTableRecords([$this->posting]);
});

it('restricts recruiters to their offers when the organisation asks for it', function () {
    $this->org->update(['settings' => ['job_postings' => ['recruiters_see_all' => false]]]);
    $own = draftPosting($this->recruiter, $this->org);
    actingInOrganization($this->recruiter->fresh(), $this->org->fresh());

    Livewire::test(ListJobPostings::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$this->posting]);

    expect(fn () => app(UpdateJobPostingDetails::class)->handle($this->recruiter, $this->posting, ['type' => 'employment', 'title' => 'X']))
        ->toThrow(AuthorizationException::class);

    $this->flushSession();
    // Not found in the recruiter's scope: refused (404 rather than 403, nothing is revealed).
    $this->actingAs($this->recruiter)->get("/app/sahel/job-postings/{$this->posting->ulid}/edit")->assertNotFound();

    // Once assigned by an orgadmin, the recruiter gets access.
    app(AssignRecruiter::class)->handle($this->admin, $this->posting, $this->recruiter);
    $this->flushSession();
    $this->actingAs($this->recruiter->fresh())->get("/app/sahel/job-postings/{$this->posting->ulid}/edit")->assertOk();
});

it('lets only orgadmins assign recruiters, and only recruiters be assigned', function () {
    actingInOrganization($this->recruiter, $this->org);

    expect(fn () => app(AssignRecruiter::class)->handle($this->recruiter, $this->posting, $this->recruiter))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(AssignRecruiter::class)->handle($this->admin, $this->posting, $this->jury))
        ->toThrow(ActionFailed::class);
});

it('lets only orgadmins change the offer workflow settings', function () {
    actingInOrganization($this->recruiter, $this->org);

    app(UpdateOrganizationProfile::class)->handle($this->recruiter, $this->org, [
        'name' => $this->org->name,
        'timezone' => 'Africa/Niamey',
        'job_postings_recruiters_see_all' => false,
    ]);
})->throws(AuthorizationException::class);
