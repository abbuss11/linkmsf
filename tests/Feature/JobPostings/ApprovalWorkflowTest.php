<?php

use App\Actions\JobPostings\ApproveJobPosting;
use App\Actions\JobPostings\PublishJobPosting;
use App\Actions\JobPostings\ReturnJobPostingToDraft;
use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Notifications\JobPostingReturnedToDraft;
use App\Notifications\JobPostingSubmittedForApproval;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->org = makeOrganization(['settings' => ['job_postings' => ['requires_approval' => true]]]);
    $this->admin = memberOf($this->org, OrganizationRole::OrgAdmin);
    $this->recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->posting = draftPosting($this->recruiter, $this->org);
    actingInOrganization($this->recruiter, $this->org);
});

it('makes recruiters submit offers that orgadmins approve', function () {
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);

    expect($this->posting->fresh()->status)->toBe(JobPostingStatus::PendingApproval)
        ->and($this->posting->fresh()->published_at)->toBeNull();
    Notification::assertSentTo($this->admin, JobPostingSubmittedForApproval::class);

    expect(fn () => app(ApproveJobPosting::class)->handle($this->recruiter, $this->posting->fresh()))
        ->toThrow(AuthorizationException::class);

    app(ApproveJobPosting::class)->handle($this->admin, $this->posting->fresh());

    expect($this->posting->fresh()->status)->toBe(JobPostingStatus::Published);
});

it('lets orgadmins publish directly even when approval is required', function () {
    $own = draftPosting($this->admin, $this->org);

    app(PublishJobPosting::class)->handle($this->admin, $own);

    expect($own->fresh()->status)->toBe(JobPostingStatus::Published);
});

it('sends an offer back to its author with a reason', function () {
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);
    app(ReturnJobPostingToDraft::class)->handle($this->admin, $this->posting->fresh(), 'Préciser le salaire.');

    expect($this->posting->fresh()->status)->toBe(JobPostingStatus::Draft);
    Notification::assertSentTo($this->recruiter, JobPostingReturnedToDraft::class, fn ($n) => $n->reason === 'Préciser le salaire.');
});

it('publishes directly when the organisation does not require approval', function () {
    $this->org->update(['settings' => ['job_postings' => ['requires_approval' => false]]]);

    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);

    expect($this->posting->fresh()->status)->toBe(JobPostingStatus::Published);
});
