<?php

namespace App\Filament\Admin\Resources\Organizations\Pages;

use App\Actions\Organizations\CreateOrganization as CreateOrganizationAction;
use App\Filament\Admin\Resources\Organizations\OrganizationResource;
use App\Filament\Support\RunsActions;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        /** @var array{name: string, slug: string, timezone?: string|null, country?: string|null, admin_email?: string|null} $data */
        return RunsActions::attempt(fn () => app(CreateOrganizationAction::class)->handle($user, $data));
    }
}
