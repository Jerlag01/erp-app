<?php

namespace App\Filament\Resources\ItLicenses;

use App\Enums\ItCategoryType;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\It\ItLogsRelationManager;
use App\Filament\Resources\ItLicenses\Pages\CreateItLicense;
use App\Filament\Resources\ItLicenses\Pages\EditItLicense;
use App\Filament\Resources\ItLicenses\Pages\ListItLicenses;
use App\Filament\Resources\ItLicenses\Pages\ViewItLicense;
use App\Filament\Resources\ItLicenses\RelationManagers\SeatsRelationManager;
use App\Models\ItLicense;
use App\Support\DueDate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ItLicenseResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = ItLicense::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'it-licenses';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_license.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_license.plural');
    }

    public static function canSeeKeys(): bool
    {
        return (bool) auth()->user()?->hasPermission('it_licenses.view_keys');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('erp.resources.it_license.singular'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('erp.fields.name'))
                            ->placeholder('Microsoft 365 Business Standard')
                            ->required()
                            ->maxLength(150),
                        Select::make('it_category_id')
                            ->label(__('erp.fields.category'))
                            ->relationship('category', 'name', fn (Builder $query) => $query->ofType(ItCategoryType::License))
                            ->preload(),
                        Select::make('it_manufacturer_id')
                            ->label(__('erp.resources.it_manufacturer.singular'))
                            ->relationship('manufacturer', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100)]),
                        TextInput::make('seats')
                            ->label(__('erp.fields.seats'))
                            ->helperText(__('erp.help.seats'))
                            ->numeric()
                            ->integer()
                            ->minValue(fn (?ItLicense $record): int => $record ? max(1, $record->licenseSeats()->where(fn (Builder $query) => $query->whereNotNull('user_id')->orWhereNotNull('it_asset_id'))->count()) : 1)
                            ->maxValue(10000)
                            ->default(1)
                            ->required(),
                        // Hidden without the key permission: a hidden field is not saved, so the key stays.
                        Textarea::make('product_key')
                            ->label(__('erp.fields.product_key'))
                            ->rows(2)
                            ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);'])
                            ->visible(fn (): bool => static::canSeeKeys())
                            ->columnSpanFull(),
                        TextInput::make('licensed_to_name')
                            ->label(__('erp.fields.licensed_to_name'))
                            ->maxLength(150),
                        TextInput::make('licensed_to_email')
                            ->label(__('erp.fields.licensed_to_email'))
                            ->email()
                            ->maxLength(150),
                        DatePicker::make('expiration_date')
                            ->label(__('erp.fields.expiration_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Toggle::make('reassignable')
                            ->label(__('erp.fields.reassignable'))
                            ->helperText(__('erp.help.reassignable'))
                            ->default(true)
                            ->inline(false),
                    ]),
                Section::make(__('erp.sections.purchase'))
                    ->columns(['default' => 1, 'md' => 3])
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('it_supplier_id')
                            ->label(__('erp.fields.supplier'))
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100)]),
                        TextInput::make('order_number')
                            ->label(__('erp.fields.order_number'))
                            ->maxLength(50),
                        DatePicker::make('purchase_date')
                            ->label(__('erp.fields.purchase_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        TextInput::make('purchase_cost')
                            ->label(__('erp.fields.purchase_price'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€'),
                        Textarea::make('notes')
                            ->label(__('erp.fields.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('manufacturer.name')->label(__('erp.resources.it_manufacturer.singular'))->placeholder('—'),
                        TextEntry::make('category.name')->label(__('erp.fields.category'))->placeholder('—'),
                        TextEntry::make('seats')
                            ->label(__('erp.fields.seats'))
                            ->formatStateUsing(fn (ItLicense $record): string => __('erp.it.seats_free', ['free' => $record->freeSeats(), 'total' => $record->seats])),
                        TextEntry::make('expiration_date')
                            ->label(__('erp.fields.expiration_date'))
                            ->date('d/m/Y')
                            ->badge()
                            ->color(fn (ItLicense $record): string => DueDate::color($record->expiration_date))
                            ->placeholder('—'),
                        TextEntry::make('product_key')
                            ->label(__('erp.fields.product_key'))
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->placeholder('—')
                            ->visible(fn (): bool => static::canSeeKeys())
                            ->columnSpanFull(),
                        TextEntry::make('licensed_to_name')->label(__('erp.fields.licensed_to_name'))->helperText(fn (ItLicense $record): ?string => $record->licensed_to_email)->placeholder('—'),
                        TextEntry::make('supplier.name')->label(__('erp.fields.supplier'))->placeholder('—'),
                        TextEntry::make('purchase_date')->label(__('erp.fields.purchase_date'))->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('order_number')->label(__('erp.fields.order_number'))->placeholder('—'),
                        TextEntry::make('notes')->label(__('erp.fields.notes'))->columnSpanFull()->visible(fn (ItLicense $record): bool => filled($record->notes)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['manufacturer', 'category'])->withCount(['licenseSeats as used_seats' => fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNotNull('user_id')->orWhereNotNull('it_asset_id'))]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (ItLicense $record): ?string => $record->manufacturer?->name)
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('erp.fields.category'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('seats')
                    ->label(__('erp.fields.seats'))
                    ->formatStateUsing(fn (ItLicense $record): string => "{$record->used_seats} / {$record->seats}")
                    ->badge()
                    ->color(fn (ItLicense $record): string => $record->used_seats >= $record->seats ? 'danger' : 'success'),
                TextColumn::make('expiration_date')
                    ->label(__('erp.fields.expiration_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (ItLicense $record): string => DueDate::color($record->expiration_date))
                    ->description(fn (ItLicense $record): ?string => DueDate::description($record->expiration_date))
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('expiring')
                    ->label(__('erp.it.expiring'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->expiring()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SeatsRelationManager::class,
            ItLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItLicenses::route('/'),
            'create' => CreateItLicense::route('/create'),
            'view' => ViewItLicense::route('/{record}'),
            'edit' => EditItLicense::route('/{record}/edit'),
        ];
    }
}
