<?php

namespace App\Filament\Resources\OrderReferences;

use App\Actions\GenerateOrderReference;
use App\Enums\NavigationGroup;
use App\Filament\Resources\DistributorOrders\DistributorOrderResource;
use App\Filament\Resources\OrderReferences\Pages\ManageOrderReferences;
use App\Filament\Resources\OrderReferences\Widgets\OrderReferenceCounter;
use App\Models\OrderReference;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class OrderReferenceResource extends Resource
{
    protected static ?string $model = OrderReference::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Purchasing;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['reference', 'supplier', 'project'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.order_reference.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.order_reference.plural');
    }

    /**
     * Only the details are editable; the reference itself is fixed once it is taken.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(self::detailFields());
    }

    /**
     * Supplier and project / site are required, also when taking a new number.
     *
     * @return list<TextInput|Textarea>
     */
    public static function detailFields(): array
    {
        return [
            TextInput::make('supplier')
                ->label(__('erp.fields.supplier'))
                ->required()
                ->maxLength(150),
            TextInput::make('project')
                ->label(__('erp.fields.project'))
                ->required()
                ->maxLength(150),
            Textarea::make('description')
                ->label(__('erp.fields.description'))
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('distributorOrders'))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('year')->orderByDesc('number'))
            ->columns([
                TextColumn::make('reference')
                    ->label(__('erp.fields.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->color('primary')
                    ->copyable()
                    ->copyMessage(__('erp.orders.copied'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('erp.fields.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('erp.fields.taken_by'))
                    ->placeholder('—'),
                TextColumn::make('supplier')
                    ->label(__('erp.fields.supplier'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('project')
                    ->label(__('erp.fields.project'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('erp.fields.description'))
                    ->limit(60)
                    ->tooltip(fn (OrderReference $record): ?string => $record->description)
                    ->placeholder('—')
                    ->searchable(),
                // The orders at distributors linked to this reference (Purchasing > Orders).
                TextColumn::make('distributor_orders_count')
                    ->label(__('erp.distributor_orders.plural'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
                    ->url(fn (OrderReference $record): ?string => $record->distributor_orders_count > 0 ? DistributorOrderResource::getUrl('index', ['tableSearch' => $record->reference]) : null),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('erp.fields.year'))
                    ->options(fn (): array => OrderReference::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all())
                    ->default((string) date('Y')),
                SelectFilter::make('user')
                    ->label(__('erp.fields.taken_by'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(fn (OrderReference $record): string => $record->reference),
                Action::make('withdraw')
                    ->label(__('erp.orders.withdraw'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (OrderReference $record): bool => Gate::allows('delete', $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (OrderReference $record): string => __('erp.orders.withdraw').': '.$record->reference)
                    ->modalDescription(__('erp.orders.withdraw_help'))
                    ->action(fn (OrderReference $record) => app(GenerateOrderReference::class)->withdraw($record))
                    ->successNotificationTitle(__('erp.orders.withdrawn')),
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            OrderReferenceCounter::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrderReferences::route('/'),
        ];
    }
}
