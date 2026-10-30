<?php

use App\Actions\Applications\StartApplication;
use App\Actions\Applications\SubmitApplication;
use App\Actions\Candidates\UpdateCandidateProfile;
use App\Enums\ApplicationStatus;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Enums\PublicApplicationStatus;
use App\Exceptions\ActionFailed;
use App\Livewire\ApplicationWizard;
use App\Models\Application;
use App\Models\ApplicationRevision;
use App\Models\AuditLog;
use App\Notifications\ApplicationSubmitted;
use App\Support\Applications\AnswerStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();

    $this->org = makeOrganization(['slug' => 'sahel', 'name' => 'ONG Sahel']);
    $this->recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->posting = openPosting($this->recruiter, $this->org, documents: [
        ['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 2048, 'max_files' => 1],
    ]);
    $this->candidate = makeCandidate(['name' => 'Awa Moussa']);
});

/**
 * Fills the three steps of the default form and uploads the CV.
 */
function completeApplication($test, Application $application)
{
    $wizard = Livewire::test(ApplicationWizard::class, ['application' => $application])
        ->set('answers.niveau_detudes', 'licence')
        ->call('next')
        ->assertHasNoErrors()
        ->set('answers.disponible_immediatement', 'yes')
        ->call('next')
        ->assertHasNoErrors();

    uploadInChunks($test, $application->ulid, 'doc_cv', 'cv.pdf', pdfBytes())->assertOk();

    return $wizard->call('next')->assertSet('step', 4);
}

it('starts a draft from the offer page and resumes it on the next visit', function () {
    $this->actingAs($this->candidate)
        ->get(route('job-postings.apply', $this->posting->slug).'?source=whatsapp')
        ->assertRedirect();

    $application = Application::forCandidate($this->candidate)->sole();
    expect($application->status)->toBe(ApplicationStatus::Draft)
        ->and($application->source)->toBe('whatsapp')
        ->and($application->organization_id)->toBe($this->org->id);

    $this->get(route('job-postings.apply', $this->posting->slug))
        ->assertRedirect(route('candidate.applications.edit', $application->ulid));

    expect(Application::forCandidate($this->candidate)->count())->toBe(1);
});

it('sends guests to the login page and refuses members of organisations', function () {
    $this->get(route('job-postings.apply', $this->posting->slug))->assertRedirect(route('login'));

    $this->actingAs($this->recruiter)
        ->get(route('job-postings.apply', $this->posting->slug))
        ->assertRedirect('/app');
});

it('lets a candidate apply end to end and freezes what the organisation receives', function () {
    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $this->posting);

    $wizard = completeApplication($this, $application)
        ->assertSet('answers.nom_complet', 'Awa Moussa')
        ->call('submit')
        ->assertHasErrors('consent')
        ->set('consent', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('candidate.applications.confirmation', $application->ulid));

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->public_status)->toBe(PublicApplicationStatus::Received)
        ->and($application->reference)->toMatch('/^LNK-\d{4}-SAH-000001$/')
        ->and($application->submitted_at)->not->toBeNull()
        ->and($application->consent_text)->toContain('ONG Sahel')
        ->and($application->profile_snapshot['name'] ?? null)->toBe('Awa Moussa')
        ->and(AnswerStore::load($application))->toMatchArray(['disponible_immediatement' => 'yes', 'niveau_detudes' => 'licence']);

    Notification::assertSentTo($this->candidate, ApplicationSubmitted::class, fn ($n) => $n->reference === $application->reference);
    expect(AuditLog::query()->where('event', AuditEvent::ApplicationSubmitted->value)->count())->toBe(1);

    // The profile changes afterwards; the snapshot does not.
    app(UpdateCandidateProfile::class)->handle($this->candidate, ['name' => 'Awa M. Issoufou']);
    expect($application->fresh()->profile_snapshot['name'])->toBe('Awa Moussa');

    $this->get(route('candidate.applications.confirmation', $application->ulid))
        ->assertOk()
        ->assertSee($application->reference);
});

it('validates each step on the server', function () {
    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $this->posting);

    Livewire::test(ApplicationWizard::class, ['application' => $application])
        ->set('answers.niveau_detudes', 'not-a-level')
        ->call('next')
        ->assertHasErrors('answers.niveau_detudes')
        ->assertSet('step', 1);
});

it('refuses submission without the required documents and jumps to the step', function () {
    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $this->posting);

    Livewire::test(ApplicationWizard::class, ['application' => $application])
        ->set('answers.niveau_detudes', 'licence')
        ->call('next')
        ->set('answers.disponible_immediatement', 'yes')
        ->call('next')
        ->call('next')
        ->assertHasErrors('documents.doc_cv')
        ->assertSet('step', 3);

    expect(fn () => app(SubmitApplication::class)->handle($this->candidate, $application->fresh(), true))
        ->toThrow(ValidationException::class);
});

it('checks the deadline to the second', function () {
    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $this->posting);
    completeApplication($this, $application);

    $this->travelTo($this->posting->closes_at);

    expect(fn () => app(SubmitApplication::class)->handle($this->candidate, $application->fresh(), true))
        ->toThrow(AuthorizationException::class);

    $this->travelTo($this->posting->closes_at->copy()->subSecond());
    app(SubmitApplication::class)->handle($this->candidate, $application->fresh(), true);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('shows a clear message when the deadline passes while the form is open', function () {
    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $this->posting);
    $wizard = completeApplication($this, $application)->set('consent', true);

    $this->travelTo($this->posting->closes_at->copy()->addMinute());

    $wizard->call('submit')->assertHasErrors('form');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
});

it('applies the duplicate rule: one application per offer, no comeback after withdrawal', function () {
    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $this->posting);
    completeApplication($this, $application)->set('consent', true)->call('submit');

    expect(fn () => app(StartApplication::class)->handle($this->candidate, $this->posting))
        ->toThrow(ActionFailed::class, __('applications.errors.already_applied'));

    $this->get(route('job-postings.apply', $this->posting->slug))
        ->assertRedirect(route('candidate.applications.show', $application->ulid));

    $this->post(route('candidate.applications.withdraw', $application->ulid))
        ->assertRedirect(route('candidate.applications.show', $application->ulid));

    expect($application->fresh()->status)->toBe(ApplicationStatus::Withdrawn)
        ->and($application->fresh()->public_status)->toBe(PublicApplicationStatus::Withdrawn);

    expect(fn () => app(StartApplication::class)->handle($this->candidate, $this->posting))
        ->toThrow(ActionFailed::class, __('applications.errors.withdrawn'));
});

it('lets the candidate modify until the deadline when the offer allows it, keeping a revision', function () {
    $posting = openPosting($this->recruiter, $this->org, ['title' => 'Comptable'], [
        ['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 2048, 'max_files' => 1],
    ], ['edit_after_submit' => 'until_close']);

    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $posting);
    completeApplication($this, $application)->set('consent', true)->call('submit');
    $reference = $application->fresh()->reference;

    $this->get(route('job-postings.apply', $posting->slug))
        ->assertRedirect(route('candidate.applications.edit', $application->ulid));

    Livewire::test(ApplicationWizard::class, ['application' => $application->fresh()])
        ->call('goTo', 2)
        ->set('answers.disponible_immediatement', 'no')
        ->call('next')
        ->call('next')
        ->set('consent', true)
        ->call('submit')
        ->assertHasNoErrors();

    $application->refresh();
    expect($application->reference)->toBe($reference)
        ->and(AnswerStore::load($application)['disponible_immediatement'])->toBe('no')
        ->and(ApplicationRevision::queryAcrossOrganizations()->where('application_id', $application->id)->count())->toBe(1);
    expect(AuditLog::query()->where('event', AuditEvent::ApplicationUpdated->value)->count())->toBe(1);
});

it('refuses modifications after submission when the offer does not allow them', function () {
    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $this->posting);
    completeApplication($this, $application)->set('consent', true)->call('submit');

    $this->get(route('candidate.applications.edit', $application->ulid))
        ->assertRedirect(route('candidate.applications.show', $application->ulid));

    $this->postJson(route('candidate.applications.documents.init', $application->ulid), [
        'field' => 'doc_cv', 'name' => 'autre.pdf', 'size' => 100,
    ])->assertForbidden();
});

it('numbers references per organisation without gaps', function () {
    $other = makeCandidate();
    $this->actingAs($this->candidate);

    foreach ([$this->candidate, $other] as $candidate) {
        $this->actingAs($candidate);
        $application = app(StartApplication::class)->handle($candidate, $this->posting);
        completeApplication($this, $application)->set('consent', true)->call('submit');
    }

    expect(Application::queryAcrossOrganizations()->orderBy('id')->pluck('reference')->map(fn ($r) => substr($r, -10))->all())
        ->toBe(['SAH-000001', 'SAH-000002']);
});

it('limits the number of submissions per hour', function () {
    config(['link.rate_limits.application_submissions_per_hour' => 1]);
    $this->actingAs($this->candidate);
    $application = app(StartApplication::class)->handle($this->candidate, $this->posting);

    $wizard = completeApplication($this, $application);
    $wizard->call('submit'); // no consent: counts as an attempt
    $wizard->set('consent', true)->call('submit')->assertHasErrors('form');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
});
