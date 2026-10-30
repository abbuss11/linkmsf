<?php

namespace App\Filament\App\Resources\AuditLogs;

use App\Filament\App\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Shared\Tables\AuditLogTable;
use App\Models\AuditLog;
use App\Models\Organization;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Activity of the current organisation, for its orgadmins (§2.1).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    // French labels are not title-cased.
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'activity';

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

    /**
     * Explicit filter on top of Filament's tenant scope: AuditLog does not use
     * BelongsToOrganization because platform rows have no organisation.
     */
    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()
            ->where('organization_id', $tenant instanceof Organization ? $tenant->getKey() : 0)
            ->with('causer');
    }

    public static function table(Table $table): Table
    {
        return AuditLogTable::configure($table, showOrganization: false);
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
