<?php

use App\Models\CandidateDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->candidate = makeCandidate(['name' => 'Ibrahim Sani']);
    $this->actingAs($this->candidate);
});

/**
 * A real file on disk: the MIME type is read from its content.
 */
function realUpload(string $name, string $bytes): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'link');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, null, null, true);
}

it('shows and updates the global profile', function () {
    $this->get(route('candidate.profile'))->assertOk()->assertSee('Ibrahim Sani');

    $this->put(route('candidate.profile.update'), [
        'name' => 'Ibrahim Sani Moussa',
        'phone' => '+227 90 00 00 00',
        'birth_date' => '1995-04-12',
        'gender' => 'male',
        'city' => 'Niamey',
        'country' => 'NE',
        'education_level' => 'master',
        'languages' => 'Français, Haoussa, Français',
        'years_of_experience' => 4,
    ])->assertRedirect(route('candidate.profile'));

    $profile = $this->candidate->fresh()->profile();
    expect($this->candidate->fresh()->name)->toBe('Ibrahim Sani Moussa')
        ->and($profile->education_level?->value)->toBe('master')
        ->and($profile->languages)->toBe(['Français', 'Haoussa'])
        ->and($profile->gender?->value)->toBe('male');
});

it('validates the profile on the server', function () {
    $this->put(route('candidate.profile.update'), [
        'name' => '',
        'phone' => 'call me',
        'birth_date' => now()->subYears(5)->format('Y-m-d'),
        'education_level' => 'phd-plus',
        'gender' => 'unknown',
    ])->assertSessionHasErrors(['name', 'phone', 'birth_date', 'education_level', 'gender']);
});

it('adds and removes education and experience entries', function () {
    $this->post(route('candidate.educations.store'), ['degree' => 'Licence', 'institution' => 'Université Abdou Moumouni', 'start_year' => 2014, 'end_year' => 2017])
        ->assertRedirect();
    $this->post(route('candidate.experiences.store'), ['title' => 'Comptable', 'employer' => 'ONG Sahel', 'start_date' => '2019-01-01', 'is_current' => '1'])
        ->assertRedirect();

    expect($this->candidate->educations()->count())->toBe(1)
        ->and($this->candidate->experiences()->first()->is_current)->toBeTrue();

    $this->get(route('candidate.profile'))->assertSee('Université Abdou Moumouni')->assertSee('ONG Sahel');

    $this->delete(route('candidate.educations.destroy', $this->candidate->educations()->first()->id))->assertRedirect();
    expect($this->candidate->educations()->count())->toBe(0);
});

it('stores profile documents privately, checks their content and sets the default CV', function () {
    $this->post(route('candidate.documents.store'), ['file' => realUpload('cv.pdf', pdfBytes()), 'type' => 'cv', 'label' => 'Mon CV'])
        ->assertRedirect(route('candidate.documents'));

    $document = CandidateDocument::query()->sole();
    expect($document->mime)->toBe('application/pdf')
        ->and($document->path)->toStartWith('candidates/'.$this->candidate->id.'/')
        ->and($this->candidate->profile()->default_cv_document_id)->toBe($document->id);
    Storage::disk('local')->assertExists($document->path);

    $this->post(route('candidate.documents.store'), ['file' => realUpload('cv.pdf', "MZ\x90\x00".str_repeat("\x00", 100)), 'type' => 'cv'])
        ->assertSessionHasErrors('file');
    expect(CandidateDocument::query()->count())->toBe(1);

    $this->get(route('candidate.documents'))->assertOk()->assertSee('Mon CV')->assertSee(__('candidate.documents.default_cv'));

    $this->delete(route('candidate.documents.destroy', $document->ulid))->assertRedirect();
    expect(CandidateDocument::query()->count())->toBe(0);
});

it('shows drafts and sent applications on the dashboard', function () {
    $this->get(route('candidate.dashboard'))
        ->assertOk()
        ->assertSee(__('candidate.dashboard.applications_empty'))
        ->assertSee(route('candidate.profile'));
});
