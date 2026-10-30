<?php

namespace App\Filament\App\Resources\JobPostings\Pages;

use App\Actions\JobPostings\CreateJobPostingDraft;
use App\Filament\App\Resources\JobPostings\JobPostingResource;
use App\Filament\App\Resources\JobPostings\Schemas\DetailsStep;
use App\Filament\Support\RunsActions;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Wizard step 1 for a new offer: creates the draft, then continues in the
 * edit wizard at step 2.
 */
class CreateJobPosting extends CreateRecord
{
    protected static string $resource = JobPostingResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components(DetailsStep::components($this->organization()));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return RunsActions::attempt(fn () => app(CreateJobPostingDraft::class)->handle($user, $this->organization(), $data));
    }

    protected function getRedirectUrl(): string
    {
        return JobPostingResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('job_postings.notifications.draft_created');
    }

    private function organization(): Organization
    {
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Organization, 404);

        return $tenant;
    }
}
