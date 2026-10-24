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
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
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
                TextInput::make('bae_cell_price')
                    ->label(__('erp.batteries.bae_cell_price'))
                    ->helperText(__('erp.batteries.bae_cell_price_help'))
                    ->numeric()
                    ->minValue(0)
                    ->step('any')
                    ->prefix('€')
                    ->columnSpan(3),
                // The rack per mounting; its BAE price and connectors fill in the lines of the BAE order.
                Repeater::make('racks')
                    ->label(__('erp.batteries.racks'))
                    ->relationship()
                    ->columns(4)
                    ->itemLabel(fn (array $state): ?string => BatteryMounting::tryFrom((string) ($state['mounting'] ?? ''))?->getLabel())
                    ->collapsible()
                    ->schema([
                        Select::make('mounting')->label(__('erp.batteries.mounting'))->options(BatteryMounting::class)->required()->distinct(),
                        TextInput::make('code')->label(__('erp.batteries.rack_code'))->maxLength(30),
                        TextInput::make('price')->label(__('erp.batteries.rack_price'))->numeric()->minValue(0)->step('any')->prefix('€'),
                        TextInput::make('bae_price')->label(__('erp.batteries.bae_rack_price'))->numeric()->minValue(0)->step('any')->prefix('€'),
                        Fieldset::make(__('erp.batteries.bae_parts'))
                            ->columns(['default' => 2, 'xl' => 9])
                            ->columnSpanFull()
                            ->schema([
                                ...collect(['intercell', 'step', 'tier70', 'tier50'])->flatMap(fn (string $part): array => [
                                    TextInput::make("{$part}_count")->label(__("erp.batteries.part_{$part}"))->integer()->minValue(0)->default(0)->required(),
                                    TextInput::make("{$part}_code")->label(__('erp.batteries.part_code'))->maxLength(40),
                                ])->all(),
                                TextInput::make('row_count')->label(__('erp.batteries.part_row'))->integer()->minValue(0)->default(0)->required(),
                            ]),
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
                EditAction::make()->modalWidth(Width::SevenExtraLarge),
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
