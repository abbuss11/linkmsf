<?php

namespace App\Filament\Admin\Resources\Users;

use App\Enums\UserType;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only list of accounts for the platform team.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // French labels are not title-cased.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('users.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.resource.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'memberships' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class)->with('organization'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('users.fields.name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('users.fields.email'))->searchable(),
                TextColumn::make('type')->label(__('users.fields.type'))->badge(),
                IconColumn::make('is_superadmin')->label(__('users.fields.is_superadmin'))->boolean(),
                IconColumn::make('email_verified_at')
                    ->label(__('users.fields.verified'))
                    ->state(fn (User $record): bool => $record->email_verified_at !== null)
                    ->boolean(),
                TextColumn::make('last_login_at')->label(__('users.fields.last_login_at'))->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label(__('users.fields.created_at'))->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->label(__('users.fields.type'))->options(UserType::class),
                TernaryFilter::make('is_superadmin')->label(__('users.fields.is_superadmin')),
            ])
            ->recordActions([
                ViewAction::make()->schema([
                    TextEntry::make('name')->label(__('users.fields.name')),
                    TextEntry::make('email')->label(__('users.fields.email')),
                    TextEntry::make('type')->label(__('users.fields.type'))->badge(),
                    IconEntry::make('is_superadmin')->label(__('users.fields.is_superadmin'))->boolean(),
                    RepeatableEntry::make('memberships')
                        ->label(__('users.fields.memberships'))
                        ->schema([
                            TextEntry::make('organization.name')->label(__('organizations.fields.name')),
                            TextEntry::make('role')->label(__('members.fields.role'))->badge(),
                            TextEntry::make('status')->label(__('members.fields.status'))->badge(),
                        ])
                        ->columns(3)
                        ->placeholder('—'),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
