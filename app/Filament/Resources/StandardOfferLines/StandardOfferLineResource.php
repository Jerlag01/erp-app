<?php

namespace App\Filament\Resources\StandardOfferLines;

use App\Enums\NavigationGroup;
use App\Filament\Resources\StandardOfferLines\Pages\ManageStandardOfferLines;
use App\Models\Invoice;
use App\Models\OfferCategory;
use App\Models\StandardOfferLine;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The default lines to add to an offer, per category, with their supply and installation price.
 */
class StandardOfferLineResource extends Resource
{
    protected static ?string $model = StandardOfferLine::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'standard-offer-lines';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Offers;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.resources.standard_offer_line.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.standard_offer_line.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Select::make('offer_category_id')
                    ->label(__('erp.finance.offers.category'))
                    ->options(fn (?StandardOfferLine $record): array => OfferCategory::options(array_filter([$record?->offer_category_id])))
                    ->required()
                    ->columnSpan(2),
                TextInput::make('description')->label(__('erp.finance.pdf.description'))->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('quantity')->label(__('erp.finance.pdf.quantity'))->numeric()->step('any')->default(1)->required(),
                TextInput::make('unit')->label(__('erp.finance.invoices.unit'))->placeholder('st/m')->maxLength(20),
                TextInput::make('supply_price')->label(__('erp.finance.offers.supply_price'))->numeric()->step(0.01)->prefix('€'),
                TextInput::make('install_price')->label(__('erp.finance.offers.install_price'))->numeric()->step(0.01)->prefix('€')
                    ->disabled(fn (Get $get): bool => (bool) $get('is_labour')),
                Toggle::make('is_labour')->label(__('erp.finance.offers.is_labour'))->helperText(__('erp.finance.offers.is_labour_help'))->live()->columnSpan(2),
                Toggle::make('is_active')->label(__('erp.finance.standard_lines.is_active'))->default(true),
                TextInput::make('sort_order')->label(__('erp.kb.sort_order'))->integer()->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->defaultGroup(Group::make('category.code')
                ->label(__('erp.finance.offers.category'))
                ->getTitleFromRecordUsing(fn (StandardOfferLine $record): string => (string) $record->category?->label())
                ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy(OfferCategory::query()->select('sort_order')->whereColumn('offer_categories.id', 'standard_offer_lines.offer_category_id'), $direction)))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('description')->label(__('erp.finance.pdf.description'))->weight('medium')->searchable(),
                TextColumn::make('unit')->label(__('erp.finance.invoices.unit'))->placeholder('—'),
                TextColumn::make('supply_price')->label(__('erp.finance.offers.supply_price'))->money('EUR', locale: 'nl_BE')->placeholder('—'),
                TextColumn::make('install_price')->label(__('erp.finance.offers.install_price'))->money('EUR', locale: 'nl_BE')
                    ->formatStateUsing(fn (StandardOfferLine $record, mixed $state): string => $record->is_labour ? __('erp.finance.offers.hourly_rate') : Invoice::money($state))
                    ->placeholder('—'),
                IconColumn::make('is_active')->label(__('erp.finance.standard_lines.is_active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('offer_category_id')->label(__('erp.finance.offers.category'))->options(fn (): array => OfferCategory::options()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStandardOfferLines::route('/'),
        ];
    }
}
