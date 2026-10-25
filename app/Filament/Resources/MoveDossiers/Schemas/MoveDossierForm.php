<?php

namespace App\Filament\Resources\MoveDossiers\Schemas;

use App\Enums\BatteryMounting;
use App\Enums\BatteryTechnology;
use App\Filament\Resources\BatteryDossiers\Schemas\BatteryDossierForm;
use App\Models\BatteryCable;
use App\Models\BatteryModel;
use App\Models\MoveDossier;
use App\Models\Project;
use App\Models\WorkSite;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The battery move form (605): the move (source and destination COW codes, decommissioning), the
 * batteries that are moved and the old ones at the destination, then the documents of the 604 dossier
 * (delivery note, measurement report, visual inspection, floor plan, single-line diagram) and two
 * shipping notes: the move, and the old batteries when they are taken away.
 */
class MoveDossierForm extends BatteryDossierForm
{
    protected static string $model = MoveDossier::class;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make(__('erp.batteries.tabs.input'))->icon(Heroicon::OutlinedPencilSquare)
                        ->visible(fn (): bool => Gate::allows('viewInput', MoveDossier::class))
                        ->schema(self::moveInputTab()),
                    Tab::make(__('erp.batteries.tabs.overview'))->icon(Heroicon::OutlinedInformationCircle)->visibleOn('edit')
                        ->visible(fn (): bool => ! Gate::allows('viewInput', MoveDossier::class))
                        ->schema(static::overviewTab()),
                    Tab::make(__('erp.batteries.tabs.delivery'))->icon(Heroicon::OutlinedTruck)->visibleOn('edit')->schema(self::moveDeliveryTab()),
                    Tab::make(__('erp.batteries.tabs.measurement'))->icon(Heroicon::OutlinedBolt)->visibleOn('edit')->schema(static::measurementTab()),
                    Tab::make(__('erp.batteries.tabs.inspection'))->icon(Heroicon::OutlinedClipboardDocumentCheck)->visibleOn('edit')->schema(static::inspectionTab()),
                    Tab::make(__('erp.batteries.tabs.floor_plan'))->icon(Heroicon::OutlinedMap)->visibleOn('edit')->schema(static::floorPlanTab()),
                    Tab::make(__('erp.batteries.tabs.single_line'))->icon(Heroicon::OutlinedShare)->visibleOn('edit')->schema(static::singleLineTab()),
                    Tab::make(__('erp.batteries.tabs.shipping'))->icon(Heroicon::OutlinedArchiveBox)->visibleOn('edit')->schema(self::moveShippingTab()),
                    Tab::make(__('erp.batteries.tabs.photos'))->icon(Heroicon::OutlinedCamera)->visibleOn('edit')
                        ->badge(fn (?MoveDossier $record): ?string => count((array) $record?->photos) ?: null)
                        ->schema(static::photosTab()),
                ]),
        ]);
    }

    /**
     * A COW site to choose: "02GAM — Gent".
     */
    public static function siteSelect(string $name): Select
    {
        return Select::make($name)
            ->getSearchResultsUsing(fn (string $search): array => WorkSite::query()
                ->where(fn ($query) => $query->where('cow_code', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                ->orderBy('cow_code')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (WorkSite $site): array => [$site->id => trim($site->cow_code.' — '.$site->city, ' —')])
                ->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => ($site = WorkSite::query()->find($value)) ? trim($site->cow_code.' — '.$site->city, ' —') : null)
            ->searchable();
    }

    /**
     * @return array<Component>
     */
    protected static function moveInputTab(): array
    {
        return [
            Section::make(__('erp.batteries.sections.dossier'))
                ->columns(4)
                ->schema([
                    Select::make('project_id')
                        ->label(__('erp.batteries.project'))
                        ->helperText(__('erp.batteries.project_help', ['category' => settings()->get(MoveDossier::CATEGORY_SETTING)]))
                        ->options(fn (?MoveDossier $record): array => self::projectOptions($record))
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->columnSpan(2),
                    TextInput::make('po_number')
                        ->label(__('erp.batteries.po_number'))
                        ->datalist(fn (Get $get): array => Project::query()->find($get('project_id'))?->poNumbers->pluck('number')->all() ?? [])
                        ->maxLength(20),
                    TextInput::make('contact_person')
                        ->label(__('erp.batteries.contact_person'))
                        ->datalist(fn (): array => array_values((array) settings()->get('batteries.contacts', [])))
                        ->maxLength(255),
                    DatePicker::make('order_date')->label(__('erp.batteries.order_date')),
                    DatePicker::make('execution_date')->label(__('erp.batteries.execution_date')),
                    TextInput::make('truck_plate')->label(__('erp.batteries.truck_plate'))->maxLength(50),
                    static::documentLocaleField(),
                ]),
            Section::make(__('erp.moves.sections.move'))
                ->description(__('erp.moves.sections.move_help'))
                ->columns(2)
                ->schema([
                    self::siteSelect('move_from_work_site_id')->label(__('erp.moves.from'))->required()->different('move_to_work_site_id'),
                    self::siteSelect('move_to_work_site_id')
                        ->label(__('erp.moves.to'))
                        ->helperText(__('erp.moves.to_help'))
                        ->placeholder(fn (Get $get): ?string => Project::query()->with('workSite')->find($get('project_id'))?->workSite?->cow_code),
                    TextInput::make('old_room')->label(__('erp.moves.from_room'))->maxLength(50),
                    TextInput::make('new_room')->label(__('erp.moves.to_room'))->maxLength(50),
                    Toggle::make('decommission_source')
                        ->label(__('erp.moves.decommission'))
                        ->helperText(__('erp.moves.decommission_help'))
                        ->columnSpanFull(),
                ]),
            Section::make(__('erp.moves.sections.batteries'))
                ->columns(4)
                ->schema([
                    TextInput::make('brand')->label(__('erp.batteries.brand'))->maxLength(50),
                    Select::make('battery_model_id')
                        ->label(__('erp.batteries.model'))
                        ->options(fn (): array => BatteryModel::query()->where('is_active', true)->orderBy('technology')->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    Select::make('new_technology')->label(__('erp.batteries.technology'))->options(BatteryTechnology::class)->live(),
                    TextInput::make('new_capacity_ah')->label(__('erp.batteries.capacity'))->integer()->minValue(0)->suffix('Ah'),
                    TextInput::make('new_count')
                        ->label(__('erp.moves.count'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(MoveDossier::MAX_BATTERIES)
                        ->default(1)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('cells_per_battery')->label(__('erp.batteries.cells_per_battery'))->integer()->minValue(1)->maxValue(60)->default(24)->required()->live(onBlur: true),
                    TextInput::make('volts_per_cell')->label(__('erp.batteries.volts_per_cell'))->numeric()->step('any')->default(2)->suffix('V'),
                    Select::make('mounting')->label(__('erp.batteries.mounting'))->options(BatteryMounting::class),
                    TextInput::make('production_date')->label(__('erp.batteries.production_date'))->maxLength(30),
                    TextInput::make('first_id_number')->label(__('erp.batteries.first_id_number'))->helperText(__('erp.batteries.first_id_number_help'))->maxLength(30),
                ]),
            Section::make(__('erp.moves.sections.old'))
                ->description(__('erp.moves.sections.old_help'))
                ->columns(4)
                ->schema([
                    Toggle::make('removal')->label(__('erp.moves.removal'))->default(false)->live()->columnSpanFull(),
                    TextInput::make('old_count')->label(__('erp.batteries.count'))->integer()->minValue(0)->maxValue(MoveDossier::MAX_BATTERIES)->visible(fn (Get $get): bool => (bool) $get('removal')),
                    TextInput::make('old_capacity_ah')->label(__('erp.batteries.capacity'))->integer()->minValue(0)->suffix('Ah')->visible(fn (Get $get): bool => (bool) $get('removal')),
                    Select::make('old_technology')->label(__('erp.batteries.technology'))->options(BatteryTechnology::class)->visible(fn (Get $get): bool => (bool) $get('removal')),
                    Select::make('waste_type')
                        ->label(__('erp.batteries.waste_type'))
                        ->options(fn (): array => collect((array) settings()->get('batteries.waste_types', []))->mapWithKeys(fn (string $type): array => [$type => $type])->all())
                        ->visible(fn (Get $get): bool => (bool) $get('removal')),
                ]),
            Section::make(__('erp.batteries.sections.ids'))
                ->visibleOn('edit')
                ->columns(['default' => 2, 'md' => 4])
                ->schema(fn (Get $get): array => array_map(
                    fn (int $position): TextInput => TextInput::make("strings.{$position}.id_number")->label('ID Batt '.$position)->maxLength(30),
                    self::positions($get),
                )),
        ];
    }

    /**
     * As the 604 delivery note, with the vertical transport at the pickup and without prices.
     *
     * @return array<Component>
     */
    protected static function moveDeliveryTab(): array
    {
        $number = fn (string $name, string $suffix): TextInput => TextInput::make($name)->label(__("erp.batteries.fields.{$name}"))->numeric()->minValue(0)->step('any')->default(0)->suffix($suffix);

        return [
            self::lockable('delivery', [
                Section::make(__('erp.batteries.sections.delivery'))
                    ->description(__('erp.batteries.sections.delivery_help'))
                    ->columns(4)
                    ->schema([
                        $number('vertical_pickup_m', 'm'),
                        $number('vertical_install_m', 'm'),
                        $number('vertical_removal_m', 'm')->visible(fn (?MoveDossier $record): bool => (bool) $record?->removal),
                        $number('ppe_sets', 'st'),
                        $number('maintenance_sets', 'st'),
                        $number('load_plates', 'st'),
                        $number('man_hours', 'u'),
                        $number('picto_plates', 'st'),
                        $number('doc_holders', 'st'),
                    ]),
                Section::make(__('erp.batteries.sections.cabling'))
                    ->columns(4)
                    ->schema([
                        $number('powerflex_length_m', 'm'),
                        TextInput::make('powerflex_section')->label(__('erp.batteries.fields.powerflex_section'))->suffix('mm²')->maxLength(20),
                        Select::make('battery_cable_id')
                            ->label(__('erp.batteries.fields.battery_cable_id'))
                            ->options(fn (): array => BatteryCable::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                        $number('frotek_count', 'st'),
                        $number('lug_count', 'st'),
                        TextInput::make('lug_section')->label(__('erp.batteries.fields.lug_section'))->suffix('mm²')->maxLength(20),
                        $number('tray_length_m', 'm'),
                        TextInput::make('tray_type')->label(__('erp.batteries.fields.tray_type'))->maxLength(50),
                        $number('fuse_count', 'st'),
                        TextInput::make('fuse_amperage')->label(__('erp.batteries.fields.fuse_amperage'))->suffix('A')->maxLength(20),
                        TextInput::make('fuse_type')->label(__('erp.batteries.fields.fuse_type'))->placeholder('gG')->maxLength(30),
                    ]),
            ]),
            self::signatureSection('delivery'),
            self::download('delivery'),
        ];
    }

    /**
     * Two shipping notes: the move, and the old batteries when they are taken away at the destination.
     *
     * @return array<Component>
     */
    protected static function moveShippingTab(): array
    {
        return [
            Section::make(__('erp.moves.sections.shipping_move'))
                ->description(__('erp.moves.sections.shipping_move_help'))
                ->columns(4)
                ->schema([
                    TextInput::make('move_packages')->label(__('erp.batteries.fields.packages'))->integer()->minValue(0)->default(0),
                    TextInput::make('move_weight_kg')->label(__('erp.batteries.fields.weight_kg'))->numeric()->minValue(0)->step('any')->suffix('kg'),
                ]),
            self::download('shipping_move'),
            Grid::make(1)
                ->visible(fn (?MoveDossier $record): bool => (bool) $record?->removal)
                ->schema(static::shippingTab()),
        ];
    }
}
