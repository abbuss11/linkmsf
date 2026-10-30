<?php

namespace App\Filament\App\Resources\JobPostings\Pages;

use App\Actions\JobPostings\AddApplicationFormField;
use App\Actions\JobPostings\CheckJobPostingReadiness;
use App\Actions\JobPostings\SaveApplicationForm;
use App\Actions\JobPostings\SaveJobRequirements;
use App\Actions\JobPostings\UpdateApplicationSettings;
use App\Actions\JobPostings\UpdateJobPostingDetails;
use App\Filament\App\Resources\JobPostings\JobPostingActions;
use App\Filament\App\Resources\JobPostings\JobPostingResource;
use App\Filament\App\Resources\JobPostings\Schemas\DetailsStep;
use App\Filament\App\Resources\JobPostings\Schemas\FormBuilderStep;
use App\Filament\App\Resources\JobPostings\Schemas\RequirementsStep;
use App\Filament\App\Resources\JobPostings\Schemas\SettingsStep;
use App\Filament\App\Resources\JobPostings\Support\FormBuilderState;
use App\Filament\App\Resources\JobPostings\Support\RequirementState;
use App\Filament\Support\RunsActions;
use App\Forms\Dynamic\FieldDefinition;
use App\Forms\Dynamic\FormSchema;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * The 5-step offer wizard (§3.1). Every "Next" saves the step through its
 * Action, so a draft can be left and resumed at any time.
 */
class EditJobPosting extends EditRecord
{
    protected static string $resource = JobPostingResource::class;

    public function getTitle(): string
    {
        return $this->posting()->title;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $posting = $this->posting();
        $schema = $this->applicationForm()->draftSchema();

        $data['reference'] = $posting->reference;
        $data['requirements'] = JobRequirement::query()
            ->where('job_posting_id', $posting->getKey())
            ->orderBy('position')
            ->get()
            ->map(fn (JobRequirement $requirement) => RequirementState::toState($requirement))
            ->all();

        foreach (['edit_after_submit', 'duplicate_policy', 'instructions', 'confirmation_message', 'notify_new_applications'] as $key) {
            $data[$key] = $posting->setting($key);
        }

        $data['documents'] = array_map(fn (FieldDefinition $field): array => [
            'key' => $field->key,
            'label' => $field->label,
            'help' => $field->help,
            'required' => $field->required,
            'extensions' => $field->file['extensions'],
            'max_kb' => $field->file['max_kb'],
            'max_files' => $field->file['max_files'],
        ], $schema->documentFields());

        $data['fields'] = FormBuilderState::toState($schema);

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        $organization = $this->organization();

        return $schema->components([
            Wizard::make([
                Step::make(__('job_postings.steps.details'))
                    ->schema(DetailsStep::components($organization))
                    ->afterValidation(fn () => $this->saveDetails()),
                Step::make(__('job_postings.steps.requirements'))
                    ->schema([Text::make(__('job_postings.help.requirements')), ...RequirementsStep::components(
                        fn (): FormSchema => $this->applicationForm()->draftSchema(),
                        fn (string $label, string $kind): string => $this->createFieldForRequirement($label, $kind),
                    )])
                    ->afterValidation(fn () => $this->saveRequirements()),
                Step::make(__('job_postings.steps.settings'))
                    ->schema(SettingsStep::components())
                    ->afterValidation(fn () => $this->saveSettings()),
                Step::make(__('job_postings.steps.form'))
                    ->schema([
                        Text::make(__('job_postings.help.form')),
                        ...FormBuilderStep::components(
                            fn (): array => $this->applicationForm()->publishedKeyTypes(),
                            fn (): array => $this->conditionSources(),
                        ),
                        Actions::make([JobPostingActions::preview($this->posting())]),
                    ])
                    ->afterValidation(fn () => $this->saveForm()),
                Step::make(__('job_postings.steps.review'))
                    ->schema([
                        View::make('filament.app.job-postings.review')
                            ->viewData(fn (): array => $this->reviewData()),
                    ]),
            ])
                ->startOnStep(fn (): int => min(max($this->posting()->wizard_step, 1), 5))
                // Archived offers (or offers the user may only view) open read-only.
                ->disabled(fn (): bool => ! $this->canUpdate())
                ->columnSpanFull(),
        ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return JobPostingActions::all($this);
    }

    public function saveDetails(): void
    {
        if (! $this->canUpdate()) {
            return;
        }

        $state = $this->stepState(DetailsStep::keys());

        RunsActions::attempt(fn () => app(UpdateJobPostingDetails::class)->handle($this->actor(), $this->posting(), $state));
        $this->refreshPosting();
        $this->data['reference'] = $this->posting()->reference;
    }

    public function saveRequirements(): void
    {
        if (! $this->canUpdate()) {
            return;
        }

        $rows = RequirementState::fromState((array) ($this->data['requirements'] ?? []));

        RunsActions::attempt(fn () => app(SaveJobRequirements::class)->handle($this->actor(), $this->posting(), $rows));
        $this->refreshPosting();
    }

    public function saveSettings(): void
    {
        if (! $this->canUpdate()) {
            return;
        }

        $state = $this->stepState(['edit_after_submit', 'duplicate_policy', 'instructions', 'confirmation_message', 'notify_new_applications', 'documents']);
        $state['documents'] = array_values((array) ($state['documents'] ?? []));

        RunsActions::attempt(fn () => app(UpdateApplicationSettings::class)->handle($this->actor(), $this->posting(), $state));
        $this->refreshPosting();
    }

    public function saveForm(): void
    {
        if (! $this->canUpdate()) {
            return;
        }

        $fields = FormBuilderState::fromState(array_values((array) ($this->data['fields'] ?? [])));

        RunsActions::attempt(fn () => app(SaveApplicationForm::class)->handle($this->actor(), $this->posting(), $fields));
        $this->refreshPosting();
        // Show generated keys and normalised options.
        $this->data['fields'] = FormBuilderState::toState($this->applicationForm()->draftSchema());

        Notification::make()->success()->title(__('job_postings.notifications.form_saved'))->send();
    }

    private function createFieldForRequirement(string $label, string $kind): string
    {
        $key = RunsActions::attempt(fn () => app(AddApplicationFormField::class)->handle($this->actor(), $this->posting(), $label, $kind));
        $this->data['fields'] = FormBuilderState::toState($this->applicationForm()->draftSchema());

        return $key;
    }

    /**
     * @return array<string, string>
     */
    private function conditionSources(): array
    {
        $sources = [];

        foreach ((array) ($this->data['fields'] ?? []) as $block) {
            $key = $block['data']['key'] ?? null;

            if (filled($key) && ! in_array($block['type'] ?? null, ['section', 'help_text'], true)) {
                $sources[(string) $key] = (string) ($block['data']['label'] ?? $key);
            }
        }

        return $sources;
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewData(): array
    {
        $posting = $this->posting();
        $form = $this->applicationForm();

        return [
            'posting' => $posting,
            'organization' => $this->organization(),
            'report' => app(CheckJobPostingReadiness::class)->handle($posting),
            'schema' => $form->draftSchema(),
            'currentVersion' => $form->currentVersion?->version,
            'hasUnpublishedChanges' => $form->currentVersion !== null && $form->hasUnpublishedChanges(),
            'requirementsCount' => JobRequirement::query()->where('job_posting_id', $posting->getKey())->count(),
        ];
    }

    /**
     * Dehydrated state (dates converted from the organisation's timezone to UTC).
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function stepState(array $keys): array
    {
        return array_intersect_key($this->form->getStateSnapshot(), array_flip($keys));
    }

    private function canUpdate(): bool
    {
        return Gate::allows('update', $this->posting());
    }

    public function posting(): JobPosting
    {
        $record = $this->getRecord();
        abort_unless($record instanceof JobPosting, 404);

        return $record;
    }

    private function refreshPosting(): void
    {
        $this->posting()->refresh();
    }

    public function applicationForm(): ApplicationForm
    {
        return ApplicationForm::query()->where('job_posting_id', $this->posting()->getKey())->with('currentVersion')->firstOrFail();
    }

    public function organization(): Organization
    {
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Organization, 404);

        return $tenant;
    }

    public function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
