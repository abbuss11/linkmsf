<?php

namespace App\Filament\Admin\Resources\Organizations;

use App\Actions\Organizations\ChangeOrganizationStatus;
use App\Enums\OrganizationStatus;
use App\Filament\Admin\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Admin\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Admin\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Support\RunsActions;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use App\Support\OrganizationSlug;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    // French labels are not title-cased.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('organizations.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organizations.resource.plural_label');
    }

    /**
     * Platform view: members are counted across organisations on purpose.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'memberships' => fn (Builder $query) => $query->withoutGlobalScope(OrganizationScope::class),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('organizations.profile.identity'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('organizations.fields.name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->label(__('organizations.fields.slug'))
                        ->helperText(__('organizations.fields.slug_help'))
                        ->required()
                        ->rules(fn (?Organization $record): array => OrganizationSlug::rules($record)),
                    Select::make('timezone')
                        ->label(__('organizations.fields.timezone'))
                        ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                        ->default('Africa/Niamey')
                        ->searchable()
                        ->required(),
                    TextInput::make('country')
                        ->label(__('organizations.fields.country'))
                        ->default('NE')
                        ->length(2)
                        ->required(),
                ]),
            Section::make(__('organizations.admin.first_admin'))
                ->visibleOn('create')
                ->schema([
                    TextInput::make('admin_email')
                        ->label(__('organizations.fields.admin_email'))
                        ->helperText(__('organizations.admin.first_admin_help'))
                        ->email()
                        ->maxLength(255),
                ]),
            Section::make(__('organizations.admin.limits'))
                ->visibleOn('edit')
                ->columns(2)
                ->schema([
                    TextInput::make('limits.max_active_job_postings')
                        ->label(__('organizations.fields.max_active_job_postings'))
                        ->integer()
                        ->minValue(0)
                        ->required(),
                    TextInput::make('limits.storage_quota_mb')
                        ->label(__('organizations.fields.storage_quota_mb'))
                        ->integer()
                        ->minValue(0)
                        ->required(),
                    Toggle::make('listed_on_job_board')
                        ->label(__('organizations.fields.listed_on_job_board')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('organizations.fields.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('organizations.fields.slug'))->searchable(),
                TextColumn::make('status')->label(__('organizations.fields.status'))->badge(),
                TextColumn::make('memberships_count')->label(__('organizations.fields.members_count')),
                TextColumn::make('created_at')->label(__('organizations.fields.created_at'))->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('organizations.fields.status'))->options(OrganizationStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::statusAction('activate', OrganizationStatus::Active, Heroicon::OutlinedCheckCircle, 'success'),
                    self::statusAction('suspend', OrganizationStatus::Suspended, Heroicon::OutlinedPauseCircle, 'danger'),
                ]),
            ]);
    }

    private static function statusAction(string $name, OrganizationStatus $status, Heroicon $icon, string $color): Action
    {
        return Action::make($name)
            ->label(__("organizations.actions.{$name}"))
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (Organization $record): bool => $record->status !== $status)
            ->authorize(fn (Organization $record): bool => Gate::allows('changeStatus', $record))
            ->action(function (Organization $record) use ($status, $name): void {
                $user = auth()->user();
                abort_unless($user instanceof User, 403);

                RunsActions::attempt(fn () => app(ChangeOrganizationStatus::class)->handle($user, $record, $status));

                Notification::make()->success()->title(__("organizations.notifications.{$name}"))->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
