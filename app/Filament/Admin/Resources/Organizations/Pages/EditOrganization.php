<?php

namespace App\Filament\Admin\Resources\Organizations\Pages;

use App\Actions\Organizations\UpdateOrganization;
use App\Filament\Admin\Resources\Organizations\OrganizationResource;
use App\Filament\Support\RunsActions;
use App\Models\Organization;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Organization $record */
        $record = $this->getRecord();
        $data['limits'] = [
            'max_active_job_postings' => $record->limit('max_active_job_postings'),
            'storage_quota_mb' => $record->limit('storage_quota_mb'),
        ];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Organization $record */
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return RunsActions::attempt(fn () => app(UpdateOrganization::class)->handle($user, $record, $data));
    }
}
