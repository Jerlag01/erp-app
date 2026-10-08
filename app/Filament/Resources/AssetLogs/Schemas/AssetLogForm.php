<?php

namespace App\Filament\Resources\AssetLogs\Schemas;

use App\Enums\AssetLogType;
use App\Enums\DamageSeverity;
use App\Models\Asset;
use App\Models\AssetLog;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * The log entry form, used from an asset's page and from the global log.
 * Pass $asset when the asset is already known (relation manager); otherwise an asset select is shown.
 */
class AssetLogForm
{
    public static function configure(Schema $schema, ?Asset $asset = null): Schema
    {
        $is = fn (AssetLogType ...$types): \Closure => fn (Get $get): bool => in_array(
            $get('type') instanceof AssetLogType ? $get('type') : AssetLogType::tryFrom((string) $get('type')),
            $types,
            true,
        );

        return $schema
            ->columns(2)
            ->components([
                Select::make('asset_id')
                    ->label(__('erp.resources.asset.singular'))
                    ->relationship('asset', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->getOptionLabelFromRecordUsing(fn (Asset $record): string => "{$record->asset_tag} · {$record->name}")
                    ->searchable(['asset_tag', 'name', 'serial_number'])
                    ->preload()
                    ->required()
                    ->live()
                    ->hidden($asset !== null)
                    ->columnSpanFull(),
                ToggleButtons::make('type')
                    ->label(__('erp.fields.log_type'))
                    ->options(AssetLogType::class)
                    ->icons([
                        AssetLogType::Damage->value => 'heroicon-o-exclamation-triangle',
                        AssetLogType::Repair->value => 'heroicon-o-wrench-screwdriver',
                        AssetLogType::Inspection->value => 'heroicon-o-clipboard-document-check',
                        AssetLogType::Note->value => 'heroicon-o-pencil-square',
                    ])
                    ->default(AssetLogType::Damage)
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label(__('erp.fields.title'))
                    ->required()
                    ->maxLength(150),
                DatePicker::make('date')
                    ->label(__('erp.fields.date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
                Select::make('severity')
                    ->label(__('erp.fields.severity'))
                    ->options(DamageSeverity::class)
                    ->default(DamageSeverity::Minor)
                    ->required($is(AssetLogType::Damage))
                    ->visible($is(AssetLogType::Damage)),
                Select::make('damage_id')
                    ->label(__('erp.fields.repairs_damage'))
                    ->helperText(__('erp.help.repairs_damage'))
                    ->options(function (Get $get, ?AssetLog $record) use ($asset): array {
                        $assetId = $asset?->id ?? $get('asset_id');

                        if (! $assetId) {
                            return [];
                        }

                        return AssetLog::query()
                            ->where('asset_id', $assetId)
                            ->where('type', AssetLogType::Damage)
                            ->where(fn (Builder $query) => $query->whereNull('resolved_at')->when($record?->damage_id, fn (Builder $query, int $id) => $query->orWhere('id', $id)))
                            ->orderByDesc('date')
                            ->get()
                            ->mapWithKeys(fn (AssetLog $damage): array => [$damage->id => $damage->date->format('d/m/Y').' · '.$damage->title])
                            ->all();
                    })
                    ->visible($is(AssetLogType::Repair)),
                TextInput::make('performed_by')
                    ->label(__('erp.fields.performed_by'))
                    ->helperText(__('erp.help.performed_by'))
                    ->maxLength(150)
                    ->visible($is(AssetLogType::Repair, AssetLogType::Inspection)),
                TextInput::make('cost')
                    ->label(__('erp.fields.cost'))
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->visible($is(AssetLogType::Repair, AssetLogType::Inspection)),
                Textarea::make('description')
                    ->label(__('erp.fields.description'))
                    ->rows(4)
                    ->columnSpanFull(),
                FileUpload::make('attachments')
                    ->label(__('erp.fields.attachments'))
                    ->helperText(__('erp.help.attachments'))
                    ->multiple()
                    ->maxFiles(10)
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'application/pdf'])
                    ->maxSize(8192)
                    ->disk('public')
                    ->directory('asset-logs')
                    ->visibility('public')
                    ->openable()
                    ->downloadable()
                    ->panelLayout('grid')
                    ->columnSpanFull(),
            ]);
    }
}
