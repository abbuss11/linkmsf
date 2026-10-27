<?php

namespace App\Filament\App\Resources\Members;

use App\Actions\Members\ChangeMemberRole;
use App\Actions\Members\ChangeMemberStatus;
use App\Actions\Members\RemoveMember;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\Members\Pages\ListMembers;
use App\Filament\Support\RunsActions;
use App\Models\OrganizationMembership;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class MemberResource extends Resource
{
    protected static ?string $model = OrganizationMembership::class;

    // French labels are not title-cased.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $tenantRelationshipName = 'memberships';

    protected static ?string $slug = 'members';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('members.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('members.resource.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label(__('members.fields.name'))->searchable()->sortable(),
                TextColumn::make('user.email')->label(__('members.fields.email'))->searchable(),
                TextColumn::make('role')->label(__('members.fields.role'))->badge(),
                TextColumn::make('status')->label(__('members.fields.status'))->badge(),
                TextColumn::make('joined_at')->label(__('members.fields.joined_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('joined_at', 'desc')
            ->filters([
                SelectFilter::make('role')->label(__('members.fields.role'))->options(OrganizationRole::class),
                SelectFilter::make('status')->label(__('members.fields.status'))->options(MembershipStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::changeRoleAction(),
                    self::suspendAction(),
                    self::reactivateAction(),
                    self::removeAction(),
                ]),
            ]);
    }

    private static function changeRoleAction(): Action
    {
        return Action::make('changeRole')
            ->label(__('members.actions.change_role'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->authorize(fn (OrganizationMembership $record): bool => Gate::allows('update', $record))
            ->schema([
                Select::make('role')
                    ->label(__('members.fields.role'))
                    ->options(OrganizationRole::class)
                    ->enum(OrganizationRole::class)
                    ->required(),
            ])
            ->fillForm(fn (OrganizationMembership $record): array => ['role' => $record->role])
            ->action(function (OrganizationMembership $record, array $data): void {
                RunsActions::attempt(fn () => app(ChangeMemberRole::class)->handle(
                    self::actor(),
                    $record,
                    $data['role'] instanceof OrganizationRole ? $data['role'] : OrganizationRole::from($data['role']),
                ));

                Notification::make()->success()->title(__('members.notifications.role_changed'))->send();
            });
    }

    private static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label(__('members.actions.suspend'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (OrganizationMembership $record): bool => $record->status === MembershipStatus::Active)
            ->authorize(fn (OrganizationMembership $record): bool => Gate::allows('update', $record))
            ->action(function (OrganizationMembership $record): void {
                RunsActions::attempt(fn () => app(ChangeMemberStatus::class)
                    ->handle(self::actor(), $record, MembershipStatus::Suspended));

                Notification::make()->success()->title(__('members.notifications.suspended'))->send();
            });
    }

    private static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label(__('members.actions.reactivate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (OrganizationMembership $record): bool => $record->status === MembershipStatus::Suspended)
            ->authorize(fn (OrganizationMembership $record): bool => Gate::allows('update', $record))
            ->action(function (OrganizationMembership $record): void {
                RunsActions::attempt(fn () => app(ChangeMemberStatus::class)
                    ->handle(self::actor(), $record, MembershipStatus::Active));

                Notification::make()->success()->title(__('members.notifications.reactivated'))->send();
            });
    }

    private static function removeAction(): Action
    {
        return Action::make('remove')
            ->label(__('members.actions.remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('members.actions.remove_confirmation'))
            ->authorize(fn (OrganizationMembership $record): bool => Gate::allows('delete', $record))
            ->action(function (OrganizationMembership $record): void {
                RunsActions::attempt(fn () => app(RemoveMember::class)->handle(self::actor(), $record));

                Notification::make()->success()->title(__('members.notifications.removed'))->send();
            });
    }

    private static function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
        ];
    }
}
