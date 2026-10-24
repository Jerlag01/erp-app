<?php

namespace App\Filament\Resources\BaeOrders;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\BaeOrders\Pages\CreateBaeOrder;
use App\Filament\Resources\BaeOrders\Pages\EditBaeOrder;
use App\Filament\Resources\BaeOrders\Pages\ListBaeOrders;
use App\Filament\Resources\BaeOrders\Schemas\BaeOrderForm;
use App\Models\BaeOrder;
use App\Support\PdfAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * BAE orders (Excel "calc.xlsx"): one per 604 project, made when the project is created, filled in
 * here and exported to PDF for the supplier. Only with "batteries.bae_orders".
 */
class BaeOrderResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = BaeOrder::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'bae-orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.batteries.bae_order.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.batteries.bae_order.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return BaeOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite', 'project.category', 'batteryModel']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.projects.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (BaeOrder $record): string => $record->cowLine())
                    ->searchable(),
                TextColumn::make('order_number')->label(__('erp.batteries.bae_order.fields.order_number'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('order_date')->label(__('erp.batteries.bae_order.fields.order_date'))->date('d/m/Y')->sortable(),
                TextColumn::make('batteryModel.name')
                    ->label(__('erp.batteries.bae_order.fields.battery_model'))
                    ->description(fn (BaeOrder $record): string => $record->batteryCount().' × '.$record->cells_per_battery.' '.__('erp.batteries.cells'))
                    ->placeholder('—'),
                TextColumn::make('total')
                    ->label(__('erp.batteries.bae_order.fields.total'))
                    ->state(fn (BaeOrder $record): float => $record->total())
                    ->money('EUR', locale: 'nl_BE')
                    ->alignEnd(),
                TextColumn::make('exported_at')
                    ->label(__('erp.batteries.bae_order.fields.exported_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('erp.batteries.bae_order.not_exported'))
                    ->badge()
                    ->color(fn (BaeOrder $record): string => $record->isExported() ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('exported_at')->label(__('erp.batteries.bae_order.fields.exported_at'))->nullable(),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'batteries'))
                    ->url(fn (BaeOrder $record): string => route('batteries.bae-order-pdf', $record), shouldOpenInNewTab: true),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBaeOrders::route('/'),
            'create' => CreateBaeOrder::route('/create'),
            'edit' => EditBaeOrder::route('/{record}/edit'),
        ];
    }
}
