<?php

namespace App\Filament\Resources\ItSuppliers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ItSuppliers\Pages\CreateItSupplier;
use App\Filament\Resources\ItSuppliers\Pages\EditItSupplier;
use App\Filament\Resources\ItSuppliers\Pages\ListItSuppliers;
use App\Filament\Schemas\CompanyFields;
use App\Models\ItSupplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The IT module's own list, kept apart from the stock module on purpose.
 */
class ItSupplierResource extends Resource
{
    protected static ?string $model = ItSupplier::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'it-suppliers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_supplier.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_supplier.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            ...CompanyFields::make('it-suppliers', [
                TextInput::make('store_url')
                    ->label(__('erp.fields.store_url'))
                    ->url()
                    ->rule('url:http,https')
                    ->prefixIcon(Heroicon::OutlinedShoppingCart)
                    ->maxLength(255),
            ]),
            Section::make(__('erp.sections.it_contact'))
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('contact_name')
                            ->label(__('erp.fields.contact_name'))
                            ->maxLength(120),
                        TextInput::make('contact_email')
                            ->label(__('erp.fields.email'))
                            ->email()
                            ->maxLength(150),
                        TextInput::make('contact_phone')
                            ->label(__('erp.fields.phone'))
                            ->tel()
                            ->maxLength(30),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('assets'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(32),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->description(fn (ItSupplier $record): ?string => $record->website)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label(__('erp.fields.contact_name'))
                    ->description(fn (ItSupplier $record): ?string => $record->contact_phone ?? $record->contact_email)
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label(__('erp.fields.phone'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('assets_count')
                    ->label(__('erp.resources.it_asset.plural'))
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                // The policy blocks deleting entries that IT records still use.
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItSuppliers::route('/'),
            'create' => CreateItSupplier::route('/create'),
            'edit' => EditItSupplier::route('/{record}/edit'),
        ];
    }
}
