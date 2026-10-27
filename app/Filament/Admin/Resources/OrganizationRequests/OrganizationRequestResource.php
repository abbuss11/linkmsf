<?php

namespace App\Filament\Admin\Resources\OrganizationRequests;

use App\Actions\Organizations\ApproveOrganizationRequest;
use App\Actions\Organizations\RejectOrganizationRequest;
use App\Enums\OrganizationRequestStatus;
use App\Filament\Admin\Resources\OrganizationRequests\Pages\ListOrganizationRequests;
use App\Filament\Support\RunsActions;
use App\Models\OrganizationRequest;
use App\Models\User;
use App\Support\OrganizationSlug;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class OrganizationRequestResource extends Resource
{
    protected static ?string $model = OrganizationRequest::class;

    // French labels are not title-cased.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'organization-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('organization_requests.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organization_requests.resource.plural_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = OrganizationRequest::query()->where('status', OrganizationRequestStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization_name')->label(__('organization_requests.fields.organization_name'))->searchable(),
                TextColumn::make('contact_name')->label(__('organization_requests.fields.contact_name'))->searchable(),
                TextColumn::make('email')->label(__('organization_requests.fields.email'))->searchable(),
                TextColumn::make('country')->label(__('organization_requests.fields.country')),
                TextColumn::make('status')->label(__('organization_requests.fields.status'))->badge(),
                TextColumn::make('created_at')->label(__('organization_requests.fields.created_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('organization_requests.fields.status'))
                    ->options(OrganizationRequestStatus::class)
                    ->default(OrganizationRequestStatus::Pending->value),
            ])
            ->recordActions([
                ViewAction::make()->schema([
                    TextEntry::make('organization_name')->label(__('organization_requests.fields.organization_name')),
                    TextEntry::make('contact_name')->label(__('organization_requests.fields.contact_name')),
                    TextEntry::make('email')->label(__('organization_requests.fields.email')),
                    TextEntry::make('phone')->label(__('organization_requests.fields.phone'))->placeholder('—'),
                    TextEntry::make('country')->label(__('organization_requests.fields.country')),
                    TextEntry::make('message')->label(__('organization_requests.fields.message'))->placeholder('—'),
                    TextEntry::make('status')->label(__('organization_requests.fields.status'))->badge(),
                    TextEntry::make('rejection_reason')->label(__('organization_requests.fields.rejection_reason'))->placeholder('—'),
                ]),
                Action::make('approve')
                    ->label(__('organization_requests.actions.approve'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (OrganizationRequest $record): bool => $record->isPending())
                    ->authorize(fn (OrganizationRequest $record): bool => Gate::allows('review', $record))
                    ->modalDescription(__('organization_requests.actions.approve_description'))
                    ->fillForm(fn (OrganizationRequest $record): array => [
                        'slug' => OrganizationSlug::suggest($record->organization_name),
                    ])
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('organizations.fields.slug'))
                            ->helperText(__('organizations.fields.slug_help'))
                            ->required()
                            ->rules(OrganizationSlug::rules()),
                    ])
                    ->action(function (OrganizationRequest $record, array $data): void {
                        RunsActions::attempt(fn () => app(ApproveOrganizationRequest::class)
                            ->handle(self::actor(), $record, $data['slug']));

                        Notification::make()->success()->title(__('organization_requests.notifications.approved'))->send();
                    }),
                Action::make('reject')
                    ->label(__('organization_requests.actions.reject'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (OrganizationRequest $record): bool => $record->isPending())
                    ->authorize(fn (OrganizationRequest $record): bool => Gate::allows('review', $record))
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('organization_requests.fields.rejection_reason'))
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->action(function (OrganizationRequest $record, array $data): void {
                        RunsActions::attempt(fn () => app(RejectOrganizationRequest::class)
                            ->handle(self::actor(), $record, $data['reason']));

                        Notification::make()->success()->title(__('organization_requests.notifications.rejected'))->send();
                    }),
            ]);
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
            'index' => ListOrganizationRequests::route('/'),
        ];
    }
}
