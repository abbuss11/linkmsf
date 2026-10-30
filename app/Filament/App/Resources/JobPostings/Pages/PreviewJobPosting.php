<?php

namespace App\Filament\App\Resources\JobPostings\Pages;

use App\Filament\App\Resources\JobPostings\JobPostingResource;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

/**
 * Shows the form exactly as candidates will see it, on a computer or a phone,
 * with server-side validation (§3.1 step 4): the form itself is drawn by
 * FormPreview, with the candidate Blade components, in an iframe. Nothing is
 * stored.
 */
class PreviewJobPosting extends Page
{
    use InteractsWithRecord;

    protected static string $resource = JobPostingResource::class;

    protected string $view = 'filament.app.job-postings.preview';

    /** Published version to show; the working copy when null. */
    #[Url]
    public ?int $version = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(Gate::allows('view', $this->record), 403);
    }

    public function previewUrl(): string
    {
        return route('filament.app.form-preview', array_filter([
            'tenant' => Filament::getTenant(),
            'record' => $this->posting()->ulid,
            'version' => $this->version,
        ]));
    }

    public function getTitle(): string
    {
        return __('job_postings.preview.title', ['title' => $this->posting()->title]);
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
