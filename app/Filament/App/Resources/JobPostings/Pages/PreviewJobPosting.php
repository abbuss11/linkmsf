<?php

namespace App\Filament\App\Resources\JobPostings\Pages;

use App\Filament\App\Resources\JobPostings\JobPostingResource;
use App\Forms\Dynamic\FilamentFormRenderer;
use App\Forms\Dynamic\FormSchema;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * Shows the form exactly as candidates will see it, on a computer or a phone,
 * with server-side validation (§3.1 step 4). Nothing is stored.
 *
 * @property-read Schema $form
 */
class PreviewJobPosting extends Page
{
    use InteractsWithRecord;

    protected static string $resource = JobPostingResource::class;

    protected string $view = 'filament.app.job-postings.preview';

    /** @var array<string, mixed> */
    public ?array $answers = [];

    /** Published version to show; the working copy when null. */
    #[Url]
    public ?int $version = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(Gate::allows('view', $this->record), 403);

        $this->form->fill();
    }

    public function getTitle(): string
    {
        return __('job_postings.preview.title', ['title' => $this->posting()->title]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('answers')
            ->components(app(FilamentFormRenderer::class)->components($this->formSchema()));
    }

    public function formSchema(): FormSchema
    {
        $form = ApplicationForm::query()->where('job_posting_id', $this->posting()->getKey())->firstOrFail();

        if ($this->version === null) {
            return $form->draftSchema();
        }

        return ApplicationFormVersion::query()
            ->where('application_form_id', $form->getKey())
            ->where('version', $this->version)
            ->firstOrFail()
            ->formSchema();
    }

    /**
     * @return array<int, int>
     */
    public function versions(): array
    {
        $form = ApplicationForm::query()->where('job_posting_id', $this->posting()->getKey())->firstOrFail();

        return ApplicationFormVersion::query()
            ->where('application_form_id', $form->getKey())
            ->orderBy('version')
            ->pluck('version', 'version')
            ->all();
    }

    public function check(): void
    {
        $this->form->getState();

        Notification::make()->success()->title(__('job_postings.preview.valid'))->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('job_postings.actions.back_to_wizard'))
                ->color('gray')
                ->url(JobPostingResource::getUrl('edit', ['record' => $this->posting()])),
        ];
    }

    public function posting(): JobPosting
    {
        $record = $this->getRecord();
        abort_unless($record instanceof JobPosting, 404);

        return $record;
    }
}
