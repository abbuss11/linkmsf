<?php

namespace App\Filament\Admin\Resources\AuditLogs;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Shared\Tables\AuditLogTable;
use App\Models\AuditLog;
use App\Models\Organization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global audit log for superadmins (platform and all organisations).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    // French labels are not title-cased.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'audit-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('audit.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('audit.resource.plural_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'organization']);
    }

    public static function table(Table $table): Table
    {
        return AuditLogTable::configure($table, showOrganization: true)
            ->pushFilters([
                SelectFilter::make('organization_id')
                    ->label(__('audit.fields.organization'))
                    ->options(fn (): array => Organization::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
