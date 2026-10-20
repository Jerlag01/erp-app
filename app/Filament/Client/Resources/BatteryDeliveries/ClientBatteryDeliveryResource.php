<?php

namespace App\Filament\Client\Resources\BatteryDeliveries;

use App\Filament\Client\ClientResource;
use App\Models\BatteryDelivery;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The battery deliveries: project, order confirmation, battery model, pieces, planned date and whether
 * delivered. The cost and the internal notes are never shown.
 */
class ClientBatteryDeliveryResource extends ClientResource
{
    protected static ?string $model = BatteryDelivery::class;

    protected static ?string $slug = 'battery-deliveries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 2;

    public static function permission(): string
    {
        return ClientPortal::BATTERY_DELIVERIES;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->batteries();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.client_portal.battery_deliveries');
    }

    public static function getModelLabel(): string
    {
        return __('erp.client_portal.battery_delivery');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.client_portal.battery_deliveries');
    }

    public static function getEloquentQuery(): Builder
    {
        return BatteryDelivery::query()->with(['project.workSite', 'batteryModel']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('planned_delivery_date', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('erp.client_portal.no_deliveries'))
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.client_portal.project_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('project.cow_code')
                    ->label(__('erp.client_portal.cow_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (BatteryDelivery $record): ?string => $record->project?->workSite?->city)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('order_confirmation')
                    ->label(__('erp.client_portal.order_confirmation'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('batteryModel.name')
                    ->label(__('erp.client_portal.battery_model'))
                    ->placeholder('—'),
                TextColumn::make('pieces')
                    ->label(__('erp.client_portal.pieces'))
                    ->alignEnd()
                    ->placeholder('—'),
                TextColumn::make('planned_delivery_date')
                    ->label(__('erp.client_portal.planned_delivery'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('executed')
                    ->label(__('erp.client_portal.delivered'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('executed')->label(__('erp.client_portal.delivered')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListClientBatteryDeliveries::route('/')];
    }
}
