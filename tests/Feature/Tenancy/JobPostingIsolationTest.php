<?php

use App\Actions\JobPostings\ArchiveJobPosting;
use App\Actions\JobPostings\AssignRecruiter;
use App\Actions\JobPostings\CloseJobPosting;
use App\Actions\JobPostings\CreateJobPostingDraft;
use App\Actions\JobPostings\DuplicateJobPosting;
use App\Actions\JobPostings\PublishFormChanges;
use App\Actions\JobPostings\PublishJobPosting;
use App\Actions\JobPostings\SaveApplicationForm;
use App\Actions\JobPostings\SaveJobRequirements;
use App\Actions\JobPostings\UpdateApplicationSettings;
use App\Actions\JobPostings\UpdateJobPostingDetails;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\JobPostings\Pages\ListJobPostings;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/*
 * §2.2 / §11.2: offers, criteria, forms and versions of another
 * organisation are refused by URL, identifier, Livewire and domain action.
 */

beforeEach(function () {
    $this->orgA = makeOrganization(['slug' => 'org-a']);
    $this->orgB = makeOrganization(['slug' => 'org-b']);
    $this->adminA = memberOf($this->orgA, OrganizationRole::OrgAdmin);
    $this->recruiterB = memberOf($this->orgB, OrganizationRole::Recruiter);

    $this->postingB = draftPosting($this->recruiterB, $this->orgB);
    inOrganization($this->orgB, function () {
        actingInOrganization($this->recruiterB, $this->orgB);
        app(PublishJobPosting::class)->handle($this->recruiterB, $this->postingB);
    });
});

it('refuses the pages of an offer of another organisation', function (string $suffix) {
    $this->actingAs($this->adminA)
        ->get("/app/org-a/job-postings/{$this->postingB->ulid}{$suffix}")
        ->assertNotFound();

    $this->actingAs($this->adminA)
        ->get("/app/org-b/job-postings/{$this->postingB->ulid}{$suffix}")
        ->assertNotFound();
})->with(['edit' => ['/edit'], 'preview' => ['/preview'], 'form preview' => ['/form-preview']]);

it('never lists offers of another organisation', function () {
    $own = draftPosting($this->adminA, $this->orgA);
    actingInOrganization($this->adminA, $this->orgA);

    Livewire::test(ListJobPostings::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$this->postingB]);
});

it('does not resolve rows of another organisation', function () {
    inOrganization($this->orgA, function () {
        expect(JobPosting::query()->find($this->postingB->id))->toBeNull()
            ->and(ApplicationForm::query()->where('job_posting_id', $this->postingB->id)->exists())->toBeFalse()
            ->and(ApplicationFormVersion::query()->count())->toBe(0)
            ->and(JobRequirement::query()->where('job_posting_id', $this->postingB->id)->exists())->toBeFalse();
    });
});

it('refuses every domain action on an offer of another organisation', function (Closure $call) {
    actingInOrganization($this->adminA, $this->orgA);

    $call->call($this);
})->with([
    'update details' => [fn () => app(UpdateJobPostingDetails::class)->handle($this->adminA, $this->postingB, ['type' => 'employment', 'title' => 'Piraté'])],
    'save criteria' => [fn () => app(SaveJobRequirements::class)->handle($this->adminA, $this->postingB, [])],
    'save settings' => [fn () => app(UpdateApplicationSettings::class)->handle($this->adminA, $this->postingB, ['edit_after_submit' => 'never', 'duplicate_policy' => 'single', 'notify_new_applications' => 'none'])],
    'save form' => [fn () => app(SaveApplicationForm::class)->handle($this->adminA, $this->postingB, [])],
    'publish form changes' => [fn () => app(PublishFormChanges::class)->handle($this->adminA, $this->postingB)],
    'close' => [fn () => app(CloseJobPosting::class)->handle($this->adminA, $this->postingB)],
    'archive' => [fn () => app(ArchiveJobPosting::class)->handle($this->adminA, $this->postingB)],
    'duplicate' => [fn () => app(DuplicateJobPosting::class)->handle($this->adminA, $this->postingB)],
    'assign a recruiter' => [fn () => app(AssignRecruiter::class)->handle($this->adminA, $this->postingB, $this->recruiterB)],
    'create in another organisation' => [fn () => app(CreateJobPostingDraft::class)->handle($this->adminA, $this->orgB, ['type' => 'employment', 'title' => 'Intrus'])],
])->throws(AuthorizationException::class);

it('leaves the offer of another organisation untouched', function () {
    actingInOrganization($this->adminA, $this->orgA);

    rescue(fn () => app(UpdateJobPostingDetails::class)->handle($this->adminA, $this->postingB, ['type' => 'employment', 'title' => 'Piraté']), report: false);
    rescue(fn () => app(CloseJobPosting::class)->handle($this->adminA, $this->postingB), report: false);

    expect($this->postingB->fresh()->title)->not->toBe('Piraté')
        ->and($this->postingB->fresh()->status->value)->toBe('published');
});
