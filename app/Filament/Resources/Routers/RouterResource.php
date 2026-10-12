<?php

namespace App\Filament\Resources\Routers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ChargingStations\ChargingStationResource;
use App\Filament\Resources\Routers\Pages\CreateRouter;
use App\Filament\Resources\Routers\Pages\EditRouter;
use App\Filament\Resources\Routers\Pages\ListRouters;
use App\Filament\Resources\Routers\Pages\ViewRouter;
use App\Models\Router;
use App\Models\WorkSite;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * 4G routers per COW site. Password and VPN file only with "charging.secrets".
 */
class RouterResource extends Resource
{
    protected static ?string $model = Router::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'routers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWifi;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Charging;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'serial_number';

    /**
     * VPN files: OpenVPN profiles, configuration files or a zip.
     */
    public const VPN_TYPES = ['text/plain', 'application/octet-stream', 'application/x-openvpn-profile', 'application/zip', 'application/x-zip-compressed'];

    public static function getModelLabel(): string
    {
        return __('erp.charging.router');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.charging.routers');
    }

    public static function canSeeSecrets(): bool
    {
        return Gate::allows('viewSecrets', Router::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('erp.charging.sections.router'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('work_site_id')->label(__('erp.charging.cow_code'))->options(fn (): array => ChargingStationResource::siteOptions())->searchable()->required(),
                    TextInput::make('type')->label(__('erp.charging.router_type'))->datalist(fn (): array => Router::query()->distinct()->orderBy('type')->limit(50)->pluck('type')->all())->required()->maxLength(100),
                    TextInput::make('serial_number')->label(__('erp.charging.serial_number'))->maxLength(100)->unique(ignoreRecord: true),
                    TextInput::make('sim_ean')->label(__('erp.charging.sim_ean'))->maxLength(30),
                    TextInput::make('sim_ssn')->label(__('erp.charging.sim_ssn'))->maxLength(30),
                    TextInput::make('username')->label(__('erp.charging.username'))->maxLength(100),
                    Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
                ]),
            Section::make(__('erp.charging.sections.secrets'))
                ->description(__('erp.charging.secrets_help'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (): bool => self::canSeeSecrets())
                ->schema([
                    TextInput::make('password')->label(__('erp.charging.password'))->password()->revealable()->maxLength(255),
                    FileUpload::make('vpn_file')
                        ->label(__('erp.charging.vpn_file'))
                        ->helperText(__('erp.charging.vpn_file_help'))
                        ->disk(Router::DISK)
                        ->directory(Router::vpnDirectory())
                        ->visibility('private')
                        ->acceptedFileTypes(self::VPN_TYPES)
                        ->maxSize(5120)
                        ->storeFileNamesIn('vpn_file_name')
                        ->downloadable(false)
                        ->previewable(false),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('erp.charging.sections.router'))
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('workSite.cow_code')->label(__('erp.charging.cow_code'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)
                        ->helperText(fn (Router $record): ?string => $record->workSite?->addressLine()),
                    TextEntry::make('type')->label(__('erp.charging.router_type')),
                    TextEntry::make('serial_number')->label(__('erp.charging.serial_number'))->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                    TextEntry::make('username')->label(__('erp.charging.username'))->copyable()->placeholder('—'),
                    TextEntry::make('sim_ean')->label(__('erp.charging.sim_ean'))->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                    TextEntry::make('sim_ssn')->label(__('erp.charging.sim_ssn'))->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                    TextEntry::make('notes')->label(__('erp.fields.notes'))->placeholder('—')->columnSpanFull(),
                ]),
            Section::make(__('erp.charging.sections.secrets'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (): bool => self::canSeeSecrets())
                ->schema([
                    TextEntry::make('secret_password')->label(__('erp.charging.password'))->state(fn (Router $record): ?string => $record->password)->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                    TextEntry::make('vpn_file_name')->label(__('erp.charging.vpn_file'))->placeholder(__('erp.charging.no_vpn_file'))
                        ->url(fn (Router $record): ?string => $record->vpn_file ? route('routers.vpn', $record) : null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('workSite'))
            ->defaultSort(fn (Builder $query) => $query->orderBy(WorkSite::query()->select('cow_code')->whereColumn('work_sites.id', 'routers.work_site_id')))
            ->searchPlaceholder(__('erp.charging.router_search'))
            ->columns([
                TextColumn::make('workSite.cow_code')->label(__('erp.charging.cow_code'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable()->description(fn (Router $record): ?string => $record->workSite?->city),
                TextColumn::make('type')->label(__('erp.charging.router_type'))->searchable(),
                TextColumn::make('serial_number')->label(__('erp.charging.serial_number'))->fontFamily(FontFamily::Mono)->searchable()->copyable()->placeholder('—'),
                TextColumn::make('sim_ean')->label(__('erp.charging.sim_ean'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('sim_ssn')->label(__('erp.charging.sim_ssn'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('username')->label(__('erp.charging.username'))->placeholder('—')->toggleable(),
                IconColumn::make('has_vpn')->label(__('erp.charging.vpn_file'))->state(fn (Router $record): bool => filled($record->vpn_file))->boolean()->visible(fn (): bool => self::canSeeSecrets()),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('erp.charging.router_type'))->options(fn (): array => Router::query()->distinct()->orderBy('type')->pluck('type', 'type')->all()),
                SelectFilter::make('work_site_id')->label(__('erp.charging.cow_code'))->options(fn (): array => ChargingStationResource::siteOptions())->searchable(),
            ])
            ->recordActions([
                Action::make('vpn')
                    ->label('VPN')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (Router $record): string => route('routers.vpn', $record))
                    ->visible(fn (Router $record): bool => filled($record->vpn_file) && self::canSeeSecrets()),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRouters::route('/'),
            'create' => CreateRouter::route('/create'),
            'view' => ViewRouter::route('/{record}'),
            'edit' => EditRouter::route('/{record}/edit'),
        ];
    }
}
