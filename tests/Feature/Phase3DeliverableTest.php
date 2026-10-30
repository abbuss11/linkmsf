<?php

use App\Enums\ApplicationStatus;
use App\Enums\OrganizationRole;
use App\Livewire\ApplicationWizard;
use App\Models\Application;
use App\Notifications\ApplicationSubmitted;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Phase 3 deliverable: a candidate applies end to end from a phone, from
 * the job board to the confirmation e-mail.
 */
it('lets a candidate find an offer, apply from a phone and follow the application', function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();

    $org = makeOrganization(['slug' => 'sahel', 'name' => 'ONG Sahel']);
    $recruiter = memberOf($org, OrganizationRole::Recruiter);
    $posting = openPosting($recruiter, $org, ['title' => 'Chargé de suivi-évaluation'], [
        ['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 2048, 'max_files' => 1],
    ]);
    $candidate = makeCandidate(['name' => 'Mariama Issa', 'email' => 'mariama@example.org', 'password' => 'secret-pass-123']);
    $phone = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 10; itel A56) AppleWebKit/537.36 Chrome/96 Mobile Safari/537.36'];

    // 1. Job board, then the offer page shared on WhatsApp.
    $this->withServerVariables($phone)->get('/offres')->assertOk()->assertSee('Chargé de suivi-évaluation');
    $this->get('/offres/'.$posting->slug.'?source=whatsapp')->assertOk()->assertSee(__('showcase.posting.apply'));

    // 2. "Postuler" asks to log in, then comes back to the offer.
    $this->get(route('job-postings.apply', $posting->slug))->assertRedirect(route('login'));
    $this->post(route('login'), ['email' => 'mariama@example.org', 'password' => 'secret-pass-123'])
        ->assertRedirect(route('job-postings.apply', $posting->slug));
    $application = null;
    $this->get(route('job-postings.apply', $posting->slug))->assertRedirect();
    $application = Application::forCandidate($candidate)->sole();

    // 3. The wizard page is light and works without Filament.
    $page = $this->get(route('candidate.applications.edit', $application->ulid))->assertOk();
    expect(strlen($page->getContent()))->toBeLessThan(80 * 1024)
        ->and($page->getContent())->not->toContain('filament');

    // 4. Steps: profile (pre-filled), questions, documents (chunked), review.
    $wizard = Livewire::test(ApplicationWizard::class, ['application' => $application])
        ->assertSet('answers.nom_complet', 'Mariama Issa')
        ->set('answers.niveau_detudes', 'master')
        ->call('next')
        ->set('answers.disponible_immediatement', 'yes')
        ->call('next')
        ->assertSet('step', 3);

    uploadInChunks($this, $application->ulid, 'doc_cv', 'CV Mariama.pdf', pdfBytes(2000), chunkSize: 700)->assertOk();

    $wizard->call('next')
        ->assertSet('step', 4)
        ->assertSee('CV Mariama.pdf')
        ->set('consent', true)
        ->call('submit')
        ->assertRedirect(route('candidate.applications.confirmation', $application->ulid));

    // 5. Confirmation, e-mail, follow-up.
    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->source)->toBe('whatsapp')
        ->and($application->reference)->toMatch('/^LNK-\d{4}-SAH-000001$/');

    $this->get(route('candidate.applications.confirmation', $application->ulid))->assertSee($application->reference);
    Notification::assertSentTo($candidate, ApplicationSubmitted::class);

    $this->get(route('candidate.dashboard'))->assertSee('Chargé de suivi-évaluation')->assertSee($application->reference);
    $this->get(route('candidate.applications.show', $application->ulid))
        ->assertOk()
        ->assertSee(__('enums.public_application_status.received'))
        ->assertSee('CV Mariama.pdf');
});
