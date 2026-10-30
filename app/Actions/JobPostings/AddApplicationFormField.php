<?php

namespace App\Actions\JobPostings;

use App\Enums\FormFieldType;
use App\Enums\ProfileField;
use App\Exceptions\ActionFailed;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\User;

/**
 * Wizard step 2 shortcut: creates the form field an eliminatory criterion
 * needs ("Créer le champ correspondant") and returns its key.
 */
class AddApplicationFormField
{
    public const KINDS = ['yes_no', 'number', 'date', 'education_level', 'short_text'];

    public function __construct(private readonly SaveApplicationForm $saveApplicationForm) {}

    public function handle(User $actor, JobPosting $posting, string $label, string $kind): string
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw ActionFailed::because('job_postings.errors.field_kind');
        }

        $form = ApplicationForm::queryForOrganization($posting->organization()->firstOrFail())
            ->where('job_posting_id', $posting->getKey())
            ->firstOrFail();

        $questions = array_map(fn ($field) => $field->toArray(), $form->draftSchema()->nonDocumentFields());
        $before = array_column($questions, 'key');

        $questions[] = $kind === 'education_level'
            ? ['type' => FormFieldType::Select->value, 'label' => $label, 'required' => true, 'profile_field' => ProfileField::EducationLevel->value]
            : ['type' => $kind, 'label' => $label, 'required' => true];

        $saved = $this->saveApplicationForm->handle($actor, $posting, $questions);
        $newKeys = array_values(array_diff($saved->draftSchema()->keys(), $before, array_column(array_map(fn ($f) => $f->toArray(), $saved->draftSchema()->documentFields()), 'key')));

        return (string) ($newKeys[0] ?? '');
    }
}
