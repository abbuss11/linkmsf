<?php

use App\Actions\Applications\StartApplication;
use App\Enums\OrganizationRole;
use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->org = makeOrganization(['slug' => 'sahel']);
    $recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->posting = openPosting($recruiter, $this->org, documents: [
        ['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 2048, 'max_files' => 1],
        ['label' => 'Diplômes', 'required' => false, 'extensions' => ['pdf', 'jpg'], 'max_kb' => 1024, 'max_files' => 2],
    ]);
    $this->candidate = makeCandidate();
    $this->actingAs($this->candidate);
    $this->application = app(StartApplication::class)->handle($this->candidate, $this->posting);
});

function chunk($test, string $url, int $offset, string $bytes)
{
    return $test->call('PUT', $url, [], [], [], [
        'HTTP_X_UPLOAD_OFFSET' => (string) $offset,
        'CONTENT_TYPE' => 'application/octet-stream',
        'HTTP_ACCEPT' => 'application/json',
    ], $bytes);
}

it('assembles a file sent in several chunks and stores it privately under a random name', function () {
    $bytes = pdfBytes(3000);

    uploadInChunks($this, $this->application->ulid, 'doc_cv', 'Mon CV final.PDF', $bytes, chunkSize: 1000)
        ->assertOk()
        ->assertJson(['status' => ApplicationDocument::COMPLETE, 'received_bytes' => strlen($bytes)]);

    $document = ApplicationDocument::queryAcrossOrganizations()->sole();
    expect($document->mime)->toBe('application/pdf')
        ->and($document->original_name)->toBe('Mon CV final.PDF')
        ->and($document->sha256)->toBe(hash('sha256', $bytes))
        ->and($document->path)->toMatch('#^applications/'.$this->org->id.'/'.$this->application->ulid.'/[0-9a-f-]{36}\.pdf$#')
        ->and($document->organization_id)->toBe($this->org->id);

    Storage::disk('local')->assertExists($document->path);
    expect(Storage::disk('local')->get($document->path))->toBe($bytes);
    Storage::disk('public')->assertMissing($document->path);
});

it('resumes after a cut: a wrong offset returns what the server already has', function () {
    $bytes = pdfBytes(1500);
    $init = $this->postJson(route('candidate.applications.documents.init', $this->application->ulid), [
        'field' => 'doc_cv', 'name' => 'cv.pdf', 'size' => strlen($bytes),
    ])->assertOk();
    $url = $init->json('chunk_url');

    chunk($this, $url, 0, substr($bytes, 0, 1000))->assertOk()->assertJson(['received_bytes' => 1000]);

    // The phone lost the answer and sends the first chunk again.
    chunk($this, $url, 0, substr($bytes, 0, 1000))->assertStatus(409)->assertJson(['received_bytes' => 1000]);

    // Asking again for the same file resumes the same upload.
    $this->postJson(route('candidate.applications.documents.init', $this->application->ulid), [
        'field' => 'doc_cv', 'name' => 'cv.pdf', 'size' => strlen($bytes),
    ])->assertOk();

    chunk($this, $url, 1000, substr($bytes, 1000))->assertOk()->assertJson(['status' => ApplicationDocument::COMPLETE]);
    expect(ApplicationDocument::queryAcrossOrganizations()->count())->toBe(1);
});

it('checks the real content of the file, not its name', function () {
    $fake = "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF".str_repeat("\x00", 200);

    uploadInChunks($this, $this->application->ulid, 'doc_cv', 'cv.pdf', $fake)
        ->assertStatus(422)
        ->assertJson(['message' => __('applications.errors.file_content', ['allowed' => 'PDF'])]);

    expect(ApplicationDocument::queryAcrossOrganizations()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('applications'))->toBe([]);
});

it('refuses wrong formats, oversized files and unknown fields before sending anything', function (array $payload, string $message) {
    $this->postJson(route('candidate.applications.documents.init', $this->application->ulid), $payload)
        ->assertStatus(422)
        ->assertJson(['message' => $message]);
})->with([
    'format' => [['field' => 'doc_cv', 'name' => 'cv.docx', 'size' => 100], 'Format non accepté. Formats autorisés : PDF.'],
    'size' => [['field' => 'doc_cv', 'name' => 'cv.pdf', 'size' => 3 * 1024 * 1024], 'Fichier trop volumineux (maximum 2 Mo).'],
    'field' => [['field' => 'nom_complet', 'name' => 'cv.pdf', 'size' => 100], 'Ce document n’est pas demandé pour cette offre.'],
]);

it('refuses chunks bigger than announced or than the chunk size', function () {
    config(['link.applications.chunk_size_kb' => 1]);
    $init = $this->postJson(route('candidate.applications.documents.init', $this->application->ulid), [
        'field' => 'doc_cv', 'name' => 'cv.pdf', 'size' => 1500,
    ]);

    chunk($this, $init->json('chunk_url'), 0, str_repeat('a', 1025))->assertStatus(422);
});

it('limits the number of files per document', function () {
    uploadInChunks($this, $this->application->ulid, 'doc_diplomes', 'a.pdf', pdfBytes(1))->assertOk();
    uploadInChunks($this, $this->application->ulid, 'doc_diplomes', 'b.pdf', pdfBytes(2))->assertOk();

    uploadInChunks($this, $this->application->ulid, 'doc_diplomes', 'c.pdf', pdfBytes(3))
        ->assertStatus(422)
        ->assertJson(['message' => __('applications.errors.too_many_files', ['max' => 2])]);
});

it('does not duplicate a file sent twice', function () {
    uploadInChunks($this, $this->application->ulid, 'doc_diplomes', 'a.pdf', pdfBytes(1))->assertOk();
    uploadInChunks($this, $this->application->ulid, 'doc_diplomes', 'a-copie.pdf', pdfBytes(1))->assertOk();

    expect(ApplicationDocument::queryAcrossOrganizations()->count())->toBe(1);
});

it('throttles upload requests', function () {
    config(['link.rate_limits.upload_requests_per_minute' => 2]);

    foreach (range(1, 2) as $i) {
        $this->postJson(route('candidate.applications.documents.init', $this->application->ulid), ['field' => 'doc_cv', 'name' => 'cv.docx', 'size' => 1]);
    }

    $this->postJson(route('candidate.applications.documents.init', $this->application->ulid), ['field' => 'doc_cv', 'name' => 'cv.docx', 'size' => 1])
        ->assertStatus(429);
});

it('lets a new file replace an upload abandoned long ago', function () {
    $this->postJson(route('candidate.applications.documents.init', $this->application->ulid), [
        'field' => 'doc_cv', 'name' => 'old.pdf', 'size' => 5000,
    ])->assertOk();

    $this->travel(31)->minutes();

    uploadInChunks($this, $this->application->ulid, 'doc_cv', 'new.pdf', pdfBytes())->assertOk();
    expect(ApplicationDocument::queryAcrossOrganizations()->pluck('original_name')->all())->toBe(['new.pdf']);
});
