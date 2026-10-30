<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\FormFieldType;
use App\Forms\Dynamic\FormSchema;
use App\Forms\Dynamic\SchemaValidator;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Wizard step 4: saves the questions of the form (working copy). Requested
 * documents (step 3) are kept at the end. Nothing changes for candidates
 * until a version is published.
 */
class SaveApplicationForm
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly SchemaValidator $schemaValidator,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $questions
     */
    public function handle(User $actor, JobPosting $posting, array $questions): ApplicationForm
    {
        Gate::forUser($actor)->authorize('update', $posting);

        return DB::transaction(function () use ($actor, $posting, $questions) {
            $form = ApplicationForm::queryForOrganization($posting->organization()->firstOrFail())
                ->where('job_posting_id', $posting->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Files are only requested through the documents of step 3.
            $questions = array_values(array_filter(
                $questions,
                fn (array $field) => ($field['type'] ?? null) !== FormFieldType::File->value,
            ));
            $documents = array_map(fn ($field) => $field->toArray(), $form->draftSchema()->documentFields());

            // Keys are generated on the server from the label when missing.
            $usedKeys = array_column($documents, 'key');
            foreach ($questions as $field) {
                if (filled($field['key'] ?? null)) {
                    $usedKeys[] = (string) $field['key'];
                }
            }
            $questions = array_map(function (array $field) use (&$usedKeys): array {
                unset($field['document']);
                $type = FormFieldType::tryFrom((string) ($field['type'] ?? ''));

                if ($type?->isInput() && blank($field['key'] ?? null)) {
                    $field['key'] = FormSchema::generateKey((string) ($field['label'] ?? ''), $usedKeys);
                    $usedKeys[] = $field['key'];
                }

                return $field;
            }, $questions);
            $raw = FormSchema::fromArray(['fields' => [...$questions, ...$documents]])->normalized()->toArray();

            $errors = $this->schemaValidator->validate(['fields' => [...$questions, ...$documents]], $form->publishedKeyTypes());

            if ($errors !== []) {
                throw ValidationException::withMessages(['fields' => $errors]);
            }

            $form->schema = $raw;
            $form->updated_by = $actor->getKey();
            $form->save();

            $posting->wizard_step = max($posting->wizard_step, 5);
            $posting->save();

            $this->audit->handle(
                AuditEvent::ApplicationFormUpdated,
                subject: $posting,
                causer: $actor,
                organization: $posting->organization_id,
                properties: ['fields' => count($raw['fields'])],
            );

            return $form;
        });
    }
}
