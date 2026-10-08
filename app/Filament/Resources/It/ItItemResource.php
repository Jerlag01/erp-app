<?php

namespace App\Filament\Resources\It;

use App\Actions\It\CheckoutAsset;
use App\Actions\It\CheckoutItem;
use App\Enums\ItCategoryType;
use App\Enums\ItItemKind;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Models\ItItem;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Shared base for accessories, consumables and components: the same table (it_items), one kind each.
 */
abstract class ItItemResource extends Resource
{
    use ScopesToVisibleRecords {
        getEloquentQuery as protected visibleQuery;
    }

    protected static ?string $model = ItItem::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?string $recordTitleAttribute = 'name';

    abstract public static function kind(): ItItemKind;

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_'.static::kind()->value.'.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_'.static::kind()->value.'.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return static::visibleQuery()->kind(static::kind());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(static::getModelLabel())
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('erp.fields.name'))
                            ->required()
                            ->maxLength(150),
                        Select::make('it_category_id')
                            ->label(__('erp.fields.category'))
                            ->relationship('category', 'name', fn (Builder $query) => $query->ofType(ItCategoryType::from(static::kind()->value)))
                            ->preload(),
                        Select::make('it_manufacturer_id')
                            ->label(__('erp.resources.it_manufacturer.singular'))
                            ->relationship('manufacturer', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->label(__('erp.fields.name'))->required()->maxLength(100)]),
                        TextInput::make('model_number')
                            ->label(__('erp.fields.model'))
                            ->maxLength(100),
                        TextInput::make('serial')
                            ->label(__('erp.fields.serial_number'))
                            ->maxLength(100)
                            ->visible(static::kind() === ItItemKind::Component),
                        TextInput::make('quantity')
                            ->label(__('erp.fields.quantity'))
                            ->helperText(__('erp.help.it_quantity_'.static::kind()->value))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(1)
                            ->required(),
                        TextInput::make('min_quantity')
                            ->label(__('erp.fields.low_stock_threshold'))
                            ->numeric()
                            ->integer()
                            ->minValue(0),
                        Select::make('location_id')
                            ->label(__('erp.resources.location.singular'))
                            ->relationship('location', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                            ->preload()
                            ->visible(fn (): bool => modules()->locations()),
                        FileUpload::make('image')
                            ->label(__('erp.fields.image'))
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('it-items')
                            ->visibility('public'),
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
                            ->label(__('erp.fields.unit_cost'))
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
                        TextEntry::make('category.name')->label(__('erp.fields.category'))->placeholder('—'),
                        TextEntry::make('manufacturer.name')->label(__('erp.resources.it_manufacturer.singular'))->helperText(fn (ItItem $record): ?string => $record->model_number)->placeholder('—'),
                        TextEntry::make('available')
                            ->label(__('erp.it.available_count'))
                            ->state(fn (ItItem $record): string => static::kind() === ItItemKind::Consumable ? (string) $record->available() : "{$record->available()} / {$record->quantity}")
                            ->badge()
                            ->color(fn (ItItem $record): string => $record->isLow() ? 'danger' : 'success'),
                        TextEntry::make('location.name')->label(__('erp.resources.location.singular'))->placeholder('—')->visible(fn (): bool => modules()->locations()),
                        TextEntry::make('serial')->label(__('erp.fields.serial_number'))->placeholder('—')->visible(static::kind() === ItItemKind::Component),
                        TextEntry::make('supplier.name')->label(__('erp.fields.supplier'))->placeholder('—'),
                        TextEntry::make('purchase_date')->label(__('erp.fields.purchase_date'))->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('notes')->label(__('erp.fields.notes'))->columnSpanFull()->visible(fn (ItItem $record): bool => filled($record->notes)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'manufacturer', 'location'])
                ->withSum(['assignments as in_use' => fn (Builder $query) => $query->whereNull('returned_at')], 'quantity'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(32)
                    ->toggleable(),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (ItItem $record): ?string => trim(($record->manufacturer?->name ?? '').' '.($record->model_number ?? '')) ?: null)
                    ->weight('bold')
                    ->searchable(['name', 'model_number', 'serial'])
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('erp.fields.category'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('location.name')
                    ->label(__('erp.resources.location.singular'))
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->locations()),
                TextColumn::make('available')
                    ->label(__('erp.it.available_count'))
                    ->state(fn (ItItem $record): int => static::kind() === ItItemKind::Consumable ? $record->quantity : max(0, $record->quantity - (int) $record->in_use))
                    ->formatStateUsing(fn (int $state, ItItem $record): string => static::kind() === ItItemKind::Consumable ? (string) $state : "{$state} / {$record->quantity}")
                    ->badge()
                    ->color(fn (int $state, ItItem $record): string => $record->min_quantity !== null && $state <= $record->min_quantity ? 'danger' : 'success'),
            ])
            ->filters([
                Filter::make('low')
                    ->label(__('erp.stock.low'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('min_quantity')->whereRaw(
                        static::kind() === ItItemKind::Consumable
                            ? 'it_items.quantity <= it_items.min_quantity'
                            : 'it_items.quantity - coalesce((select sum(quantity) from it_item_assignments where it_item_assignments.it_item_id = it_items.id and returned_at is null), 0) <= it_items.min_quantity'
                    )),
            ])
            ->recordActions([
                static::checkoutAction(),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    /**
     * Hand out pieces: accessories and consumables to a user, components into an asset.
     */
    public static function checkoutAction(): Action
    {
        $kind = static::kind();

        return Action::make('checkout')
            ->label(__('erp.it.handout.'.$kind->value))
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('primary')
            ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('it_items.checkout'))
            ->disabled(fn (ItItem $record): bool => $record->available() === 0)
            ->modalHeading(fn (ItItem $record): string => __('erp.it.handout.'.$kind->value).': '.$record->name)
            ->modalDescription(fn (ItItem $record): string => __('erp.it.available_now', ['count' => $record->available()]))
            ->schema(fn (ItItem $record): array => [
                ...ItTargetFields::make([$kind === ItItemKind::Component ? 'asset' : 'user']),
                TextInput::make('quantity')->label(__('erp.fields.quantity'))->numeric()->integer()->minValue(1)->maxValue($record->available())->default(1)->required(),
                TextInput::make('note')->label(__('erp.fields.note'))->maxLength(255),
            ])
            ->fillForm(['target_type' => $kind === ItItemKind::Component ? 'asset' : 'user', 'quantity' => 1])
            ->action(fn (ItItem $record, array $data) => app(CheckoutItem::class)->handle($record, CheckoutAsset::resolveTarget($data), (int) $data['quantity'], $data['note'] ?? null))
            ->successNotificationTitle(__('erp.it.checked_out_done'));
    }

    public static function getRelations(): array
    {
        return [
            ItItemAssignmentsRelationManager::class,
            ItLogsRelationManager::class,
        ];
    }
}
