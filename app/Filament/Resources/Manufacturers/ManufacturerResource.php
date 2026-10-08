<?php

namespace App\Filament\Resources\Manufacturers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Manufacturers\Pages\CreateManufacturer;
use App\Filament\Resources\Manufacturers\Pages\EditManufacturer;
use App\Filament\Resources\Manufacturers\Pages\ListManufacturers;
use App\Filament\Schemas\CompanyFields;
use App\Models\Manufacturer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ManufacturerResource extends Resource
{
    protected static ?string $model = Manufacturer::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.manufacturer.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.manufacturer.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...CompanyFields::make('manufacturers', [
                Select::make('distributors')
                    ->label(__('erp.resources.distributor.plural'))
                    ->helperText(__('erp.help.manufacturer_distributors'))
                    ->relationship('distributors', 'name')
                    ->multiple()
                    ->preload(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('distributors')->withCount('stockItems'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(32),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->description(fn (Manufacturer $record): ?string => $record->website)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('distributors.name')
                    ->label(__('erp.resources.distributor.plural'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label(__('erp.fields.phone'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('stock_items_count')
                    ->label(__('erp.resources.stock_item.plural'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManufacturers::route('/'),
            'create' => CreateManufacturer::route('/create'),
            'edit' => EditManufacturer::route('/{record}/edit'),
        ];
    }
}
