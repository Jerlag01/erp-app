<?php

namespace App\Filament\Resources\DistributorOrders;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Distributor;
use App\Models\DistributorOrder;
use App\Models\DistributorOrderLine;
use App\Models\Expense;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * "Orders": the orders placed at the distributors, with a tab per distributor that has an API. Per order
 * the distributor's number, our order reference (linked automatically when it is in the order), what
 * was ordered with its references, and the delivery status. Orders come from the API (read-only) or are
 * added by hand.
 */
class DistributorOrderResource extends Resource
{
    protected static ?string $model = DistributorOrder::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'distributor-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Purchasing;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'external_number';

    public static function getModelLabel(): string
    {
        return __('erp.distributor_orders.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.distributor_orders.plural');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(DistributorOrder::STATUSES)->mapWithKeys(fn (string $status): array => [$status => __('erp.distributor_orders.statuses.'.$status)])->all();
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            DistributorOrder::DELIVERED => 'success',
            DistributorOrder::PARTIAL => 'warning',
            DistributorOrder::CANCELLED => 'danger',
            DistributorOrder::CONFIRMED => 'info',
            default => 'gray',
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('distributor_id')
                    ->label(__('erp.distributor_orders.distributor'))
                    ->relationship('distributor', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('external_number')->label(__('erp.distributor_orders.external_number'))->maxLength(100),
                TextInput::make('customer_reference')->label(__('erp.distributor_orders.customer_reference'))->helperText(__('erp.distributor_orders.customer_reference_help'))->maxLength(255),
                Select::make('order_reference_id')
                    ->label(__('erp.distributor_orders.order_reference'))
                    ->helperText(__('erp.distributor_orders.order_reference_help'))
                    ->relationship('orderReference', 'reference', fn (Builder $query) => $query->latest('id'))
                    ->searchable(['reference', 'supplier', 'project']),
                DatePicker::make('ordered_at')->label(__('erp.distributor_orders.ordered_at'))->default(today()),
                Select::make('status')->label(__('erp.fields.status'))->options(self::statusOptions())->default(DistributorOrder::OPEN)->required()->selectablePlaceholder(false),
                DatePicker::make('expected_delivery')->label(__('erp.distributor_orders.expected_delivery')),
                DatePicker::make('delivered_at')->label(__('erp.distributor_orders.delivered_at')),
                TextInput::make('total')->label(__('erp.distributor_orders.total'))->numeric()->prefix('€'),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->columnSpanFull(),
                Repeater::make('lines')
                    ->label(__('erp.distributor_orders.lines'))
                    ->relationship()
                    ->orderColumn('line_number')
                    ->columns(6)
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->schema([
                        TextInput::make('distributor_reference')->label(__('erp.distributor_orders.distributor_reference'))->maxLength(100),
                        TextInput::make('manufacturer_reference')->label(__('erp.distributor_orders.manufacturer_reference'))->maxLength(100),
                        TextInput::make('description')->label(__('erp.fields.description'))->maxLength(255)->columnSpan(2),
                        TextInput::make('quantity')->label(__('erp.distributor_orders.quantity'))->numeric()->default(1)->required(),
                        TextInput::make('quantity_delivered')->label(__('erp.distributor_orders.quantity_delivered'))->numeric()->default(0),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 2, 'lg' => 4])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('distributor.name')->label(__('erp.distributor_orders.distributor'))->weight(FontWeight::Bold),
                    TextEntry::make('external_number')->label(__('erp.distributor_orders.external_number'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                    TextEntry::make('orderReference.reference')->label(__('erp.distributor_orders.order_reference'))->fontFamily(FontFamily::Mono)->badge()->color('success')
                        ->helperText(fn (DistributorOrder $record): ?string => $record->orderReference ? trim(implode(' · ', array_filter([$record->orderReference->supplier, $record->orderReference->project, $record->orderReference->description]))) : null)
                        ->placeholder(__('erp.distributor_orders.not_linked')),
                    TextEntry::make('customer_reference')->label(__('erp.distributor_orders.customer_reference'))->placeholder('—'),
                    TextEntry::make('ordered_at')->label(__('erp.distributor_orders.ordered_at'))->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('status')->label(__('erp.fields.status'))->badge()->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state)->color(fn (string $state): string => self::statusColor($state)),
                    TextEntry::make('delivery')->label(__('erp.distributor_orders.delivery'))->state(fn (DistributorOrder $record): string => $record->deliveryProgress()),
                    TextEntry::make('expected_delivery')->label(__('erp.distributor_orders.expected_delivery'))->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('delivered_at')->label(__('erp.distributor_orders.delivered_at'))->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('total')->label(__('erp.distributor_orders.total'))->money('EUR')->placeholder('—'),
                    TextEntry::make('source')->label(__('erp.distributor_orders.source'))->formatStateUsing(fn (string $state): string => __('erp.distributor_orders.sources.'.$state))
                        ->helperText(fn (DistributorOrder $record): ?string => $record->synced_at ? __('erp.distributor_orders.synced', ['date' => $record->synced_at->format('d/m/Y H:i')]) : null),
                    TextEntry::make('expense.supplier_invoice_number')->label(__('erp.distributor_orders.invoice'))
                        ->visible(fn (DistributorOrder $record): bool => $record->expense_id !== null)
                        ->url(fn (DistributorOrder $record): ?string => auth()->user()?->can('viewAny', Expense::class) ? ExpenseResource::getUrl('index', ['search' => $record->expense?->supplier_invoice_number]) : null),
                    TextEntry::make('notes')->label(__('erp.fields.notes'))->placeholder('—')->columnSpanFull(),
                ]),
            Section::make(__('erp.distributor_orders.lines'))
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('lines')
                        ->hiddenLabel()
                        ->columns(['default' => 2, 'lg' => 6])
                        ->schema([
                            TextEntry::make('distributor_reference')->label(__('erp.distributor_orders.distributor_reference'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                            TextEntry::make('manufacturer_reference')->label(__('erp.distributor_orders.manufacturer_reference'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                            TextEntry::make('description')->label(__('erp.fields.description'))->placeholder('—')->columnSpan(2),
                            TextEntry::make('quantity')->label(__('erp.distributor_orders.quantity_short'))
                                ->state(fn (DistributorOrderLine $record): string => rtrim(rtrim(number_format((float) $record->quantity_delivered, 3, ',', ''), '0'), ',').' / '.rtrim(rtrim(number_format((float) $record->quantity, 3, ',', ''), '0'), ',').($record->unit ? ' '.$record->unit : ''))
                                ->badge()
                                ->color(fn (DistributorOrderLine $record): string => $record->isDelivered() ? 'success' : ((float) $record->quantity_delivered > 0 ? 'warning' : 'gray')),
                            TextEntry::make('stockItem.name')->label(__('erp.distributor_orders.stock_item'))->placeholder('—'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['distributor', 'orderReference', 'lines']))
            ->defaultSort('ordered_at', 'desc')
            ->emptyStateHeading(__('erp.distributor_orders.none'))
            ->emptyStateDescription(__('erp.distributor_orders.none_help'))
            ->columns([
                TextColumn::make('external_number')->label(__('erp.distributor_orders.external_number'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable()->placeholder('—')
                    ->description(fn (DistributorOrder $record): string => $record->distributor?->name ?? ''),
                TextColumn::make('orderReference.reference')->label(__('erp.distributor_orders.order_reference'))->fontFamily(FontFamily::Mono)->badge()->color('success')->searchable()
                    ->placeholder(__('erp.distributor_orders.not_linked'))
                    ->description(fn (DistributorOrder $record): ?string => $record->orderReference?->project),
                TextColumn::make('customer_reference')->label(__('erp.distributor_orders.customer_reference'))->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('ordered_at')->label(__('erp.distributor_orders.ordered_at'))->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('status')->label(__('erp.fields.status'))->badge()->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state)->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('delivery')->label(__('erp.distributor_orders.delivery'))->state(fn (DistributorOrder $record): string => $record->deliveryProgress()),
                TextColumn::make('expected_delivery')->label(__('erp.distributor_orders.expected_delivery'))->date('d/m/Y')->sortable()->placeholder('—'),
                TextColumn::make('total')->label(__('erp.distributor_orders.total'))->money('EUR')->placeholder('—')->toggleable(),
                TextColumn::make('lines.distributor_reference')->label(__('erp.distributor_orders.references'))->listWithLineBreaks()->limitList(3)->fontFamily(FontFamily::Mono)
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('lines', fn (Builder $query) => $query->where('distributor_reference', 'like', "%{$search}%")->orWhere('manufacturer_reference', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('erp.fields.status'))->options(self::statusOptions())->multiple(),
                TernaryFilter::make('linked')->label(__('erp.distributor_orders.linked'))
                    ->queries(true: fn (Builder $query) => $query->whereNotNull('order_reference_id'), false: fn (Builder $query) => $query->whereNull('order_reference_id')),
            ])
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()]);
    }

    /**
     * Distributors with an API connection: each gets its own tab.
     *
     * @return Collection<int, Distributor>
     */
    public static function apiDistributors(): Collection
    {
        return Distributor::query()->whereNotNull('price_provider')->orderBy('name')->get();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDistributorOrders::route('/'),
            'create' => Pages\CreateDistributorOrder::route('/create'),
            'view' => Pages\ViewDistributorOrder::route('/{record}'),
            'edit' => Pages\EditDistributorOrder::route('/{record}/edit'),
        ];
    }
}
