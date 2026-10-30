<?php

namespace App\Livewire;

use App\Actions\Applications\AttachProfileDocument;
use App\Actions\Applications\RemoveApplicationDocument;
use App\Actions\Applications\SaveApplicationStep;
use App\Actions\Applications\SubmitApplication;
use App\Enums\FormFieldType;
use App\Exceptions\ActionFailed;
use App\Forms\Dynamic\FieldDefinition;
use App\Forms\Dynamic\FormSchema;
use App\Forms\Dynamic\RulesBuilder;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\CandidateDocument;
use App\Models\Organization;
use App\Models\User;
use App\Support\Applications\AnswerStore;
use App\Support\Applications\ApplicationSteps;
use App\Support\Applications\ProfileFieldValues;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Application in 4 steps (§4.4): profile, questions, documents, review.
 * One request per step on slow connections; the draft is saved at every
 * step and resumes on any device. Every call re-checks authorisation.
 */
#[Layout('components.layouts.public')]
class ApplicationWizard extends Component
{
    #[Locked]
    public string $applicationUlid;

    public int $step = 1;

    /** @var array<string, mixed> */
    public array $answers = [];

    public bool $updateProfile = true;

    public bool $consent = false;

    public function mount(Application $application): void
    {
        $this->applicationUlid = $application->ulid;

        if (Gate::forUser($this->candidate())->denies('update', $this->application())) {
            session()->flash('status', __('applications.errors.not_modifiable'));
            $this->redirectRoute('candidate.applications.show', $application->ulid);

            return;
        }

        $schema = $this->schema();
        $answers = AnswerStore::load($application);

        foreach ($schema->inputFields() as $field) {
            if (array_key_exists((string) $field->key, $answers)) {
                continue;
            }

            $answers[$field->key] = match (true) {
                $field->profileField !== null => ProfileFieldValues::read($this->candidate(), $field->profileField),
                $field->type === FormFieldType::CheckboxList => [],
                default => null,
            };
        }

        $this->answers = $answers;
        $this->step = min(max($application->last_step, 1), ApplicationSteps::REVIEW);

        if ($this->stepFields($this->step) === [] && $this->step < ApplicationSteps::REVIEW) {
            $this->step = $this->nextNonEmptyStep($this->step);
        }
    }

    public function next(): void
    {
        $this->resetErrorBag();

        if ($this->save(validate: true)) {
            $this->step = $this->nextNonEmptyStep($this->step);
        }
    }

    public function previous(): void
    {
        $this->resetErrorBag();
        $this->save(validate: false);

        do {
            $this->step = max(ApplicationSteps::PROFILE, $this->step - 1);
        } while ($this->step > ApplicationSteps::PROFILE && $this->stepFields($this->step) === []);
    }

    public function goTo(int $step): void
    {
        if ($step >= ApplicationSteps::PROFILE && $step <= $this->application()->last_step && $step !== $this->step) {
            $this->resetErrorBag();
            $this->save(validate: false);
            $this->step = $step;
        }
    }

    public function removeDocument(string $documentUlid): void
    {
        $document = ApplicationDocument::queryAcrossOrganizations()
            ->where('application_id', $this->application()->getKey())
            ->where('ulid', $documentUlid)
            ->firstOrFail();

        $this->attempt(fn () => app(RemoveApplicationDocument::class)->handle($this->candidate(), $document));
    }

    public function attachProfileDocument(string $fieldKey, string $documentUlid): void
    {
        $document = $this->candidate()->candidateDocuments()->where('ulid', $documentUlid)->firstOrFail();

        $this->attempt(fn () => app(AttachProfileDocument::class)->handle($this->candidate(), $this->application(), $fieldKey, $document));
    }

    public function submit(): void
    {
        $this->resetErrorBag();
        $application = $this->application();

        $limiterKey = 'application-submissions:'.$this->candidate()->getKey();
        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('link.rate_limits.application_submissions_per_hour'))) {
            $this->addError('form', __('applications.errors.too_many_attempts'));

            return;
        }
        RateLimiter::hit($limiterKey, 3600);

        try {
            app(SubmitApplication::class)->handle($this->candidate(), $application, $this->consent);
        } catch (ValidationException $exception) {
            $this->jumpToFirstError(array_keys($exception->errors()));

            throw $exception;
        } catch (ActionFailed $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        } catch (AuthorizationException) {
            $this->addError('form', __('applications.errors.not_modifiable'));

            return;
        }

        $this->redirectRoute('candidate.applications.confirmation', $application->ulid);
    }

    public function render(): View
    {
        $application = $this->application();
        $schema = $this->schema();
        $posting = $application->jobPosting;
        $organization = Organization::query()->findOrFail($application->organization_id);

        return view('livewire.application-wizard', [
            'application' => $application,
            'posting' => $posting,
            'organization' => $organization,
            'schema' => $schema,
            'fields' => $this->stepFields($this->step),
            'documents' => $this->documents(),
            'profileDocuments' => $this->step === ApplicationSteps::DOCUMENTS
                ? $this->candidate()->candidateDocuments()->get()
                : new Collection,
            'conditions' => collect($schema->fields)
                ->filter(fn ($field) => $field->visibleIf !== null)
                ->mapWithKeys(fn ($field) => [$field->key => $field->visibleIf])
                ->all(),
            'steps' => collect([ApplicationSteps::PROFILE, ApplicationSteps::QUESTIONS, ApplicationSteps::DOCUMENTS, ApplicationSteps::REVIEW])
                ->filter(fn (int $step) => $step === ApplicationSteps::REVIEW || $this->stepFields($step) !== [])
                ->values()
                ->all(),
        ])->title(__('applications.wizard.title', ['title' => $posting->title]));
    }

    private function save(bool $validate): bool
    {
        if ($this->step === ApplicationSteps::REVIEW || $this->step === ApplicationSteps::DOCUMENTS) {
            if ($validate && $this->step === ApplicationSteps::DOCUMENTS) {
                return $this->checkRequiredDocuments();
            }

            return true;
        }

        return $this->attempt(fn () => app(SaveApplicationStep::class)->handle(
            $this->candidate(),
            $this->application(),
            $this->step,
            $this->answers,
            validate: $validate,
            updateProfile: $this->step === ApplicationSteps::PROFILE && $this->updateProfile,
        ));
    }

    private function checkRequiredDocuments(): bool
    {
        $schema = $this->schema();
        $documents = $this->documents();
        $ok = true;

        foreach ($schema->documentFields() as $field) {
            if ($field->required && $schema->isVisible($field, $this->answers)
                && $documents->where('field_key', $field->key)->where('status', ApplicationDocument::COMPLETE)->isEmpty()) {
                $this->addError('documents.'.$field->key, __('applications.errors.document_required', ['document' => $field->label]));
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function attempt(callable $callback): bool
    {
        try {
            $callback();

            return true;
        } catch (ActionFailed $exception) {
            $this->addError('form', $exception->getMessage());

            return false;
        } catch (AuthorizationException) {
            // Deadline passed while the page was open: every action checks it.
            $this->addError('form', __('applications.errors.not_modifiable'));

            return false;
        }
    }

    /**
     * @param  list<string>  $errorKeys
     */
    private function jumpToFirstError(array $errorKeys): void
    {
        $schema = $this->schema();

        foreach ([ApplicationSteps::PROFILE, ApplicationSteps::QUESTIONS, ApplicationSteps::DOCUMENTS] as $step) {
            $keys = ApplicationSteps::keys($schema, $step);

            foreach ($errorKeys as $errorKey) {
                if (in_array(explode('.', $errorKey)[1] ?? '', $keys, true)) {
                    $this->step = $step;

                    return;
                }
            }
        }
    }

    private function nextNonEmptyStep(int $step): int
    {
        do {
            $step++;
        } while ($step < ApplicationSteps::REVIEW && $this->stepFields($step) === []);

        return min($step, ApplicationSteps::REVIEW);
    }

    /**
     * @return list<FieldDefinition>
     */
    private function stepFields(int $step): array
    {
        return ApplicationSteps::fields($this->schema(), $step);
    }

    /**
     * @return Collection<int, ApplicationDocument>
     */
    private function documents(): Collection
    {
        return ApplicationDocument::queryAcrossOrganizations()
            ->where('application_id', $this->application()->getKey())
            ->where('status', ApplicationDocument::COMPLETE)
            ->orderBy('id')
            ->get();
    }

    private function schema(): FormSchema
    {
        return FormSchema::fromArray($this->application()->formVersion?->schema);
    }

    private function application(): Application
    {
        $application = Application::forCandidate($this->candidate())
            ->where('ulid', $this->applicationUlid)
            ->with(['jobPosting', 'formVersion'])
            ->firstOrFail();

        // Reading needs ownership only; every change goes through an Action
        // that checks the "update" ability (deadline, edit policy) itself.
        abort_unless(Gate::forUser($this->candidate())->allows('view', $application), 403);

        return $application;
    }

    private function candidate(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->isCandidate(), 403);

        return $user;
    }

    /**
     * Profile documents usable for a file field (format and size).
     *
     * @param  Collection<int, CandidateDocument>  $documents
     * @return Collection<int, CandidateDocument>
     */
    public static function compatibleProfileDocuments(Collection $documents, FieldDefinition $field): Collection
    {
        $mimes = app(RulesBuilder::class)->mimeTypes($field->file['extensions']);

        return $documents->filter(fn (CandidateDocument $d) => in_array($d->mime, $mimes, true) && $d->size <= $field->file['max_kb'] * 1024)->values();
    }
}
