<?php

namespace App\Filament\Shared\Tables;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only audit log table, shared by the platform and organisation panels.
 */
class AuditLogTable
{
    public static function configure(Table $table, bool $showOrganization): Table
    {
        return $table
            ->columns(array_values(array_filter([
                TextColumn::make('created_at')->label(__('audit.fields.date'))->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('event')
                    ->label(__('audit.fields.event'))
                    ->formatStateUsing(fn (?string $state): string => AuditEvent::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                    ->badge(),
                $showOrganization
                    ? TextColumn::make('organization.name')->label(__('audit.fields.organization'))->placeholder('—')
                    : null,
                TextColumn::make('causer.name')->label(__('audit.fields.causer'))->placeholder(__('audit.system')),
                TextColumn::make('subject_type')
                    ->label(__('audit.fields.subject'))
                    ->formatStateUsing(fn (AuditLog $record): string => class_basename((string) $record->subject_type).' #'.$record->subject_id)
                    ->placeholder('—'),
                TextColumn::make('ip')->label(__('audit.fields.ip'))->toggleable(isToggledHiddenByDefault: true),
            ])))
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label(__('audit.fields.event'))
                    ->options(collect(AuditEvent::cases())->mapWithKeys(fn (AuditEvent $event) => [$event->value => $event->getLabel()])->all()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        TextEntry::make('created_at')->label(__('audit.fields.date'))->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('event')
                            ->label(__('audit.fields.event'))
                            ->formatStateUsing(fn (?string $state): string => AuditEvent::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                        TextEntry::make('causer.name')->label(__('audit.fields.causer'))->placeholder(__('audit.system')),
                        TextEntry::make('ip')->label(__('audit.fields.ip'))->placeholder('—'),
                        TextEntry::make('user_agent')->label(__('audit.fields.user_agent'))->placeholder('—'),
                        KeyValueEntry::make('properties')
                            ->label(__('audit.fields.properties'))
                            ->state(fn (AuditLog $record): array => collect($record->properties?->all() ?? [])
                                ->map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE))
                                ->all()),
                    ]),
            ])
            ->paginated([25, 50, 100]);
    }
}
