<?php

namespace App\Filament\Resources\BatteryCables;

use App\Enums\NavigationGroup;
use App\Filament\Resources\BatteryCables\Pages\ManageBatteryCables;
use App\Models\BatteryCable;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Ready-made Frötek battery cables with their price (list in sheet "DATANL" of the Excel).
 */
class BatteryCableResource extends Resource
{
    protected static ?string $model = BatteryCable::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'battery-cables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.batteries.cable');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.batteries.cables');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextInput::make('name')->label(__('erp.fields.name'))->placeholder('70mm² x 3m')->required()->maxLength(50)->unique(ignoreRecord: true),
                TextInput::make('price')->label(__('erp.batteries.rack_price'))->numeric()->minValue(0)->step('any')->prefix('€')->required(),
                Toggle::make('is_active')->label(__('erp.fields.active'))->default(true)->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->weight(FontWeight::Bold)->searchable(),
                TextColumn::make('price')->label(__('erp.batteries.rack_price'))->money('EUR', locale: 'nl_BE')->alignEnd(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBatteryCables::route('/'),
        ];
    }
}
