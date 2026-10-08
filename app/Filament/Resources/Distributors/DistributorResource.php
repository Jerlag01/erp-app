<?php

namespace App\Filament\Resources\Distributors;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Distributors\Pages\CreateDistributor;
use App\Filament\Resources\Distributors\Pages\EditDistributor;
use App\Filament\Resources\Distributors\Pages\ListDistributors;
use App\Filament\Resources\Distributors\RelationManagers\ContactsRelationManager;
use App\Filament\Schemas\CompanyFields;
use App\Models\Distributor;
use App\Services\Distributors\DistributorClients;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DistributorResource extends Resource
{
    protected static ?string $model = Distributor::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.distributor.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.distributor.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $hasProvider = fn (Get $get): bool => filled($get('price_provider'));

        return $schema->components([
            ...CompanyFields::make('distributors', [
                TextInput::make('store_url')
                    ->label(__('erp.fields.store_url'))
                    ->url()
                    ->rule('url:http,https')
                    ->prefixIcon(Heroicon::OutlinedShoppingCart)
                    ->maxLength(255),
                Select::make('manufacturers')
                    ->label(__('erp.resources.manufacturer.plural'))
                    ->relationship('manufacturers', 'name')
                    ->multiple()
                    ->preload()
                    ->columnSpanFull(),
            ]),
            // Credentials for the price/catalogue connection: administrators only.
            Section::make(__('erp.sections.distributor_api'))
                ->description(__('erp.help.distributor_api'))
                ->columns(2)
                ->collapsible()
                ->columnSpanFull()
                ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                ->schema([
                    Select::make('price_provider')
                        ->label(__('erp.fields.price_provider'))
                        ->options(DistributorClients::options())
                        ->placeholder(__('erp.stock.provider.manual'))
                        ->live(),
                    TextInput::make('api_customer_number')
                        ->label(__('erp.fields.api_customer_number'))
                        ->maxLength(50)
                        ->visible($hasProvider),
                    TextInput::make('api_username')
                        ->label(__('erp.settings.fields.username'))
                        ->autocomplete('off')
                        ->maxLength(255)
                        ->visible($hasProvider),
                    TextInput::make('api_password')
                        ->label(__('erp.fields.password'))
                        ->helperText(__('erp.settings.help.mail_password'))
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                        ->visible($hasProvider),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['manufacturers', 'contacts'])->withCount('stockItems'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(32),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->description(fn (Distributor $record): ?string => $record->store_url ?? $record->website)
                    ->url(fn (Distributor $record): ?string => $record->store_url, shouldOpenInNewTab: true)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('manufacturers.name')
                    ->label(__('erp.resources.manufacturer.plural'))
                    ->badge()
                    ->color('gray')
                    ->limitList(4)
                    ->placeholder('—'),
                TextColumn::make('contacts')
                    ->label(__('erp.resources.distributor_contact.plural'))
                    ->state(fn (Distributor $record): ?string => $record->contacts->map->fullName()->implode(', ') ?: null)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('price_provider')
                    ->label(__('erp.fields.price_provider'))
                    ->formatStateUsing(fn (?string $state): string => DistributorClients::options()[$state] ?? __('erp.stock.provider.manual'))
                    ->badge()
                    ->color(fn (Distributor $record): string => $record->client()?->isConfigured() ? 'success' : 'gray')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                TextColumn::make('stock_items_count')
                    ->label(__('erp.resources.stock_item.plural'))
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDistributors::route('/'),
            'create' => CreateDistributor::route('/create'),
            'edit' => EditDistributor::route('/{record}/edit'),
        ];
    }
}
