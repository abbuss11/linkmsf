<?php

namespace App\Filament\App\Resources\Invitations\Pages;

use App\Actions\Members\InviteMember;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\Invitations\InvitationResource;
use App\Filament\Support\RunsActions;
use App\Models\Invitation;
use App\Models\Organization;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('invitations.actions.invite'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->authorize(fn (): bool => Gate::allows('create', [Invitation::class, $this->organization()]))
                ->schema([
                    TextInput::make('email')
                        ->label(__('invitations.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Select::make('role')
                        ->label(__('invitations.fields.role'))
                        ->options(OrganizationRole::class)
                        ->enum(OrganizationRole::class)
                        ->default(OrganizationRole::Recruiter->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    RunsActions::attempt(fn () => app(InviteMember::class)->handle(
                        InvitationResource::actor(),
                        $this->organization(),
                        $data['email'],
                        $data['role'] instanceof OrganizationRole ? $data['role'] : OrganizationRole::from($data['role']),
                    ));

                    Notification::make()->success()->title(__('invitations.notifications.sent'))->send();
                }),
        ];
    }

    private function organization(): Organization
    {
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Organization, 404);

        return $tenant;
    }
}
