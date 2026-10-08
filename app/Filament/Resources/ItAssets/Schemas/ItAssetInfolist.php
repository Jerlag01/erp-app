<?php

namespace App\Filament\Resources\ItAssets\Schemas;

use App\Models\ItAsset;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class ItAssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 4])
                    ->columnSpanFull()
                    ->schema([
                        Section::make()
                            ->columnSpan(['lg' => 3])
                            ->columns(['default' => 1, 'sm' => 2, 'xl' => 3])
                            ->schema([
                                TextEntry::make('model')
                                    ->label(__('erp.resources.it_model.singular'))
                                    ->state(fn (ItAsset $record): string => $record->model->fullName())
                                    ->helperText(fn (ItAsset $record): ?string => $record->model->model_number),
                                TextEntry::make('status.name')
                                    ->label(__('erp.fields.status'))
                                    ->badge()
                                    ->color(fn (ItAsset $record): string => $record->status->type->getColor()),
                                TextEntry::make('assigned')
                                    ->label(__('erp.it.checked_out_to'))
                                    ->state(fn (ItAsset $record): ?string => $record->assignedName())
                                    ->helperText(fn (ItAsset $record): ?string => $record->assigned_at ? __('erp.it.since', ['date' => $record->assigned_at->format('d/m/Y')]).($record->expected_checkin ? ' · '.__('erp.it.expected_back', ['date' => $record->expected_checkin->format('d/m/Y')]) : '') : null)
                                    ->placeholder(__('erp.it.available')),
                                TextEntry::make('serial')
                                    ->label(__('erp.fields.serial_number'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('location.name')
                                    ->label(__('erp.resources.location.singular'))
                                    ->placeholder('—')
                                    ->visible(fn (): bool => modules()->locations()),
                                TextEntry::make('hostname')
                                    ->label(__('erp.fields.hostname'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('ip_address')
                                    ->label(__('erp.fields.ip_address'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('mac_address')
                                    ->label(__('erp.fields.mac_address'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('operating_system')
                                    ->label(__('erp.fields.operating_system'))
                                    ->placeholder('—'),
                                TextEntry::make('purchase_date')
                                    ->label(__('erp.fields.purchase_date'))
                                    ->date('d/m/Y')
                                    ->helperText(fn (ItAsset $record): ?string => $record->supplier?->name)
                                    ->placeholder('—'),
                                TextEntry::make('warranty')
                                    ->label(__('erp.it.warranty_until'))
                                    ->state(fn (ItAsset $record) => $record->warrantyExpires())
                                    ->date('d/m/Y')
                                    ->color(fn (ItAsset $record): ?string => $record->warrantyExpires()?->isPast() ? 'danger' : null)
                                    ->placeholder('—'),
                                TextEntry::make('eol')
                                    ->label(__('erp.it.end_of_life'))
                                    ->state(fn (ItAsset $record) => $record->endOfLife())
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                                TextEntry::make('last_audit_date')
                                    ->label(__('erp.fields.last_audit_date'))
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                                TextEntry::make('next_audit_date')
                                    ->label(__('erp.fields.next_audit_date'))
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                                KeyValueEntry::make('specs')
                                    ->label(__('erp.fields.specs'))
                                    ->keyLabel(__('erp.fields.spec'))
                                    ->valueLabel(__('erp.fields.value'))
                                    ->columnSpanFull()
                                    ->visible(fn (ItAsset $record): bool => filled($record->specs)),
                                TextEntry::make('notes')
                                    ->label(__('erp.fields.notes'))
                                    ->columnSpanFull()
                                    ->visible(fn (ItAsset $record): bool => filled($record->notes)),
                            ]),
                        Grid::make(1)
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                ImageEntry::make('image')
                                    ->hiddenLabel()
                                    ->disk('public')
                                    ->imageHeight(160)
                                    ->visible(fn (ItAsset $record): bool => filled($record->image)),
                                ViewEntry::make('qr')
                                    ->hiddenLabel()
                                    ->view('filament.it.qr'),
                            ]),
                    ]),
            ]);
    }
}
