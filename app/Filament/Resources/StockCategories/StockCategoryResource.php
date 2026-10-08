<?php

namespace App\Filament\Resources\StockCategories;

use App\Enums\NavigationGroup;
use App\Filament\Resources\StockCategories\Pages\ManageStockCategories;
use App\Models\StockCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Categories (Cables, Fuses, Breakers…) and their subcategories (Cables › XVB, XGB…).
 */
class StockCategoryResource extends Resource
{
    protected static ?string $model = StockCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.stock_category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.stock_category.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label(__('erp.fields.parent_category'))
                    ->helperText(__('erp.help.parent_category'))
                    ->relationship('parent', 'name', fn (Builder $query, ?StockCategory $record) => $query->topLevel()->when($record, fn (Builder $query) => $query->whereKeyNot($record->id)))
                    ->disabled(fn (?StockCategory $record): bool => (bool) $record?->children()->exists())
                    ->preload(),
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('parent_id', $get('parent_id'))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent')->withCount(['children', 'stockItems']))
            ->defaultGroup(Group::make('parent.name')->label(__('erp.fields.category'))->getTitleFromRecordUsing(fn (StockCategory $record): string => $record->parent?->name ?? __('erp.stock.main_categories'))->collapsible())
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->formatStateUsing(fn (StockCategory $record): string => $record->parent ? '↳ '.$record->name : $record->name)
                    ->weight(fn (StockCategory $record): string => $record->parent ? 'normal' : 'bold')
                    ->searchable(),
                TextColumn::make('children_count')
                    ->label(__('erp.fields.subcategories'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—'),
                TextColumn::make('stock_items_count')
                    ->label(__('erp.resources.stock_item.plural'))
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('parent')
                    ->label(__('erp.fields.category'))
                    ->relationship('parent', 'name', fn (Builder $query) => $query->topLevel())
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                // The policy blocks deleting categories that still have subcategories or items.
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStockCategories::route('/'),
        ];
    }
}
