<?php

namespace App\Filament\Resources\ChargingStations;

use App\Enums\ChargingConnectivity;
use App\Enums\ChargingStationType;
use App\Enums\ChargingSystem;
use App\Enums\NavigationGroup;
use App\Filament\Resources\ChargingStations\Pages\CreateChargingStation;
use App\Filament\Resources\ChargingStations\Pages\EditChargingStation;
use App\Filament\Resources\ChargingStations\Pages\ListChargingStations;
use App\Filament\Resources\ChargingStations\Pages\ViewChargingStation;
use App\Models\ChargingStation;
use App\Models\Project;
use App\Models\WorkSite;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Charging stations of the 608 projects (the Excel "Overzicht Laadpalen"). Address and city come
 * from the COW site; password and recovery code only with "charging.secrets".
 */
class ChargingStationResource extends Resource
{
    protected static ?string $model = ChargingStation::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'charging-stations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Charging;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'ace_number';

    public static function getModelLabel(): string
    {
        return __('erp.charging.station');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.charging.stations');
    }

    public static function canSeeSecrets(): bool
    {
        return Gate::allows('viewSecrets', ChargingStation::class);
    }

    /**
     * COW sites as "02GAM · Evere".
     *
     * @return array<int, string>
     */
    public static function siteOptions(): array
    {
        return WorkSite::query()->orderBy('cow_code')->get()->mapWithKeys(fn (WorkSite $site): array => [$site->id => trim($site->cow_code.' · '.$site->city, ' ·')])->all();
    }

    /**
     * The 608 projects (category from the module, see Settings), for linking a station or router.
     *
     * @return array<int, string>
     */
    public static function projectOptions(): array
    {
        return Project::query()
            ->visibleTo(auth()->user())
            ->whereHas('category', fn (Builder $query) => $query->where('code', settings()->get('charging.project_category', '608')))
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' '.($project->subtitle() ? '— '.$project->subtitle() : ''))])
            ->all();
    }

    /**
     * Earlier values of a column, offered while typing (back offices, brands…).
     *
     * @return list<string>
     */
    public static function suggestions(string $column): array
    {
        return ChargingStation::query()->whereNotNull($column)->distinct()->orderBy($column)->limit(50)->pluck($column)->all();
    }

    public static function form(Schema $schema): Schema
    {
        $isCluster = fn (Get $get): bool => in_array($get('type'), [ChargingStationType::Cluster, ChargingStationType::Cluster->value], true);

        return $schema->components([
            Section::make(__('erp.charging.sections.location'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('project_id')
                        ->label(__('erp.charging.project'))
                        ->helperText(__('erp.charging.project_help'))
                        ->options(fn (): array => self::projectOptions())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            if ($site = Project::query()->find($state)?->work_site_id) {
                                $set('work_site_id', $site);
                            }
                        }),
                    Select::make('work_site_id')
                        ->label(__('erp.charging.cow_code'))
                        ->options(fn (): array => self::siteOptions())
                        ->searchable()
                        ->required()
                        ->live(),
                    Text::make(fn (Get $get): string => ($site = WorkSite::query()->find($get('work_site_id')))
                        ? __('erp.charging.address').': '.((new ChargingStation)->setRelation('workSite', $site)->address() ?? '—')
                        : __('erp.charging.address_help')),
                ]),
            Section::make(__('erp.charging.sections.station'))
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('ace_number')->label(__('erp.charging.ace_number'))->placeholder('ACE0682661')->maxLength(30)->unique(ignoreRecord: true),
                    ToggleButtons::make('type')->label(__('erp.charging.type'))->options(ChargingStationType::class)->default(ChargingStationType::Standalone->value)->inline()->required()->live(),
                    TextInput::make('cluster_number')->label(__('erp.charging.cluster_number'))->helperText(__('erp.charging.cluster_number_help'))->integer()->minValue(1)->maxValue(999)->visible($isCluster),
                    ToggleButtons::make('system')->label(__('erp.charging.system'))->options(ChargingSystem::class)->inline(),
                    TextInput::make('brand')->label(__('erp.charging.brand'))->datalist(fn (): array => self::suggestions('brand'))->maxLength(100),
                    TextInput::make('quantity')->label(__('erp.charging.quantity'))->integer()->minValue(0)->maxValue(999),
                    TextInput::make('installation_year')->label(__('erp.charging.installation_year'))->integer()->minValue(2000)->maxValue(2100)->default(now()->year),
                    TextInput::make('backoffice')->label(__('erp.charging.backoffice'))->datalist(fn (): array => self::suggestions('backoffice'))->maxLength(100),
                    Select::make('connectivity')->label(__('erp.charging.connectivity'))->options(ChargingConnectivity::class),
                    TextInput::make('license_key')->label(__('erp.charging.license_key'))->maxLength(100)->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                    TextInput::make('sim_ean')->label(__('erp.charging.sim_ean'))->maxLength(30),
                    TextInput::make('sim_ssn')->label(__('erp.charging.sim_ssn'))->maxLength(30),
                ]),
            Section::make(__('erp.charging.sections.status'))
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('online')->label(__('erp.charging.online'))->helperText(__('erp.charging.online_help'))->default(true),
                    Toggle::make('in_service')->label(__('erp.charging.in_service')),
                    TextInput::make('commissioned_by')->label(__('erp.charging.commissioned_by'))->maxLength(100),
                    DatePicker::make('onsite_warranty_end')->label(__('erp.charging.onsite_warranty_end')),
                    Toggle::make('fully_executed')->label(__('erp.charging.fully_executed')),
                    Toggle::make('plans_updated')->label(__('erp.charging.plans_updated')),
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
                    TextInput::make('recovery_code')->label(__('erp.charging.recovery_code'))->password()->revealable()->maxLength(255),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $yesNo = fn (string $name, string $label): IconEntry => IconEntry::make($name)->label(__($label))->boolean();

        return $schema->components([
            Section::make(__('erp.charging.sections.location'))
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('workSite.cow_code')->label(__('erp.charging.cow_code'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold),
                    TextEntry::make('address')->label(__('erp.charging.address'))->state(fn (ChargingStation $record): ?string => $record->address())->placeholder('—')->columnSpan(2),
                    TextEntry::make('workSite.city')->label(__('erp.charging.city'))->placeholder('—'),
                    TextEntry::make('project.reference')->label(__('erp.charging.project'))->placeholder('—'),
                ]),
            Section::make(__('erp.charging.sections.station'))
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('ace_number')->label(__('erp.charging.ace_number'))->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                    TextEntry::make('type')->label(__('erp.charging.type'))->badge(),
                    TextEntry::make('cluster_number')->label(__('erp.charging.cluster_number'))->placeholder('—'),
                    TextEntry::make('system')->label(__('erp.charging.system'))->placeholder('—'),
                    TextEntry::make('brand')->label(__('erp.charging.brand'))->placeholder('—'),
                    TextEntry::make('quantity')->label(__('erp.charging.quantity'))->placeholder('—'),
                    TextEntry::make('installation_year')->label(__('erp.charging.installation_year'))->placeholder('—'),
                    TextEntry::make('backoffice')->label(__('erp.charging.backoffice'))->placeholder('—'),
                    TextEntry::make('connectivity')->label(__('erp.charging.connectivity'))->placeholder('—'),
                    TextEntry::make('license_key')->label(__('erp.charging.license_key'))->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                    TextEntry::make('sim_ean')->label(__('erp.charging.sim_ean'))->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                    TextEntry::make('sim_ssn')->label(__('erp.charging.sim_ssn'))->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                ]),
            Section::make(__('erp.charging.sections.status'))
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('online')->label(__('erp.charging.status'))->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'ONLINE' : 'OFFLINE')
                        ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                    $yesNo('in_service', 'erp.charging.in_service'),
                    TextEntry::make('commissioned_by')->label(__('erp.charging.commissioned_by'))->placeholder('—'),
                    TextEntry::make('onsite_warranty_end')->label(__('erp.charging.onsite_warranty_end'))->date('d/m/Y')->placeholder('—'),
                    $yesNo('fully_executed', 'erp.charging.fully_executed'),
                    $yesNo('plans_updated', 'erp.charging.plans_updated'),
                    TextEntry::make('notes')->label(__('erp.fields.notes'))->placeholder('—')->columnSpanFull(),
                ]),
            Section::make(__('erp.charging.sections.secrets'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (): bool => self::canSeeSecrets())
                ->schema([
                    TextEntry::make('secret_password')->label(__('erp.charging.password'))->state(fn (ChargingStation $record): ?string => $record->password)->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                    TextEntry::make('secret_recovery_code')->label(__('erp.charging.recovery_code'))->state(fn (ChargingStation $record): ?string => $record->recovery_code)->fontFamily(FontFamily::Mono)->copyable()->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['workSite', 'project']))
            ->defaultSort(fn (Builder $query) => $query->orderBy(WorkSite::query()->select('cow_code')->whereColumn('work_sites.id', 'charging_stations.work_site_id'))->orderBy('cluster_number')->orderBy('ace_number'))
            ->searchPlaceholder(__('erp.charging.search'))
            ->columns([
                TextColumn::make('workSite.cow_code')->label(__('erp.charging.cow_code'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable()->description(fn (ChargingStation $record): ?string => $record->workSite?->city),
                TextColumn::make('ace_number')->label(__('erp.charging.ace_number'))->fontFamily(FontFamily::Mono)->searchable()->copyable()->placeholder('—'),
                TextColumn::make('type')->label(__('erp.charging.type'))->badge()->color('gray')->description(fn (ChargingStation $record): ?string => $record->cluster_number ? __('erp.charging.cluster_short', ['number' => $record->cluster_number]) : null),
                TextColumn::make('connectivity')->label(__('erp.charging.connectivity'))->placeholder('—')->toggleable(),
                ToggleColumn::make('online')
                    ->label(__('erp.charging.online'))
                    ->disabled(fn (ChargingStation $record): bool => ! Gate::allows('update', $record)),
                TextColumn::make('backoffice')->label(__('erp.charging.backoffice'))->placeholder('—')->toggleable(),
                TextColumn::make('brand')->label(__('erp.charging.brand'))->placeholder('—')->description(fn (ChargingStation $record): ?string => $record->system?->getLabel())->toggleable(),
                TextColumn::make('installation_year')->label(__('erp.charging.installation_year'))->sortable()->placeholder('—')->toggleable(),
                IconColumn::make('in_service')->label(__('erp.charging.in_service'))->boolean(),
                TextColumn::make('onsite_warranty_end')->label(__('erp.charging.onsite_warranty_end'))->date('d/m/Y')->sortable()->placeholder('—')->toggleable(),
                IconColumn::make('plans_updated')->label(__('erp.charging.plans_updated'))->boolean()->toggleable(),
                TextColumn::make('license_key')->label(__('erp.charging.license_key'))->fontFamily(FontFamily::Mono)->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sim_ean')->label(__('erp.charging.sim_ean'))->fontFamily(FontFamily::Mono)->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('online')->label(__('erp.charging.status'))->trueLabel('ONLINE')->falseLabel('OFFLINE'),
                SelectFilter::make('type')->label(__('erp.charging.type'))->options(ChargingStationType::class),
                SelectFilter::make('connectivity')->label(__('erp.charging.connectivity'))->options(ChargingConnectivity::class),
                SelectFilter::make('backoffice')->label(__('erp.charging.backoffice'))->options(fn (): array => array_combine(self::suggestions('backoffice'), self::suggestions('backoffice')) ?: []),
                SelectFilter::make('brand')->label(__('erp.charging.brand'))->options(fn (): array => array_combine(self::suggestions('brand'), self::suggestions('brand')) ?: []),
                TernaryFilter::make('in_service')->label(__('erp.charging.in_service')),
                SelectFilter::make('work_site_id')->label(__('erp.charging.cow_code'))->options(fn (): array => self::siteOptions())->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChargingStations::route('/'),
            'create' => CreateChargingStation::route('/create'),
            'view' => ViewChargingStation::route('/{record}'),
            'edit' => EditChargingStation::route('/{record}/edit'),
        ];
    }
}
