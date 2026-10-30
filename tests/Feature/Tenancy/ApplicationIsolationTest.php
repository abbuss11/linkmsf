<?php

use App\Actions\Applications\StartApplication;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Candidate\DocumentDownloadController;
use App\Livewire\ApplicationWizard;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\CandidateDocument;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Candidate data is isolated per candidate, and applications per
 * organisation (§2.2, §10.1): every attempt below must be refused.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->orgA = makeOrganization(['slug' => 'org-a']);
    $this->orgB = makeOrganization(['slug' => 'org-b']);
    $recruiterA = memberOf($this->orgA, OrganizationRole::Recruiter);
    $this->recruiterB = memberOf($this->orgB, OrganizationRole::OrgAdmin);
    $this->posting = openPosting($recruiterA, $this->orgA, documents: [
        ['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 2048, 'max_files' => 1],
    ]);

    $this->alice = makeCandidate();
    $this->bob = makeCandidate();

    $this->actingAs($this->alice);
    $this->application = app(StartApplication::class)->handle($this->alice, $this->posting);
    uploadInChunks($this, $this->application->ulid, 'doc_cv', 'cv.pdf', pdfBytes())->assertOk();
    $this->document = ApplicationDocument::queryAcrossOrganizations()->sole();
});

it('hides an application from another candidate', function (string $method, string $route) {
    $this->actingAs($this->bob)
        ->call($method, route($route, $this->application->ulid))
        ->assertNotFound();
})->with([
    'page' => ['GET', 'candidate.applications.show'],
    'wizard' => ['GET', 'candidate.applications.edit'],
    'confirmation' => ['GET', 'candidate.applications.confirmation'],
    'withdraw' => ['POST', 'candidate.applications.withdraw'],
    'upload' => ['POST', 'candidate.applications.documents.init'],
]);

it('refuses chunks sent to another candidate\'s upload', function () {
    $url = route('candidate.applications.documents.chunk', [$this->application->ulid, $this->document->ulid]);

    $this->actingAs($this->bob)
        ->call('PUT', $url, [], [], [], ['HTTP_X_UPLOAD_OFFSET' => '0', 'HTTP_ACCEPT' => 'application/json'], 'x')
        ->assertNotFound();
});

it('refuses the wizard component for another candidate\'s application', function () {
    $this->actingAs($this->bob);

    Livewire::test(ApplicationWizard::class, ['application' => $this->application])->assertNotFound();
});

it('refuses a valid signed link to someone else', function () {
    $url = DocumentDownloadController::applicationUrl($this->document);

    $this->actingAs($this->alice)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($this->bob)->get($url)->assertForbidden();
    $this->actingAs($this->recruiterB)->get($url)->assertForbidden();
});

it('refuses unsigned, tampered and expired download links', function () {
    $url = DocumentDownloadController::applicationUrl($this->document);
    $this->actingAs($this->alice);

    $this->get(route('documents.application', $this->document->ulid))->assertForbidden();
    $this->get(str_replace($this->document->ulid, strrev($this->document->ulid), $url))->assertForbidden();

    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();
});

it('hides profile documents from other candidates', function () {
    $profileDocument = CandidateDocument::query()->create([
        'user_id' => $this->alice->id, 'type' => 'cv', 'label' => 'CV', 'disk' => 'local',
        'path' => 'candidates/x.pdf', 'original_name' => 'cv.pdf', 'mime' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64),
    ]);

    $this->actingAs($this->bob)->get(DocumentDownloadController::profileUrl($profileDocument))->assertForbidden();
    $this->actingAs($this->bob)->delete(route('candidate.documents.destroy', $profileDocument->ulid))->assertNotFound();
    $this->actingAs($this->bob)->post(route('candidate.documents.default', $profileDocument->ulid))->assertNotFound();
    $this->actingAs($this->bob);
    Livewire::test(ApplicationWizard::class, ['application' => app(StartApplication::class)->handle($this->bob, $this->posting)])
        ->call('attachProfileDocument', 'doc_cv', $profileDocument->ulid)
        ->assertNotFound();
});

it('keeps applications inside their organisation', function () {
    app(OrganizationContext::class)->set($this->orgB);
    expect(Application::query()->count())->toBe(0)
        ->and(ApplicationDocument::query()->count())->toBe(0);

    app(OrganizationContext::class)->set($this->orgA);
    expect(Application::query()->count())->toBe(1);

    app(OrganizationContext::class)->clear();
    expect(Application::query()->count())->toBe(0);
});

it('hides other candidates\' education and experience entries', function () {
    $education = $this->alice->educations()->create(['degree' => 'Licence', 'institution' => 'UAM']);
    $experience = $this->alice->experiences()->create(['title' => 'Agent', 'employer' => 'ONG']);

    $this->actingAs($this->bob)->delete(route('candidate.educations.destroy', $education->id))->assertNotFound();
    $this->actingAs($this->bob)->delete(route('candidate.experiences.destroy', $experience->id))->assertNotFound();
    expect($education->fresh())->not->toBeNull();
});
