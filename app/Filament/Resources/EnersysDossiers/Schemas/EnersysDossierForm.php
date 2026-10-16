<?php

namespace App\Filament\Resources\EnersysDossiers\Schemas;

use App\Enums\BatteryDeliveryMode;
use App\Enums\BatteryMounting;
use App\Filament\Resources\BatteryDossiers\Schemas\BatteryDossierForm;
use App\Models\EnersysDossier;
use App\Models\Project;
use App\Support\BatteryInspection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The Enersys dossier form, one tab per sheet of the Excel "12V enersys": Input, Leveringsnota,
 * Meetrapport, Visueel keuringsverslag, Vervoersdocument and Bestelling PI, plus the photos. No
 * single-line diagram and no floor plan. Signatures, locking and finishing work like the BAE dossiers.
 */
class EnersysDossierForm extends BatteryDossierForm
{
    protected static string $model = EnersysDossier::class;

    protected static function inspectionSections(): array
    {
        return BatteryInspection::enersysSections();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make(__('erp.batteries.tabs.input'))->icon(Heroicon::OutlinedPencilSquare)
                        ->visible(fn (): bool => Gate::allows('viewInput', EnersysDossier::class))
                        ->schema(self::enersysInputTab()),
                    Tab::make(__('erp.batteries.tabs.overview'))->icon(Heroicon::OutlinedInformationCircle)->visibleOn('edit')
                        ->visible(fn (): bool => ! Gate::allows('viewInput', EnersysDossier::class))
                        ->schema(static::overviewTab()),
                    Tab::make(__('erp.batteries.tabs.delivery'))->icon(Heroicon::OutlinedTruck)->visibleOn('edit')->schema(self::enersysDeliveryTab()),
                    Tab::make(__('erp.batteries.tabs.measurement'))->icon(Heroicon::OutlinedBolt)->visibleOn('edit')->schema(self::enersysMeasurementTab()),
                    Tab::make(__('erp.batteries.tabs.inspection'))->icon(Heroicon::OutlinedClipboardDocumentCheck)->visibleOn('edit')->schema(static::inspectionTab()),
                    Tab::make(__('erp.batteries.tabs.shipping'))->icon(Heroicon::OutlinedArchiveBox)->visibleOn('edit')->schema(static::shippingTab()),
                    Tab::make(__('erp.enersys.tabs.order'))->icon(Heroicon::OutlinedCurrencyEuro)->visibleOn('edit')
                        ->visible(fn (?EnersysDossier $record): bool => $record !== null && Gate::allows('viewPrices', $record))
                        ->schema(self::orderTab()),
                    Tab::make(__('erp.batteries.tabs.photos'))->icon(Heroicon::OutlinedCamera)->visibleOn('edit')
                        ->badge(fn (?EnersysDossier $record): ?string => count((array) $record?->photos) ?: null)
                        ->schema(static::photosTab()),
                ]),
        ]);
    }

    /**
     * "BEREKENING INPUT": old (WEG) and new (NIEUW) batteries, the dossier and the battery info.
     *
     * @return array<Component>
     */
    protected static function enersysInputTab(): array
    {
        return [
            Section::make(__('erp.batteries.sections.dossier'))
                ->columns(4)
                ->schema([
                    Select::make('project_id')
                        ->label(__('erp.batteries.project'))
                        ->helperText(__('erp.batteries.project_help', ['category' => settings()->get(EnersysDossier::CATEGORY_SETTING)]))
                        ->options(fn (?EnersysDossier $record): array => static::projectOptions($record))
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->columnSpan(2),
                    TextInput::make('po_number')
                        ->label(__('erp.batteries.po_number'))
                        ->helperText(__('erp.enersys.po_help'))
                        ->datalist(fn (Get $get): array => Project::query()->find($get('project_id'))?->poNumbers->pluck('number')->all() ?? [])
                        ->maxLength(20),
                    TextInput::make('contact_person')
                        ->label(__('erp.batteries.contact_person'))
                        ->datalist(fn (): array => array_values((array) settings()->get('batteries.contacts', [])))
                        ->maxLength(255),
                    DatePicker::make('order_date')->label(__('erp.batteries.order_date')),
                    DatePicker::make('execution_date')->label(__('erp.batteries.execution_date')),
                    TextInput::make('truck_plate')->label(__('erp.batteries.truck_plate'))->placeholder('Camionette')->maxLength(50),
                    Select::make('waste_type')
                        ->label(__('erp.batteries.waste_type'))
                        ->options(fn (): array => collect((array) settings()->get('batteries.waste_types', []))->mapWithKeys(fn (string $type): array => [$type => $type])->all()),
                ]),
            Grid::make(['default' => 1, 'lg' => 2])
                ->schema([
                    Section::make(__('erp.enersys.old'))
                        ->columns(2)
                        ->schema([
                            TextInput::make('old_capacity_ah')->label(__('erp.enersys.capacity'))->integer()->minValue(0)->suffix('Ah')->default(0),
                            TextInput::make('old_count')->label(__('erp.enersys.count'))->integer()->minValue(0)->maxValue(EnersysDossier::MAX_BATTERIES)->default(1),
                            TextInput::make('old_room')->label(__('erp.batteries.room'))->maxLength(50),
                            Toggle::make('removal')->label(__('erp.enersys.removal'))->helperText(__('erp.batteries.removal_help'))->default(true),
                        ]),
                    Section::make(__('erp.enersys.new'))
                        ->columns(2)
                        ->schema([
                            TextInput::make('new_capacity_label')->label(__('erp.enersys.capacity'))->helperText(__('erp.enersys.capacity_help'))->placeholder('155/100')->maxLength(30),
                            TextInput::make('new_count')
                                ->label(__('erp.enersys.count'))
                                ->helperText(__('erp.batteries.new_count_help'))
                                ->integer()
                                ->minValue(1)
                                ->maxValue(EnersysDossier::MAX_BATTERIES)
                                ->default(1)
                                ->required()
                                ->live(onBlur: true),
                            Select::make('delivery_mode')->label(__('erp.enersys.supply'))->options(BatteryDeliveryMode::class)->default(BatteryDeliveryMode::Place->value)->required(),
                            TextInput::make('supplier_location')->label(__('erp.enersys.where'))->placeholder('Power Installation NV')->maxLength(255),
                            TextInput::make('new_room')->label(__('erp.batteries.room'))->maxLength(50),
                        ]),
                ]),
            Section::make(__('erp.enersys.info'))
                ->columns(4)
                ->schema([
                    TextInput::make('brand')->label(__('erp.batteries.brand'))->default(fn (): ?string => settings()->get('batteries.enersys_brand'))->maxLength(50),
                    Select::make('battery_type')
                        ->label(__('erp.enersys.type'))
                        ->options(fn (): array => collect(EnersysDossier::types())->mapWithKeys(fn (int $capacity, string $type): array => [$type => $type])->all())
                        ->live()
                        ->afterStateUpdated(fn (?string $state, Set $set) => $set('new_capacity_ah', EnersysDossier::types()[$state] ?? null)),
                    TextInput::make('new_capacity_ah')->label(__('erp.enersys.type_capacity'))->integer()->minValue(0)->suffix('Ah'),
                    Select::make('mounting')->label(__('erp.batteries.mounting'))->options(BatteryMounting::class)->default(BatteryMounting::Cabinet->value),
                    TextInput::make('cells_per_battery')
                        ->label(__('erp.enersys.blocks_per_battery'))
                        ->helperText(__('erp.enersys.blocks_per_battery_help'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(24)
                        ->default(4)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('production_date')->label(__('erp.batteries.production_date'))->maxLength(30),
                    TextInput::make('first_id_number')->label(__('erp.batteries.first_id_number'))->helperText(__('erp.batteries.first_id_number_help'))->maxLength(30),
                ]),
            Section::make(__('erp.batteries.sections.ids'))
                ->columns(4)
                ->schema(fn (Get $get): array => array_map(
                    fn (int $position): TextInput => TextInput::make("strings.{$position}.id_number")->label('ID Batt '.$position)->maxLength(30),
                    static::positions($get),
                )),
        ];
    }

    /**
     * The quantities of the delivery note ("Leveringsnota").
     *
     * @return array<Component>
     */
    protected static function enersysDeliveryTab(): array
    {
        $number = fn (string $name, string $suffix): TextInput => TextInput::make($name)->label(__("erp.batteries.fields.{$name}"))->numeric()->minValue(0)->step('any')->default(0)->suffix($suffix);

        return [
            static::lockable('delivery', [
                Section::make(__('erp.batteries.sections.delivery'))
                    ->description(__('erp.enersys.delivery_help'))
                    ->columns(4)
                    ->schema([
                        $number('vertical_install_m', 'm'),
                        $number('vertical_removal_m', 'm'),
                        $number('man_hours', 'u'),
                        TextInput::make('leak_trays')->label(__('erp.enersys.leak_trays'))->integer()->minValue(0)->default(0)->suffix('st'),
                        $number('powerflex_length_m', 'm'),
                        TextInput::make('powerflex_section')->label(__('erp.batteries.fields.powerflex_section'))->suffix('mm²')->maxLength(20),
                        $number('lug_count', 'st'),
                        TextInput::make('lug_section')->label(__('erp.batteries.fields.lug_section'))->suffix('mm²')->maxLength(20),
                        $number('fuse_count', 'st'),
                        TextInput::make('fuse_amperage')->label(__('erp.batteries.fields.fuse_amperage'))->suffix('A')->maxLength(20),
                        TextInput::make('fuse_type')->label(__('erp.batteries.fields.fuse_type'))->placeholder('gG')->maxLength(30),
                    ]),
            ]),
            static::signatureSection('delivery'),
            static::download('delivery'),
        ];
    }

    /**
     * The measurement report: per battery the float voltage, then every 12 V block disconnected
     * ("Afgeschakeld") and floating ("In vlotting"). Rows follow the number of batteries and blocks.
     *
     * @return array<Component>
     */
    protected static function enersysMeasurementTab(): array
    {
        $volt = fn (string $path): TextInput => TextInput::make($path)->hiddenLabel()->numeric()->step('any')->suffix('V')->live(onBlur: true);
        $sum = fn (Get $get, string $path): string => ($values = array_filter((array) $get($path), 'is_numeric')) ? number_format(array_sum($values), 2, ',', '').' V' : '—';

        return [
            static::lockable('measurement', [
                Section::make()
                    ->columns(3)
                    ->schema([
                        DatePicker::make('measured_on')->label(__('erp.batteries.measured_on'))->helperText(__('erp.batteries.measured_on_help')),
                        TextInput::make('average_temperature')->label(__('erp.batteries.average_temperature'))->numeric()->step('any')->suffix('°C'),
                        Text::make(fn (Get $get): string => __('erp.enersys.measurement_help', ['blocks' => (int) $get('cells_per_battery'), 'count' => (int) $get('new_count')])),
                    ]),
                Grid::make(['default' => 1, 'xl' => 2])
                    ->schema(fn (Get $get): array => array_map(fn (int $position): Section => Section::make('Batt '.$position)
                        ->description(fn (Get $get): string => __('erp.enersys.totals', [
                            'off' => $sum($get, "strings.{$position}.cell_voltages"),
                            'floating' => $sum($get, "strings.{$position}.floating_voltages"),
                        ]))
                        ->compact()
                        ->columns(3)
                        ->schema([
                            TextInput::make("strings.{$position}.float_voltage")->label(__('erp.enersys.float_voltage'))->numeric()->step('any')->suffix('V')->columnSpanFull(),
                            Text::make(__('erp.enersys.block')),
                            Text::make(__('erp.enersys.disconnected')),
                            Text::make(__('erp.enersys.floating')),
                            ...array_merge(...array_map(fn (int $block): array => [
                                Text::make((string) $block),
                                $volt("strings.{$position}.cell_voltages.".($block - 1)),
                                $volt("strings.{$position}.floating_voltages.".($block - 1)),
                            ], range(1, max(1, min(24, (int) $get('cells_per_battery')))))),
                        ]), static::positions($get))),
            ]),
            static::signatureSection('measurement'),
            static::download('measurement'),
        ];
    }

    /**
     * "Bestelling PI": the amounts of the order, filled in from Settings > Batteries and changeable.
     *
     * @return array<Component>
     */
    protected static function orderTab(): array
    {
        $amount = fn (string $key, string $label): TextInput => TextInput::make("order_amounts.{$key}")
            ->label($label)
            ->numeric()
            ->step('any')
            ->prefix('€')
            ->placeholder(fn (?EnersysDossier $record): ?string => $record ? number_format($record->defaultOrderAmounts()[$key], 2, ',', '') : null)
            ->helperText(__('erp.enersys.order_default'));

        return [
            Section::make(__('erp.enersys.tabs.order'))
                ->description(fn (?EnersysDossier $record): string => $record?->description() ?? '')
                ->columns(2)
                ->schema([
                    $amount('pickup', __('erp.enersys.order.pickup')),
                    $amount('removal', __('erp.enersys.order.removal')),
                    $amount('placement', __('erp.enersys.order.placement')),
                    $amount('disposal', __('erp.enersys.order.disposal')),
                    TextInput::make('order_amounts.other_label')->label(__('erp.enersys.order.other_label'))->placeholder('Andere: lekbakken')->maxLength(100),
                    $amount('other', __('erp.enersys.order.other')),
                    $amount('file_costs', __('erp.enersys.order.file_costs')),
                    Text::make(fn (?EnersysDossier $record): string => $record ? __('erp.enersys.order.total', ['amount' => EnersysDossier::money($record->orderTotal())]) : '')
                        ->columnSpanFull(),
                ]),
            static::download('order'),
        ];
    }
}
