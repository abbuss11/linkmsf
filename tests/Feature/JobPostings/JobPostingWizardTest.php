<?php

use App\Actions\JobPostings\ArchiveJobPosting;
use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\JobPostings\Pages\CreateJobPosting;
use App\Filament\App\Resources\JobPostings\Pages\EditJobPosting;
use App\Filament\App\Resources\JobPostings\Pages\PreviewJobPosting;
use App\Livewire\FormPreview;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use Filament\Schemas\Components\Wizard;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = makeOrganization(['slug' => 'sahel']);
    $this->recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    actingInOrganization($this->recruiter, $this->org);
});

function wizardFor(JobPosting $posting)
{
    return Livewire::test(EditJobPosting::class, ['record' => $posting->ulid]);
}

/**
 * Clicks "Next" on the given step, as the browser does: the current step is
 * held by Alpine and sent with the request, not kept on the server.
 */
function nextFrom($livewire, int $step)
{
    $wizard = $livewire->instance()->form->getComponent(fn ($component) => $component instanceof Wizard);

    return $livewire->call('callSchemaComponentMethod', $wizard->getKey(), 'nextStep', [$step - 1]);
}

it('creates a draft from step 1 and continues at step 2', function () {
    Livewire::test(CreateJobPosting::class)
        ->fillForm([
            'type' => 'internship',
            'title' => 'Stagiaire en communication',
            'description' => '<p>Stage de 6 mois.</p>',
            'closes_at' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $posting = JobPosting::query()->sole();

    expect($posting->status)->toBe(JobPostingStatus::Draft)
        ->and($posting->wizard_step)->toBe(2);

    wizardFor($posting)->assertWizardCurrentStep(2);
});

it('saves each step when moving forward', function () {
    $posting = draftPosting($this->recruiter, $this->org);

    $wizard = wizardFor($posting)->fillForm(['title' => 'Chargé de suivi-évaluation senior']);
    nextFrom($wizard, 1)->assertHasNoFormErrors()->assertNotNotified();

    expect($posting->fresh()->title)->toBe('Chargé de suivi-évaluation senior');

    $wizard
        ->fillForm(['requirements' => [[
            'label' => 'Licence au minimum',
            'usage' => 'eliminatory',
            'field_key' => 'niveau_detudes',
            'operator' => 'education_at_least',
            'value_choice' => 'licence',
        ]]]);
    nextFrom($wizard, 2)->assertHasNoFormErrors()->assertNotNotified();

    $requirement = JobRequirement::query()->where('job_posting_id', $posting->id)->sole();
    expect($requirement->rule)->toEqual(['operator' => 'education_at_least', 'value' => 'licence']);

    $wizard
        ->fillForm([
            'edit_after_submit' => 'until_close',
            'duplicate_policy' => 'single',
            'notify_new_applications' => 'each',
            'documents' => [['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 5120, 'max_files' => 1]],
        ]);
    nextFrom($wizard, 3)->assertHasNoFormErrors()->assertNotNotified();

    $form = ApplicationForm::query()->where('job_posting_id', $posting->id)->sole();
    expect($form->draftSchema()->find('doc_cv'))->not->toBeNull()
        ->and($posting->fresh()->setting('notify_new_applications'))->toBe('each');

    $fields = $wizard->get('data.fields');
    $fields[] = ['type' => 'long_text', 'data' => ['label' => 'Lettre de motivation', 'required' => true, 'max_words' => 300]];

    $wizard->set('data.fields', $fields);
    nextFrom($wizard, 4)->assertHasNoFormErrors()->assertNotified(__('job_postings.notifications.form_saved'));
    $wizard->assertSee(__('job_postings.review.ready'));

    $field = ApplicationForm::query()->where('job_posting_id', $posting->id)->sole()->draftSchema()->find('lettre_de_motivation');
    expect($field)->not->toBeNull()
        ->and($field->validation)->toBe(['max_words' => 300]);
});

it('stays on the step and explains why when a rule does not fit the field', function () {
    $posting = draftPosting($this->recruiter, $this->org);

    $wizard = wizardFor($posting)->fillForm(['requirements' => [[
        'label' => 'Âge',
        'usage' => 'eliminatory',
        'field_key' => 'disponible_immediatement',
        'operator' => 'age_between',
        'min' => 18,
        'max' => 35,
    ]]]);
    // The operator is not offered for a yes/no field: refused by the form
    // itself, before the Action (which checks it again, see unit tests).
    nextFrom($wizard, 2)->assertHasErrors();

    expect(JobRequirement::query()->count())->toBe(0);
});

it('publishes from the wizard header', function () {
    $posting = draftPosting($this->recruiter, $this->org);

    wizardFor($posting)->callAction('publish')->assertNotified(__('job_postings.notifications.published'));

    expect($posting->fresh()->status)->toBe(JobPostingStatus::Published);
});

it('shows why publication is refused', function () {
    $posting = draftPosting($this->recruiter, $this->org);
    $posting->forceFill(['closes_at' => null])->save();

    wizardFor($posting->fresh())
        ->assertSee(__('job_postings.readiness.no_closing_date'))
        ->callAction('publish')
        ->assertNotified();

    expect($posting->fresh()->status)->toBe(JobPostingStatus::Draft);
});

it('previews the form with server-side validation, without storing anything', function () {
    $posting = draftPosting($this->recruiter, $this->org);

    Livewire::test(PreviewJobPosting::class, ['record' => $posting->ulid])
        ->assertSee('form-preview', escape: false);

    Livewire::test(FormPreview::class, ['record' => $posting->ulid])
        ->assertSee('Nom complet')
        ->assertSee('Disponible immédiatement ?')
        ->call('check')
        ->assertHasErrors()
        ->set('answers.nom_complet', 'Awa Moussa')
        ->set('answers.niveau_detudes', 'licence')
        ->set('answers.disponible_immediatement', 'yes')
        ->call('check')
        ->assertHasNoErrors()
        ->assertSee(__('job_postings.preview.valid'));

    expect(Application::queryAcrossOrganizations()->count())->toBe(0);
});

it('serves the form preview inside the panel, with the candidate stylesheet', function () {
    $posting = draftPosting($this->recruiter, $this->org);

    $this->get("/app/sahel/job-postings/{$posting->ulid}/form-preview")
        ->assertOk()
        ->assertSee('Nom complet')
        ->assertDontSee('filament', escape: false);
});

it('opens an archived offer read-only and refuses changes', function () {
    $posting = draftPosting($this->recruiter, $this->org);
    app(ArchiveJobPosting::class)->handle($this->recruiter, $posting);

    $wizard = wizardFor($posting->fresh())
        ->assertOk()
        ->assertActionHidden('publish')
        ->set('data.title', 'Modifié');
    nextFrom($wizard, 1);

    expect($posting->fresh()->title)->not->toBe('Modifié');
});

it('proposes a field key as soon as the label is typed', function () {
    $posting = draftPosting($this->recruiter, $this->org);
    $wizard = wizardFor($posting);
    $fields = $wizard->get('data.fields');
    $uuid = (string) Str::uuid();
    $fields[$uuid] = ['type' => 'short_text', 'data' => ['label' => null, 'key' => null]];

    $wizard->set('data.fields', $fields)
        ->set("data.fields.{$uuid}.data.label", 'Ville de résidence')
        ->assertSet("data.fields.{$uuid}.data.key", 'ville_de_residence');
});
