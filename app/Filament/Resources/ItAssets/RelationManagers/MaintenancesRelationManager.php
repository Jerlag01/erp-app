<?php

namespace App\Filament\Resources\ItAssets\RelationManagers;

use App\Enums\ItMaintenanceType;
use App\Models\ItMaintenance;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Repairs, upgrades and other maintenance of the asset.
 */
class MaintenancesRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenances';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedWrenchScrewdriver;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.resources.it_maintenance.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return static::maintenanceForm($schema);
    }

    /**
     * @param  list<Component|Field>  $prepend  extra fields shown first (e.g. the asset on the overview page)
     */
    public static function maintenanceForm(Schema $schema, array $prepend = []): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ...$prepend,
                Select::make('type')
                    ->label(__('erp.fields.type'))
                    ->options(ItMaintenanceType::class)
                    ->default(ItMaintenanceType::Repair)
                    ->required(),
                TextInput::make('title')
                    ->label(__('erp.fields.title'))
                    ->required()
                    ->maxLength(150),
                DatePicker::make('start_date')
                    ->label(__('erp.fields.start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->required(),
                DatePicker::make('completion_date')
                    ->label(__('erp.fields.completion_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date'),
                Select::make('it_supplier_id')
                    ->label(__('erp.fields.supplier'))
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100)]),
                TextInput::make('cost')
                    ->label(__('erp.fields.cost'))
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€'),
                Toggle::make('is_warranty')
                    ->label(__('erp.fields.is_warranty'))
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('erp.fields.notes'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('start_date')
                    ->label(__('erp.fields.start_date'))
                    ->date('d/m/Y')
                    ->description(fn (ItMaintenance $record): ?string => $record->completion_date ? '→ '.$record->completion_date->format('d/m/Y') : __('erp.it.ongoing'))
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('erp.fields.type'))
                    ->badge(),
                TextColumn::make('title')
                    ->label(__('erp.fields.title'))
                    ->description(fn (ItMaintenance $record): ?string => $record->supplier?->name)
                    ->wrap(),
                IconColumn::make('is_warranty')
                    ->label(__('erp.fields.is_warranty'))
                    ->boolean(),
                TextColumn::make('cost')
                    ->label(__('erp.fields.cost'))
                    ->money('EUR', locale: 'nl_BE')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
