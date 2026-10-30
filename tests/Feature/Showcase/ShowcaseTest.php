<?php

use App\Actions\Applications\StartApplication;
use App\Actions\JobPostings\CloseJobPosting;
use App\Actions\JobPostings\UpdateJobPostingDetails;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Jobs\GenerateShareImage;
use App\Models\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->org = makeOrganization(['slug' => 'sahel', 'name' => 'ONG Sahel']);
    $this->recruiter = memberOf($this->org, OrganizationRole::Recruiter);
});

it('lists open offers of listed organisations only', function () {
    $open = openPosting($this->recruiter, $this->org, ['title' => 'Comptable principal']);
    $draft = draftPosting($this->recruiter, $this->org, ['title' => 'Brouillon secret']);

    $hidden = makeOrganization(['slug' => 'discrete', 'listed_on_job_board' => false]);
    $hiddenPosting = openPosting(memberOf($hidden, OrganizationRole::Recruiter), $hidden, ['title' => 'Poste carrière seulement']);

    $suspended = makeOrganization(['slug' => 'suspendue']);
    openPosting(memberOf($suspended, OrganizationRole::Recruiter), $suspended, ['title' => 'Organisation suspendue']);
    $suspended->update(['status' => OrganizationStatus::Suspended]);

    $this->get(route('job-postings.index'))
        ->assertOk()
        ->assertSee('Comptable principal')
        ->assertDontSee('Brouillon secret')
        ->assertDontSee('Poste carrière seulement')
        ->assertDontSee('Organisation suspendue')
        ->assertHeader('Cache-Control', 'max-age=60, private');

    $this->get(route('career-pages.show', 'discrete'))->assertOk()->assertSee('Poste carrière seulement');
    $this->get(route('career-pages.show', 'suspendue'))->assertNotFound();
    $this->get(route('job-postings.show', $draft->slug))->assertNotFound();
});

it('filters the job board', function () {
    openPosting($this->recruiter, $this->org, ['title' => 'Stagiaire logistique', 'type' => 'internship', 'location' => 'Zinder']);
    openPosting($this->recruiter, $this->org, ['title' => 'Chef de projet', 'type' => 'employment', 'location' => 'Niamey', 'closes_at' => now()->addDays(25)->toDateTimeString()]);

    $this->get(route('job-postings.index', ['type' => 'internship']))->assertSee('Stagiaire logistique')->assertDontSee('Chef de projet');
    $this->get(route('job-postings.index', ['lieu' => 'niam']))->assertSee('Chef de projet')->assertDontSee('Stagiaire logistique');
    $this->get(route('job-postings.index', ['cloture' => 'semaine']))->assertDontSee('Chef de projet');
    $this->get(route('job-postings.index', ['q' => 'ch']))->assertSee('Chef de projet');
    $this->get(route('job-postings.index', ['type' => 'nope', 'tri' => 'x']))->assertOk();
});

it('renders the job board with a bounded number of queries and a light page', function () {
    foreach (range(1, 12) as $i) {
        openPosting($this->recruiter, $this->org, ['title' => "Poste numéro {$i}"]);
    }

    DB::enableQueryLog();
    $response = $this->get(route('job-postings.index'))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThan(12)
        ->and(strlen($response->getContent()))->toBeLessThan(60 * 1024)
        ->and($response->getContent())->not->toContain('filament');
});

it('publishes an offer page with sharing tags and Google Jobs data', function () {
    $posting = openPosting($this->recruiter, $this->org, ['title' => 'Chargé de suivi', 'location' => 'Niamey'], [
        ['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 5120, 'max_files' => 1],
    ]);

    $html = $this->get(route('job-postings.show', $posting->slug))
        ->assertOk()
        ->assertSee('<meta property="og:title"', escape: false)
        ->assertSee('application/ld+json', escape: false)
        ->assertSee(route('job-postings.apply', $posting->slug))
        ->assertSee('CV')
        ->assertSee('PDF, 5 Mo max.')
        ->getContent();

    preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match);
    $data = json_decode($match[1], true);
    expect($data['@type'])->toBe('JobPosting')
        ->and($data['hiringOrganization']['name'])->toBe('ONG Sahel')
        ->and($data['validThrough'])->toBe($posting->closes_at->toIso8601String());

    // The share image was generated when the offer was published.
    expect($posting->fresh()->share_image_path)->toBe('share/'.$posting->ulid.'.png');
    Storage::disk('public')->assertExists('share/'.$posting->ulid.'.png');
    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get('share/'.$posting->ulid.'.png'));
    expect([$width, $height])->toBe([1200, 630]);
});

it('keeps the page of a closed offer, without the apply button nor Google Jobs data', function () {
    $posting = openPosting($this->recruiter, $this->org);
    inOrganization($this->org, fn () => app(CloseJobPosting::class)->handle($this->recruiter, $posting));

    $this->get(route('job-postings.show', $posting->slug))
        ->assertOk()
        ->assertSee(__('showcase.posting.closed'))
        ->assertDontSee(route('job-postings.apply', $posting->slug))
        ->assertDontSee('application/ld+json', escape: false)
        ->assertSee('noindex', escape: false);

    $this->get(route('job-postings.index'))->assertDontSee($posting->title);
});

it('remembers the diffusion channel from the shared link', function () {
    $posting = openPosting($this->recruiter, $this->org);
    $candidate = makeCandidate();

    $this->get(route('job-postings.show', $posting->slug).'?source=Facebook<script>')->assertOk();
    $this->actingAs($candidate)->get(route('job-postings.apply', $posting->slug))->assertRedirect();

    expect(Application::forCandidate($candidate)->sole()->source)->toBe('facebookscript');
});

it('offers to continue a draft from the offer page', function () {
    $posting = openPosting($this->recruiter, $this->org);
    $candidate = makeCandidate();
    $application = app(StartApplication::class)->handle($candidate, $posting);

    $response = $this->actingAs($candidate)
        ->get(route('job-postings.show', $posting->slug))
        ->assertSee(route('candidate.applications.edit', $application->ulid));

    // Personalised: never cached.
    expect($response->headers->get('Cache-Control'))->not->toContain('max-age=60');
});

it('lists open offers and career pages in the sitemap', function () {
    $posting = openPosting($this->recruiter, $this->org);
    $draft = draftPosting($this->recruiter, $this->org, ['title' => 'Pas encore']);

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('job-postings.show', $posting->slug), escape: false)
        ->assertSee(route('career-pages.show', 'sahel'), escape: false)
        ->assertDontSee($draft->slug, escape: false);
});

it('regenerates the share image when a published offer changes', function () {
    $posting = openPosting($this->recruiter, $this->org);
    Queue::fake();

    inOrganization($this->org, fn () => app(UpdateJobPostingDetails::class)->handle($this->recruiter, $posting, [
        'type' => 'employment',
        'title' => 'Nouveau titre',
        'closes_at' => $posting->closes_at->toDateTimeString(),
    ]));

    Queue::assertPushed(GenerateShareImage::class, fn ($job) => $job->organizationId === $this->org->id);
});
