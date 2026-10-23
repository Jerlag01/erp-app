<?php

namespace App\Filament\Client\Resources\ChargingStations;

use App\Enums\ChargingStationType;
use App\Filament\Client\ClientResource;
use App\Models\ChargingStation;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The charging stations per COW site. The password, recovery code and licence key are only shown with
 * the "charging secrets" permission.
 */
class ClientChargingStationResource extends ClientResource
{
    protected static ?string $model = ChargingStation::class;

    protected static ?string $slug = 'charging-stations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 3;

    public static function permission(): string
    {
        return ClientPortal::CHARGING_STATIONS;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->charging();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.client_portal.charging_stations');
    }

    public static function getModelLabel(): string
    {
        return __('erp.client_portal.charging_station');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.client_portal.charging_stations');
    }

    public static function getEloquentQuery(): Builder
    {
        // Only stations at a COW site: those of third-party clients never show on the portal.
        $query = ChargingStation::query()->with(['workSite', 'project'])->whereNotNull('charging_stations.work_site_id');

        return ClientPortal::user()?->restrictToOwnSites($query, orOwnProject: true) ?? $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        $secrets = fn (): bool => ClientPortal::may(ClientPortal::CHARGING_SECRETS);

        return $table
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('erp.client_portal.no_stations'))
            ->columns([
                TextColumn::make('workSite.cow_code')
                    ->label(__('erp.client_portal.cow_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->state(fn (ChargingStation $record): ?string => $record->locationLabel())
                    ->description(fn (ChargingStation $record): ?string => $record->cityName())
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('project.reference')
                    ->label(__('erp.client_portal.project_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ace_number')
                    ->label(__('erp.charging.ace_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label(__('erp.charging.type'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('brand')
                    ->label(__('erp.charging.brand'))
                    ->description(fn (ChargingStation $record): ?string => $record->system?->getLabel())
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label(__('erp.charging.quantity'))
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('connectivity')
                    ->label(__('erp.charging.connectivity'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('backoffice')
                    ->label(__('erp.charging.backoffice'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('installation_year')
                    ->label(__('erp.charging.installation_year'))
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('online')->label(__('erp.charging.online'))->boolean(),
                IconColumn::make('in_service')->label(__('erp.charging.in_service'))->boolean(),
                IconColumn::make('fully_executed')->label(__('erp.charging.fully_executed'))->boolean()->toggleable(),
                TextColumn::make('onsite_warranty_end')
                    ->label(__('erp.charging.onsite_warranty_end'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sim_ean')
                    ->label(__('erp.charging.sim_ean'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                // Logins only with their own permission.
                TextColumn::make('password')
                    ->label(__('erp.client_portal.password'))
                    ->state(fn (ChargingStation $record): ?string => $record->password)
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->placeholder('—')
                    ->visible($secrets),
                TextColumn::make('recovery_code')
                    ->label(__('erp.client_portal.recovery_code'))
                    ->state(fn (ChargingStation $record): ?string => $record->recovery_code)
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->placeholder('—')
                    ->visible($secrets),
                TextColumn::make('license_key')
                    ->label(__('erp.client_portal.license_key'))
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->placeholder('—')
                    ->visible($secrets)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('erp.charging.type'))->options(ChargingStationType::class),
                TernaryFilter::make('online')->label(__('erp.charging.online')),
                TernaryFilter::make('in_service')->label(__('erp.charging.in_service')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListClientChargingStations::route('/')];
    }
}
