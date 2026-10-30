<?php

namespace App\Actions\Applications;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ApplicationStatus;
use App\Enums\AuditEvent;
use App\Enums\FormFieldType;
use App\Enums\PublicApplicationStatus;
use App\Exceptions\ActionFailed;
use App\Forms\Dynamic\FormSchema;
use App\Forms\Dynamic\RulesBuilder;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationForm;
use App\Models\ApplicationRevision;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;
use App\Support\Applications\AnswerStore;
use App\Support\Applications\ApplicationReference;
use App\Support\Applications\ProfileSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Submits (or re-submits a modified) application, all or nothing (§4.4):
 * deadline checked to the second under a lock, full server-side validation
 * against the current form version, required documents, explicit consent,
 * frozen profile snapshot, readable reference.
 */
class SubmitApplication
{
    public function __construct(
        private readonly RulesBuilder $rulesBuilder,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(User $candidate, Application $application, bool $consent): Application
    {
        Gate::forUser($candidate)->authorize('update', $application);

        if (! $consent) {
            throw ValidationException::withMessages(['consent' => __('applications.errors.consent')]);
        }

        $wasSubmitted = false;

        $application = DB::transaction(function () use ($candidate, $application, &$wasSubmitted) {
            /** @var JobPosting $posting */
            $posting = JobPosting::queryAcrossOrganizations()->lockForUpdate()->findOrFail($application->job_posting_id);
            /** @var Application $application */
            $application = Application::queryAcrossOrganizations()->lockForUpdate()->findOrFail($application->getKey());
            $application->setRelation('jobPosting', $posting);

            // Checked again under the lock: a form opened before the deadline
            // and sent after it is refused (§3.2).
            if (! $application->canStillBeChanged()) {
                throw ActionFailed::because($posting->acceptsApplicationsAt(now()) ? 'applications.errors.already_applied' : 'applications.errors.closed');
            }

            $organization = Organization::query()->findOrFail($posting->organization_id);
            $form = ApplicationForm::queryForOrganization($organization)->where('job_posting_id', $posting->getKey())->with('currentVersion')->firstOrFail();
            $version = $form->currentVersion ?? throw ActionFailed::because('applications.errors.closed');
            $schema = $version->formSchema();

            $answers = AnswerStore::load($application);
            $this->validateAnswers($schema, $answers);
            $this->checkDocuments($application, $schema, $answers);

            $wasSubmitted = $application->isSubmitted();

            if ($wasSubmitted) {
                $this->keepRevision($application, $answers);
            }

            $this->dropStaleAnswers($application, $schema, $answers);

            $application->application_form_version_id = $version->getKey();
            $application->profile_snapshot = ProfileSnapshot::of($candidate);
            $application->reference ??= ApplicationReference::next($organization);
            $application->status = ApplicationStatus::Submitted;
            $application->public_status = PublicApplicationStatus::Received;
            $application->submitted_at ??= now();
            $application->consent_at = now();
            $application->consent_text = __('applications.consent', ['organization' => $organization->name]);
            $application->last_step = 4;
            $application->save();

            $this->audit->handle(
                $wasSubmitted ? AuditEvent::ApplicationUpdated : AuditEvent::ApplicationSubmitted,
                subject: $application,
                causer: $candidate,
                organization: $organization,
                properties: ['reference' => $application->reference, 'job_posting_id' => $posting->getKey(), 'form_version' => $version->version],
            );

            return $application;
        });

        $postingTitle = $application->jobPosting->title;
        $organizationName = (string) Organization::query()->whereKey($application->organization_id)->value('name');

        DB::afterCommit(fn () => $candidate->notify(new ApplicationSubmitted(
            $application->ulid,
            (string) $application->reference,
            $postingTitle,
            $organizationName,
            $wasSubmitted,
        )));

        return $application;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function validateAnswers(FormSchema $schema, array $answers): void
    {
        $rules = $this->rulesBuilder->rules($schema, $answers);

        // Files are checked through their documents below.
        foreach ($schema->inputFields() as $field) {
            if ($field->type === FormFieldType::File) {
                unset($rules['answers.'.$field->key], $rules['answers.'.$field->key.'.*']);
            }
        }

        Validator::make(['answers' => $answers], $rules, [], $this->rulesBuilder->attributes($schema))->validate();
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function checkDocuments(Application $application, FormSchema $schema, array $answers): void
    {
        $documents = ApplicationDocument::queryAcrossOrganizations()
            ->where('application_id', $application->getKey())
            ->get();
        $errors = [];

        foreach ($schema->documentFields() as $field) {
            $complete = $documents->where('field_key', $field->key)->where('status', ApplicationDocument::COMPLETE)->count();

            if (! $schema->isVisible($field, $answers)) {
                continue;
            }

            if ($field->required && $complete === 0) {
                $errors['documents.'.$field->key] = __('applications.errors.document_required', ['document' => $field->label]);
            } elseif ($complete > $field->file['max_files']) {
                $errors['documents.'.$field->key] = __('applications.errors.too_many_files', ['max' => $field->file['max_files']]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Unfinished uploads and files of fields that no longer apply are not part of the application.
        $documents
            ->filter(fn (ApplicationDocument $d) => ! $d->isComplete()
                || ($field = $schema->find($d->field_key)) === null
                || ! $schema->isVisible($field, $answers))
            ->each(fn (ApplicationDocument $d) => RemoveApplicationDocument::purge($d));
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function dropStaleAnswers(Application $application, FormSchema $schema, array $answers): void
    {
        $stale = [];

        foreach (array_keys($answers) as $key) {
            $field = $schema->find($key);

            if ($field === null || ! $schema->isVisible($field, $answers)) {
                $stale[] = $key;
            }
        }

        AnswerStore::forget($application, $stale);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function keepRevision(Application $application, array $answers): void
    {
        $revision = new ApplicationRevision([
            'application_id' => $application->getKey(),
            'application_form_version_id' => $application->application_form_version_id,
            'snapshot' => [
                'answers' => $answers,
                'profile' => $application->profile_snapshot,
                'documents' => ApplicationDocument::queryAcrossOrganizations()
                    ->where('application_id', $application->getKey())
                    ->get(['field_key', 'original_name', 'sha256', 'size'])
                    ->toArray(),
            ],
            'submitted_at' => $application->updated_at ?? now(),
        ]);
        $revision->organization_id = $application->organization_id;
        $revision->save();
    }
}
