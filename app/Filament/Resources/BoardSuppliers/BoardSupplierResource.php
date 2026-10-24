<?php

namespace App\Filament\Resources\BoardSuppliers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\BoardSuppliers\Pages\ManageBoardSuppliers;
use App\Models\BoardSupplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
 * The electrical board suppliers, each with its own follow-up list (tab) and list layout.
 */
class BoardSupplierResource extends Resource
{
    protected static ?string $model = BoardSupplier::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'board-suppliers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::BoardDeliveries;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.follow_ups.supplier_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.follow_ups.supplier_plural');
    }

    /**
     * @return array<string, string>
     */
    public static function layouts(): array
    {
        return collect(BoardSupplier::LAYOUTS)->mapWithKeys(fn (string $layout): array => [$layout => __('erp.follow_ups.layouts.'.$layout)])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100)->unique(ignoreRecord: true),
                TextInput::make('sort')->label(__('erp.follow_ups.sort'))->integer()->minValue(0)->default(0),
                Select::make('layout')->label(__('erp.follow_ups.layout'))->options(self::layouts())->default(BoardSupplier::SIMPLE)->required()->columnSpanFull(),
                Toggle::make('is_active')->label(__('erp.fields.active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->weight(FontWeight::Bold)->searchable(),
                TextColumn::make('layout')->label(__('erp.follow_ups.layout'))->formatStateUsing(fn (string $state): string => self::layouts()[$state] ?? $state)->wrap(),
                TextColumn::make('follow_ups_count')->label(__('erp.follow_ups.board_plural'))->counts('followUps')->alignEnd(),
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
            'index' => ManageBoardSuppliers::route('/'),
        ];
    }
}
