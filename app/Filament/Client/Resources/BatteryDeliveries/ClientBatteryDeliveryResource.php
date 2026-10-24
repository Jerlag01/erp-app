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
 * The battery deliveries: project, order confirmation, battery model, pieces, planned and actual delivery
 * date, when the installation is planned and whether (and when) the project was executed. The cost, the
 * internal notes and the delivery documents are never shown.
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
        $user = ClientPortal::user();

        // Deliveries follow their project: only those of the projects the user sees.
        return BatteryDelivery::query()
            ->with(['project' => fn ($query) => $query->with(['workSite', 'batteryDossier'])->withMin('planningItems', 'date'), 'batteryModel'])
            ->whereHas('project', fn (Builder $query) => $user ? $user->restrictProjects($query) : $query->whereRaw('1 = 0'));
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
                // Ticked in the employees' delivery overview ("delivered"), with the date it was ticked.
                IconColumn::make('delivered')
                    ->label(__('erp.client_portal.delivered'))
                    ->boolean(),
                TextColumn::make('delivered_at')
                    ->label(__('erp.client_portal.actual_delivery'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('installation_planned')
                    ->label(__('erp.client_portal.installation_planned'))
                    ->state(fn (BatteryDelivery $record): ?string => $record->plannedInstallationDate()?->format('d/m/Y'))
                    ->placeholder('—'),
                // A check mark with the date the project went to "executed".
                TextColumn::make('project.executed_at')
                    ->label(__('erp.client_portal.installation_executed'))
                    ->date('d/m/Y')
                    ->icon(Heroicon::CheckCircle)
                    ->iconColor('success')
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('delivered')->label(__('erp.client_portal.delivered')),
                TernaryFilter::make('project_executed')
                    ->label(__('erp.client_portal.installation_executed'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('project', fn (Builder $query) => $query->whereNotNull('executed_at')),
                        false: fn (Builder $query) => $query->whereHas('project', fn (Builder $query) => $query->whereNull('executed_at')),
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListClientBatteryDeliveries::route('/')];
    }
}
