<?php

namespace App\Filament\Client\Resources\Routers;

use App\Filament\Client\ClientResource;
use App\Models\Router;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The 4G routers per COW site. The login (username and password) and the VPN configuration are only
 * shown with the "router secrets" permission.
 */
class ClientRouterResource extends ClientResource
{
    protected static ?string $model = Router::class;

    protected static ?string $slug = 'routers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWifi;

    protected static ?int $navigationSort = 4;

    public static function permission(): string
    {
        return ClientPortal::ROUTERS;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->charging();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.client_portal.routers');
    }

    public static function getModelLabel(): string
    {
        return __('erp.client_portal.router');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.client_portal.routers');
    }

    public static function getEloquentQuery(): Builder
    {
        // Only routers at a COW site.
        $query = Router::query()->with('workSite')->whereNotNull('routers.work_site_id');

        return ClientPortal::user()?->restrictToOwnSites($query) ?? $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        $secrets = fn (): bool => ClientPortal::may(ClientPortal::ROUTER_SECRETS);

        return $table
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('erp.client_portal.no_routers'))
            ->columns([
                TextColumn::make('workSite.cow_code')
                    ->label(__('erp.client_portal.cow_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (Router $record): ?string => $record->workSite?->city)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label(__('erp.charging.router_type'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('serial_number')
                    ->label(__('erp.charging.serial_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('sim_ean')
                    ->label(__('erp.charging.sim_ean'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('sim_ssn')
                    ->label(__('erp.charging.sim_ssn'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->toggleable(),
                // Login and VPN only with their own permission.
                TextColumn::make('username')
                    ->label(__('erp.charging.username'))
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->placeholder('—')
                    ->visible($secrets),
                TextColumn::make('password')
                    ->label(__('erp.client_portal.password'))
                    ->state(fn (Router $record): ?string => $record->password)
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->placeholder('—')
                    ->visible($secrets),
            ])
            ->recordActions([
                Action::make('vpn')
                    ->label(__('erp.client_portal.vpn'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (Router $record): bool => $secrets() && filled($record->vpn_file))
                    ->url(fn (Router $record): string => route('client.routers.vpn', $record)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListClientRouters::route('/')];
    }
}
