<?php

use App\Actions\JobPostings\PublishFormChanges;
use App\Actions\JobPostings\PublishJobPosting;
use App\Actions\JobPostings\SaveApplicationForm;
use App\Actions\JobPostings\UpdateApplicationSettings;
use App\Enums\OrganizationRole;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->org = makeOrganization();
    $this->recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->posting = draftPosting($this->recruiter, $this->org);
    actingInOrganization($this->recruiter, $this->org);
    app(PublishJobPosting::class)->handle($this->recruiter, $this->posting);
    $this->form = fn () => ApplicationForm::query()->where('job_posting_id', $this->posting->id)->with('currentVersion')->sole();
});

it('creates a new version only when the form changed', function () {
    $v1 = ($this->form)()->currentVersion;

    expect(app(PublishFormChanges::class)->handle($this->recruiter, $this->posting->fresh())->id)->toBe($v1->id);

    $questions = array_map(fn ($field) => $field->toArray(), ($this->form)()->draftSchema()->nonDocumentFields());
    $questions[] = ['type' => 'long_text', 'label' => 'Motivation', 'required' => true];
    app(SaveApplicationForm::class)->handle($this->recruiter, $this->posting, $questions);

    expect(($this->form)()->hasUnpublishedChanges())->toBeTrue();

    $v2 = app(PublishFormChanges::class)->handle($this->recruiter, $this->posting->fresh());

    expect($v2->version)->toBe(2)
        ->and(($this->form)()->current_version_id)->toBe($v2->id)
        ->and(($this->form)()->hasUnpublishedChanges())->toBeFalse();
});

it('keeps old versions readable after a field is removed', function () {
    $v1 = ($this->form)()->currentVersion;
    app(SaveApplicationForm::class)->handle($this->recruiter, $this->posting, [
        ['type' => 'short_text', 'label' => 'Nom complet', 'key' => 'nom_complet', 'required' => true],
    ]);
    app(PublishFormChanges::class)->handle($this->recruiter, $this->posting->fresh());

    $old = ApplicationFormVersion::query()->find($v1->id)->formSchema();

    expect($old->find('disponible_immediatement'))->not->toBeNull()
        ->and(($this->form)()->currentVersion->formSchema()->find('disponible_immediatement'))->toBeNull();
});

it('never modifies or deletes a published version', function () {
    $version = ($this->form)()->currentVersion;

    expect(fn () => $version->update(['schema' => ['fields' => []]]))->toThrow(LogicException::class)
        ->and(fn () => $version->delete())->toThrow(LogicException::class);
});

it('refuses to change the type of a published key', function () {
    expect(fn () => app(SaveApplicationForm::class)->handle($this->recruiter, $this->posting, [
        ['type' => 'number', 'label' => 'Disponible', 'key' => 'disponible_immediatement'],
    ]))->toThrow(ValidationException::class);
});

it('generates stable keys from labels and keeps existing ones', function () {
    $keys = ($this->form)()->draftSchema()->keys();

    expect($keys)->toBe(['nom_complet', 'niveau_detudes', 'disponible_immediatement']);
});

it('fills education level options from the platform list', function () {
    $field = ($this->form)()->draftSchema()->find('niveau_detudes');

    expect($field->optionValues())->toBe(['none', 'cepe', 'bepc', 'bac', 'bac2', 'licence', 'master', 'doctorate']);
});

it('turns requested documents into file fields placed after the questions', function () {
    app(UpdateApplicationSettings::class)->handle($this->recruiter, $this->posting, [
        'edit_after_submit' => 'until_close',
        'duplicate_policy' => 'single',
        'notify_new_applications' => 'daily',
        'documents' => [
            ['label' => 'CV', 'required' => true, 'extensions' => ['pdf'], 'max_kb' => 5120, 'max_files' => 1],
            ['label' => 'Diplômes', 'required' => false, 'extensions' => ['pdf', 'jpg'], 'max_kb' => 2048, 'max_files' => 3],
        ],
    ]);

    $schema = ($this->form)()->draftSchema();
    $last = array_slice($schema->fields, -2);

    expect(array_map(fn ($f) => [$f->key, $f->type->value, $f->document], $last))->toBe([
        ['doc_cv', 'file', true],
        ['doc_diplomes', 'file', true],
    ])->and($this->posting->fresh()->editAfterSubmitPolicy()->value)->toBe('until_close');

    // Saving the questions again keeps the documents.
    app(SaveApplicationForm::class)->handle($this->recruiter, $this->posting, array_map(fn ($f) => $f->toArray(), $schema->nonDocumentFields()));
    expect(count(($this->form)()->draftSchema()->documentFields()))->toBe(2);
});

it('refuses document constraints above the platform caps', function () {
    app(UpdateApplicationSettings::class)->handle($this->recruiter, $this->posting, [
        'edit_after_submit' => 'never',
        'duplicate_policy' => 'single',
        'notify_new_applications' => 'none',
        'documents' => [['label' => 'CV', 'required' => true, 'extensions' => ['exe'], 'max_kb' => 999999, 'max_files' => 50]],
    ]);
})->throws(ValidationException::class);
