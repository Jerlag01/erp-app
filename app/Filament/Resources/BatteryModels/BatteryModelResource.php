<?php

namespace App\Filament\Resources\BatteryModels;

use App\Enums\BatteryMounting;
use App\Enums\BatteryTechnology;
use App\Enums\NavigationGroup;
use App\Filament\Resources\BatteryModels\Pages\ManageBatteryModels;
use App\Models\BatteryModel;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Battery models (sheet "Batterijen" of the Excel): weight, dimensions and the rack per mounting.
 */
class BatteryModelResource extends Resource
{
    protected static ?string $model = BatteryModel::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'battery-models';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.batteries.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.batteries.models');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                TextInput::make('name')->label(__('erp.fields.name'))->placeholder('7 OPzV 490')->required()->maxLength(50)->unique(ignoreRecord: true)->columnSpan(3),
                Select::make('technology')->label(__('erp.batteries.technology'))->options(BatteryTechnology::class)->required()->columnSpan(2),
                Toggle::make('is_active')->label(__('erp.fields.active'))->default(true)->inline(false),
                TextInput::make('weight_kg')->label(__('erp.batteries.weight'))->numeric()->minValue(0)->step('any')->suffix('kg')->columnSpan(3),
                TextInput::make('length_mm')->label('l')->integer()->minValue(0)->suffix('mm'),
                TextInput::make('width_mm')->label('b')->integer()->minValue(0)->suffix('mm'),
                TextInput::make('height_mm')->label('h')->integer()->minValue(0)->suffix('mm'),
                Repeater::make('racks')
                    ->label(__('erp.batteries.racks'))
                    ->relationship()
                    ->table([
                        Repeater\TableColumn::make(__('erp.batteries.mounting')),
                        Repeater\TableColumn::make(__('erp.batteries.rack_code')),
                        Repeater\TableColumn::make(__('erp.batteries.rack_price')),
                    ])
                    ->schema([
                        Select::make('mounting')->options(BatteryMounting::class)->required()->distinct(),
                        TextInput::make('code')->maxLength(30),
                        TextInput::make('price')->numeric()->minValue(0)->step('any')->prefix('€'),
                    ])
                    ->maxItems(count(BatteryMounting::cases()))
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with('racks'))
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->weight(FontWeight::Bold)->searchable()->sortable(),
                TextColumn::make('technology')->label(__('erp.batteries.technology'))->badge()->sortable(),
                TextColumn::make('weight_kg')->label(__('erp.batteries.weight'))->suffix(' kg')->alignEnd(),
                TextColumn::make('dimensions')->label('l × b × h')->state(fn (BatteryModel $record): string => "{$record->length_mm} × {$record->width_mm} × {$record->height_mm} mm"),
                TextColumn::make('racks_list')
                    ->label(__('erp.batteries.racks'))
                    ->state(fn (BatteryModel $record): array => $record->racks->map(fn ($rack): string => $rack->mounting->getLabel().': '.($rack->code ?? '—'))->all())
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('technology')->label(__('erp.batteries.technology'))->options(BatteryTechnology::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBatteryModels::route('/'),
        ];
    }
}
