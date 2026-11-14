<?php

namespace App\Filament\Resources\Offers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Filament\Resources\Offers\Schemas\OfferForm;
use App\Filament\Resources\Offers\Tables\OffersTable;
use App\Models\Offer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Offers (quotes) for projects, with lines per category and default lines to add.
 */
class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'offers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyEuro;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Offers;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getModelLabel(): string
    {
        return __('erp.resources.offer.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.offer.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Offer ? $record->title() : __('erp.resources.offer.singular');
    }

    public static function form(Schema $schema): Schema
    {
        return OfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OffersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffers::route('/'),
            'create' => CreateOffer::route('/create'),
            'edit' => EditOffer::route('/{record}/edit'),
        ];
    }
}
