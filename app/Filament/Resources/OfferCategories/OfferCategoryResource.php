<?php

namespace App\Filament\Resources\OfferCategories;

use App\Enums\NavigationGroup;
use App\Filament\Resources\OfferCategories\Pages\ManageOfferCategories;
use App\Models\OfferCategory;
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
use UnitEnum;

/**
 * The chapters of an offer (A Plaatsen omvormer, C Bekabeling AC & DC, G Algemene onkosten, …).
 */
class OfferCategoryResource extends Resource
{
    protected static ?string $model = OfferCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'offer-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('erp.resources.offer_category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.offer_category.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                TextInput::make('code')->label(__('erp.finance.offers.code'))->helperText(__('erp.finance.offers.code_help'))->required()->maxLength(5),
                TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(255)->columnSpan(3),
                Toggle::make('is_general_costs')->label(__('erp.finance.offers.is_general_costs'))->helperText(__('erp.finance.offers.is_general_costs_help'))->columnSpan(2),
                Toggle::make('is_active')->label(__('erp.finance.standard_lines.is_active'))->default(true),
                TextInput::make('sort_order')->label(__('erp.kb.sort_order'))->integer()->default(fn (): int => (int) OfferCategory::query()->max('sort_order') + 1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->label(__('erp.finance.offers.code'))->fontFamily(FontFamily::Mono)->weight('bold'),
                TextColumn::make('name')->label(__('erp.fields.name'))->searchable(),
                TextColumn::make('standard_lines_count')->label(__('erp.resources.standard_offer_line.plural'))->counts('standardLines')->badge(),
                IconColumn::make('is_general_costs')->label(__('erp.finance.offers.is_general_costs'))->boolean(),
                IconColumn::make('is_active')->label(__('erp.finance.standard_lines.is_active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOfferCategories::route('/'),
        ];
    }
}
