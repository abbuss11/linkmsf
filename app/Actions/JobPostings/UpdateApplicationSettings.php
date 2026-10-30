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
use App\Support\JobPostings\JobPostingRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Wizard step 3: application settings and requested documents. Documents are
 * stored as file fields of the form (flag "document"), appended after the
 * questions, so the form remains the single description of what is asked.
 */
class UpdateApplicationSettings
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly SchemaValidator $schemaValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, JobPosting $posting, array $data): JobPosting
    {
        Gate::forUser($actor)->authorize('update', $posting);

        $data = Validator::make($data, JobPostingRules::settings())->validate();
        $form = ApplicationForm::queryForOrganization($posting->organization()->firstOrFail())
            ->where('job_posting_id', $posting->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $schema = $form->draftSchema();
        $questions = array_map(fn ($field) => $field->toArray(), $schema->nonDocumentFields());
        $existingKeys = $schema->keys();
        $documents = [];

        foreach ((array) ($data['documents'] ?? []) as $document) {
            $key = filled($document['key'] ?? null) && in_array($document['key'], $existingKeys, true)
                ? $document['key']
                : FormSchema::generateKey('doc_'.$document['label'], [...$existingKeys, ...array_column($documents, 'key')]);

            $documents[] = [
                'key' => $key,
                'type' => FormFieldType::File->value,
                'label' => $document['label'],
                'help' => $document['help'] ?? null,
                'required' => (bool) ($document['required'] ?? false),
                'file' => [
                    'extensions' => array_values($document['extensions']),
                    'max_kb' => (int) $document['max_kb'],
                    'max_files' => (int) $document['max_files'],
                ],
                'document' => true,
            ];
        }

        $newSchema = ['fields' => [...$questions, ...$documents]];
        $errors = $this->schemaValidator->validate($newSchema, $form->publishedKeyTypes());

        if ($errors !== []) {
            throw ValidationException::withMessages(['documents' => $errors]);
        }

        return DB::transaction(function () use ($actor, $posting, $data, $form, $newSchema) {
            $posting->settings = array_merge(JobPosting::DEFAULT_SETTINGS, (array) $posting->settings, [
                'edit_after_submit' => $data['edit_after_submit'],
                'duplicate_policy' => $data['duplicate_policy'],
                'instructions' => $data['instructions'] ?? null,
                'confirmation_message' => $data['confirmation_message'] ?? null,
                'notify_new_applications' => $data['notify_new_applications'],
            ]);
            $posting->wizard_step = max($posting->wizard_step, 4);
            $posting->save();

            $form->schema = FormSchema::fromArray($newSchema)->toArray();
            $form->updated_by = $actor->getKey();
            $form->save();

            $this->audit->handle(
                AuditEvent::JobPostingUpdated,
                subject: $posting,
                causer: $actor,
                organization: $posting->organization_id,
                properties: ['changed' => ['settings', 'documents']],
            );

            return $posting;
        });
    }
}
