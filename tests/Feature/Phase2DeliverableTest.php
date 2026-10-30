<?php

use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\JobPostings\Pages\CreateJobPosting;
use App\Filament\App\Resources\JobPostings\Pages\EditJobPosting;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use Filament\Schemas\Components\Wizard;
use Livewire\Livewire;

/*
 * Livrable de la phase 2 : un recruteur publie une offre avec son formulaire.
 */
it('lets a recruiter publish an offer with its form', function () {
    $org = makeOrganization(['slug' => 'sahel', 'timezone' => 'Africa/Niamey']);
    $recruiter = memberOf($org, OrganizationRole::Recruiter);
    $jury = memberOf($org, OrganizationRole::Jury);
    actingInOrganization($recruiter, $org);

    // Step 1: general information.
    Livewire::test(CreateJobPosting::class)
        ->fillForm([
            'type' => 'employment',
            'title' => 'Agent de saisie',
            'description' => '<p>Saisie des données d’enquête.</p>',
            'location' => 'Niamey',
            'education_level' => 'bac',
            'closes_at' => now()->addDays(15)->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $posting = JobPosting::query()->sole();
    $wizard = Livewire::test(EditJobPosting::class, ['record' => $posting->ulid]);
    $next = function (int $step) use ($wizard) {
        $key = $wizard->instance()->form->getComponent(fn ($c) => $c instanceof Wizard)->getKey();

        return $wizard->call('callSchemaComponentMethod', $key, 'nextStep', [$step - 1]);
    };

    // Step 4 first in practice: the questions the criteria will rely on.
    $wizard->set('data.fields', [
        ['type' => 'section', 'data' => ['label' => 'Vous']],
        ['type' => 'short_text', 'data' => ['label' => 'Nom complet', 'required' => true, 'profile_field' => 'full_name']],
        ['type' => 'phone', 'data' => ['label' => 'Téléphone', 'required' => true, 'profile_field' => 'phone']],
        ['type' => 'select', 'data' => ['label' => 'Niveau d’études', 'required' => true, 'profile_field' => 'education_level']],
        ['type' => 'yes_no', 'data' => ['label' => 'Avez-vous déjà fait de la saisie ?', 'key' => 'experience_saisie', 'required' => true]],
        ['type' => 'number', 'data' => ['label' => 'Vitesse de frappe (mots/minute)', 'visible_if_field' => 'experience_saisie', 'visible_if_operator' => 'equals', 'visible_if_value' => 'yes', 'min' => 0, 'max' => 200]],
    ]);
    $next(4)->assertHasNoFormErrors();

    // Step 2: an eliminatory criterion on the education level.
    $wizard->set('data.requirements', [[
        'label' => 'Baccalauréat au minimum',
        'usage' => 'eliminatory',
        'field_key' => 'niveau_detudes',
        'operator' => 'education_at_least',
        'value_choice' => 'bac',
    ]]);
    $next(2)->assertHasNoFormErrors();

    // Step 3: documents and rules.
    $wizard->fillForm([
        'edit_after_submit' => 'never',
        'duplicate_policy' => 'single',
        'notify_new_applications' => 'daily',
        'documents' => [['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 5120, 'max_files' => 1]],
    ]);
    $next(3)->assertHasNoFormErrors();

    // Step 5: publication.
    $wizard->callAction('publish')->assertNotified(__('job_postings.notifications.published'));

    $posting->refresh();
    $form = ApplicationForm::query()->where('job_posting_id', $posting->id)->with('currentVersion')->sole();
    $version = $form->currentVersion->formSchema();

    expect($posting->status)->toBe(JobPostingStatus::Published)
        ->and($posting->acceptsApplicationsAt(now()))->toBeTrue()
        ->and($form->currentVersion->version)->toBe(1)
        ->and($version->keys())->toBe(['nom_complet', 'telephone', 'niveau_detudes', 'experience_saisie', 'vitesse_de_frappe_motsminute', 'doc_cv'])
        ->and($version->find('vitesse_de_frappe_motsminute')->visibleIf['field'])->toBe('experience_saisie');

    // The jury has no access to offer management.
    $this->flushSession();
    $this->actingAs($jury)->get("/app/sahel/job-postings/{$posting->ulid}/edit")->assertForbidden();
});
