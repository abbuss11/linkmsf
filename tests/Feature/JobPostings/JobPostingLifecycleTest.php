<?php

use App\Actions\JobPostings\ArchiveJobPosting;
use App\Actions\JobPostings\CheckJobPostingReadiness;
use App\Actions\JobPostings\CloseJobPosting;
use App\Actions\JobPostings\PublishJobPosting;
use App\Actions\JobPostings\ReopenJobPosting;
use App\Actions\JobPostings\ResumeJobPosting;
use App\Actions\JobPostings\SaveJobRequirements;
use App\Actions\JobPostings\SuspendJobPosting;
use App\Actions\JobPostings\UpdateJobPostingDetails;
use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Exceptions\ActionFailed;
use App\Models\ApplicationForm;
use App\Models\AuditLog;

beforeEach(function () {
    $this->org = makeOrganization(['slug' => 'sahel']);
    $this->recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->posting = draftPosting($this->recruiter, $this->org);
    actingInOrganization($this->recruiter, $this->org);
});

it('creates a draft with a readable reference, a global slug and the creator assigned', function () {
    expect($this->posting->status)->toBe(JobPostingStatus::Draft)
        ->and($this->posting->reference)->toMatch('/^SAH-\d{4}-001$/')
        ->and($this->posting->slug)->toBe('charge-de-suivi-evaluation-sahel')
        ->and($this->posting->isAssignedTo($this->recruiter))->toBeTrue();

    $other = draftPosting($this->recruiter, $this->org);
    expect($other->slug)->toBe('charge-de-suivi-evaluation-sahel-2')
        ->and($other->reference)->toMatch('/-002$/');
});

it('publishes a ready draft and freezes the form as version 1', function () {
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);

    $form = ApplicationForm::query()->where('job_posting_id', $this->posting->id)->with('currentVersion')->sole();

    expect($this->posting->fresh()->status)->toBe(JobPostingStatus::Published)
        ->and($this->posting->fresh()->published_at)->not->toBeNull()
        ->and($form->currentVersion->version)->toBe(1)
        ->and($form->currentVersion->schema)->toEqual($form->schema)
        ->and(AuditLog::query()->where('event', AuditEvent::JobPostingPublished->value)->exists())->toBeTrue();
});

it('blocks publication when something is inconsistent', function (Closure $breakIt, string $expected) {
    $breakIt($this);

    expect(fn () => app(PublishJobPosting::class)->handle($this->recruiter, $this->posting->fresh()))
        ->toThrow(ActionFailed::class, $expected);

    expect($this->posting->fresh()->status)->toBe(JobPostingStatus::Draft);
})->with([
    'deadline in the past' => [fn ($test) => $test->posting->forceFill(['closes_at' => now()->subDay()])->save(), 'déjà passée'],
    'no deadline' => [fn ($test) => $test->posting->forceFill(['closes_at' => null])->save(), 'obligatoire'],
    'empty form' => [fn ($test) => ApplicationForm::query()->where('job_posting_id', $test->posting->id)->update(['schema' => json_encode(['fields' => []])]), 'aucune question'],
    'eliminatory criterion without field' => [fn ($test) => app(SaveJobRequirements::class)->handle($test->recruiter, $test->posting, [
        ['label' => 'Âge', 'usage' => 'eliminatory', 'field_key' => null, 'rule' => null],
    ]), 'n’est relié à aucune question'],
]);

it('enforces the active offers limit of the organisation', function () {
    $this->org->update(['settings' => ['limits' => ['max_active_job_postings' => 1, 'storage_quota_mb' => 100]]]);
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);

    $second = draftPosting($this->recruiter, $this->org);

    expect(fn () => app(PublishJobPosting::class)->handle($this->recruiter, $second))
        ->toThrow(ActionFailed::class, 'limite de 1 offres actives');
});

it('follows the lifecycle and refuses transitions that are not allowed', function () {
    $posting = $this->posting;
    app(PublishJobPosting::class)->handle($this->recruiter, $posting);

    expect(fn () => app(ArchiveJobPosting::class)->handle($this->recruiter, $posting->fresh()))
        ->toThrow(ActionFailed::class);

    app(SuspendJobPosting::class)->handle($this->recruiter, $posting->fresh());
    expect($posting->fresh()->status)->toBe(JobPostingStatus::Suspended);

    app(ResumeJobPosting::class)->handle($this->recruiter, $posting->fresh());
    expect($posting->fresh()->status)->toBe(JobPostingStatus::Published);

    app(CloseJobPosting::class)->handle($this->recruiter, $posting->fresh());
    expect($posting->fresh()->status)->toBe(JobPostingStatus::Closed)
        ->and($posting->fresh()->closed_at)->not->toBeNull();

    app(ArchiveJobPosting::class)->handle($this->recruiter, $posting->fresh());
    expect($posting->fresh()->status)->toBe(JobPostingStatus::Archived)
        ->and(JobPostingStatus::Archived->allowedTransitions())->toBe([]);
});

it('reopens a closed offer only with a reason and a future deadline, and logs the reason', function () {
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);
    app(CloseJobPosting::class)->handle($this->recruiter, $this->posting->fresh());

    expect(fn () => app(ReopenJobPosting::class)->handle($this->recruiter, $this->posting->fresh(), ' ', now()->addDay()))
        ->toThrow(ActionFailed::class, __('job_postings.errors.reason_required'));
    expect(fn () => app(ReopenJobPosting::class)->handle($this->recruiter, $this->posting->fresh(), 'Prolongation', now()->subDay()))
        ->toThrow(ActionFailed::class);

    app(ReopenJobPosting::class)->handle($this->recruiter, $this->posting->fresh(), 'Peu de candidatures reçues', now()->addWeek());

    $log = AuditLog::query()->where('event', AuditEvent::JobPostingReopened->value)->sole();
    expect($this->posting->fresh()->status)->toBe(JobPostingStatus::Published)
        ->and($log->properties['reason'])->toBe('Peu de candidatures reçues');
});

it('refuses to resume a suspended offer whose deadline has passed', function () {
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);
    app(SuspendJobPosting::class)->handle($this->recruiter, $this->posting->fresh());
    $this->posting->forceFill(['closes_at' => now()->subMinute()])->save();

    expect(fn () => app(ResumeJobPosting::class)->handle($this->recruiter, $this->posting->fresh()))->toThrow(ActionFailed::class);
});

it('accepts applications only before the deadline, to the second', function () {
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);
    $posting = $this->posting->fresh();
    $deadline = $posting->closes_at;

    expect($posting->acceptsApplicationsAt($deadline->copy()->subSecond()))->toBeTrue()
        ->and($posting->acceptsApplicationsAt($deadline))->toBeFalse()
        ->and($posting->acceptsApplicationsAt($deadline->copy()->addSecond()))->toBeFalse();

    $posting->forceFill(['opens_at' => now()->addDay()])->save();
    expect($posting->fresh()->acceptsApplicationsAt(now()))->toBeFalse();

    $draft = draftPosting($this->recruiter, $this->org);
    expect($draft->acceptsApplicationsAt(now()))->toBeFalse();
});

it('reports warnings without blocking', function () {
    $report = app(CheckJobPostingReadiness::class)->handle($this->posting);

    expect($report->isReady())->toBeTrue()
        ->and($report->warnings)->toContain(__('job_postings.readiness.no_requirements'))
        ->and($report->warnings)->toContain(__('job_postings.readiness.no_documents'));
});

it('keeps the slug once published', function () {
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);

    app(UpdateJobPostingDetails::class)->handle($this->recruiter, $this->posting->fresh(), [
        'type' => 'employment',
        'title' => 'Nouveau titre',
        'closes_at' => '2030-06-01 12:00:00',
    ]);

    expect($this->posting->fresh()->slug)->toBe('charge-de-suivi-evaluation-sahel')
        ->and($this->posting->fresh()->title)->toBe('Nouveau titre');
});

it('sanitizes the description on save', function () {
    app(UpdateJobPostingDetails::class)->handle($this->recruiter, $this->posting, [
        'type' => 'employment',
        'title' => 'Titre',
        'description' => '<p>Ok</p><script>alert(1)</script>',
    ]);

    expect($this->posting->fresh()->description)->toBe('<p>Ok</p>');
});
