<?php

namespace App\Filament\Resources\Units;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Units\Pages\ManageUnits;
use App\Models\Unit;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * How items are counted: meters for cable, pieces for fuses and breakers…
 */
class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.unit.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.unit.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->placeholder(__('erp.help.unit_name'))
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('abbreviation')
                    ->label(__('erp.fields.abbreviation'))
                    ->placeholder('m')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true),
                Toggle::make('allows_decimals')
                    ->label(__('erp.fields.allows_decimals'))
                    ->helperText(__('erp.help.allows_decimals')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('stockItems'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('abbreviation')
                    ->label(__('erp.fields.abbreviation'))
                    ->fontFamily(FontFamily::Mono),
                IconColumn::make('allows_decimals')
                    ->label(__('erp.fields.allows_decimals'))
                    ->boolean(),
                TextColumn::make('stock_items_count')
                    ->label(__('erp.resources.stock_item.plural'))
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                // The policy blocks deleting units that items are measured in.
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUnits::route('/'),
        ];
    }
}
