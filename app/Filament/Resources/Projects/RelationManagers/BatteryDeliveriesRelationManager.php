<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\BatteryDeliveries\DeliveryDocuments;
use App\Models\BatteryDelivery;
use App\Models\Project;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The battery deliveries of a 604 project with their documents (order to the supplier, the
 * supplier's confirmation, delivery note), for those who may see them.
 */
class BatteryDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'batteryDeliveries';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedTruck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.batteries.delivery_documents.tab');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Project $ownerRecord */
        return modules()->batteries()
            && Gate::allows('viewAny', BatteryDelivery::class)
            && (bool) auth()->user()?->hasPermission('batteries.documents')
            && $ownerRecord->batteryDeliveries()->exists();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->label(__('erp.batteries.order_number'))->state(fn (BatteryDelivery $record): string => $record->orderNumber())->fontFamily(FontFamily::Mono),
                TextColumn::make('order_confirmation')->label(__('erp.batteries.order_confirmation'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                TextColumn::make('batteryModel.name')->label(__('erp.batteries.model'))->placeholder('—'),
                TextColumn::make('pieces')->label(__('erp.batteries.pieces'))->placeholder('—'),
                TextColumn::make('planned_delivery_date')->label(__('erp.batteries.planned_delivery_date'))->date('d/m/Y')->placeholder('—'),
                IconColumn::make('executed')->label(__('erp.batteries.delivered'))->boolean(),
                DeliveryDocuments::column(),
            ])
            ->recordActions([DeliveryDocuments::action()]);
    }
}
