<?php

namespace App\Filament\App\Resources\Invitations;

use App\Actions\Members\ResendInvitation;
use App\Actions\Members\RevokeInvitation;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\Invitations\Pages\ListInvitations;
use App\Filament\Support\RunsActions;
use App\Models\Invitation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    // French labels are not title-cased.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'invitations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('invitations.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('invitations.resource.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('inviter');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label(__('invitations.fields.email'))->searchable(),
                TextColumn::make('role')->label(__('invitations.fields.role'))->badge(),
                TextColumn::make('status')->label(__('invitations.fields.status'))->badge(),
                TextColumn::make('inviter.name')->label(__('invitations.fields.invited_by'))->placeholder('—'),
                TextColumn::make('expires_at')->label(__('invitations.fields.expires_at'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('created_at')->label(__('invitations.fields.created_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label(__('invitations.fields.status'))
                    ->options([
                        InvitationStatus::Pending->value => InvitationStatus::Pending->getLabel(),
                        InvitationStatus::Accepted->value => InvitationStatus::Accepted->getLabel(),
                        InvitationStatus::Revoked->value => InvitationStatus::Revoked->getLabel(),
                        InvitationStatus::Expired->value => InvitationStatus::Expired->getLabel(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        InvitationStatus::Pending->value => $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now()),
                        InvitationStatus::Accepted->value => $query->whereNotNull('accepted_at'),
                        InvitationStatus::Revoked->value => $query->whereNotNull('revoked_at'),
                        InvitationStatus::Expired->value => $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '<=', now()),
                        default => $query,
                    }),
                SelectFilter::make('role')->label(__('invitations.fields.role'))->options(OrganizationRole::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('resend')
                        ->label(__('invitations.actions.resend'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->requiresConfirmation()
                        ->visible(fn (Invitation $record): bool => $record->accepted_at === null && $record->revoked_at === null)
                        ->authorize(fn (Invitation $record): bool => Gate::allows('resend', $record))
                        ->action(function (Invitation $record): void {
                            RunsActions::attempt(fn () => app(ResendInvitation::class)->handle(self::actor(), $record));

                            Notification::make()->success()->title(__('invitations.notifications.resent'))->send();
                        }),
                    Action::make('revoke')
                        ->label(__('invitations.actions.revoke'))
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Invitation $record): bool => $record->isPending())
                        ->authorize(fn (Invitation $record): bool => Gate::allows('revoke', $record))
                        ->action(function (Invitation $record): void {
                            RunsActions::attempt(fn () => app(RevokeInvitation::class)->handle(self::actor(), $record));

                            Notification::make()->success()->title(__('invitations.notifications.revoked'))->send();
                        }),
                ]),
            ]);
    }

    public static function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitations::route('/'),
        ];
    }
}
