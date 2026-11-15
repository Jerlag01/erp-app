<?php

namespace App\Filament\Resources\CableCalculations;

use App\Enums\NavigationGroup;
use App\Filament\Resources\CableCalculations\Pages\CreateCableCalculation;
use App\Filament\Resources\CableCalculations\Pages\EditCableCalculation;
use App\Filament\Resources\CableCalculations\Pages\ListCableCalculations;
use App\Models\CableCalculation;
use App\Models\CableCalculationCircuit;
use App\Models\Project;
use App\Support\CableCalculator;
use App\Support\PdfAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * "Kabelberekening": AC or DC is chosen first. AC gives the cable section as an indication, like
 * Schneider's cable calculator, after IEC 60364-5-52; DC follows the Proximus 48 V calculation note
 * (see CableCalculator). The result follows the input live; saved calculations can be
 * linked to a project and printed as PDF on the letterhead of the battery documents.
 */
class CableCalculationResource extends Resource
{
    protected static ?string $model = CableCalculation::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'cable-calculations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::CableCalculator;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('erp.cable_calculator.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.cable_calculator.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.cable_calculator.title');
    }

    /**
     * The options of a choice, labelled from the language files: "erp.cable_calculator.{group}.{value}".
     *
     * @param  list<string>  $values
     * @return array<string, string>
     */
    public static function options(string $group, array $values): array
    {
        return collect($values)->mapWithKeys(fn (string $value): array => [$value => __("erp.cable_calculator.{$group}.{$value}")])->all();
    }

    /**
     * What one circuit of the form holds now, for its live result.
     *
     * @return array<string, mixed>
     */
    public static function inputsFrom(Get $get): array
    {
        return collect(CableCalculationCircuit::INPUTS)->mapWithKeys(fn (string $field): array => [$field => $get($field)])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('erp.cable_calculator.sections.general'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('erp.cable_calculator.fields.name'))
                            ->placeholder(__('erp.cable_calculator.fields.name_placeholder'))
                            ->required()
                            ->maxLength(255),
                        Select::make('project_id')
                            ->label(__('erp.cable_calculator.fields.project'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Project::query()
                                ->visibleTo(auth()->user())
                                ->where(fn (Builder $query) => $query->where('reference', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('cow_code', 'like', '%'.addcslashes($search, '%_\\').'%'))
                                ->orderByDesc('id')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' — '.$project->subtitle(), ' —')])
                                ->all())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => Project::query()->find($value)?->reference),
                        Textarea::make('notes')->label(__('erp.cable_calculator.fields.notes'))->rows(2)->maxLength(5000)->columnSpanFull(),
                    ]),
                // Several circuits at once, AC or DC each, from a start point to an end point.
                Repeater::make('circuits')
                    ->label(__('erp.cable_calculator.circuits'))
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->addActionLabel(__('erp.cable_calculator.add_circuit'))
                    ->minItems(1)
                    ->defaultItems(1)
                    ->collapsible()
                    ->cloneable()
                    ->reorderableWithButtons()
                    ->itemLabel(fn (array $state): string => self::circuitLabel($state))
                    ->columns(['default' => 1, 'xl' => 3])
                    ->schema(self::circuitSchema()),
            ]);
    }

    /**
     * "1 · Krachtbord → Batterij 1 · DC · 95 mm² per pool" for a circuit of the form.
     *
     * @param  array<string, mixed>  $state
     */
    public static function circuitLabel(array $state): string
    {
        if (blank($state['system'] ?? null) && blank($state['kind'] ?? null)) {
            return CableCalculationCircuit::routeLabel($state['from_point'] ?? null, $state['to_point'] ?? null);
        }

        $input = collect(CableCalculationCircuit::INPUTS)->mapWithKeys(fn (string $field): array => [$field => $state[$field] ?? null])->all();
        $cable = CableCalculationCircuit::cableLabelFor(CableCalculator::calculate($input), $input);

        return implode(' · ', array_filter([
            CableCalculationCircuit::routeLabel($state['from_point'] ?? null, $state['to_point'] ?? null),
            ($state['system'] ?? null) === 'dc' ? 'DC' : 'AC',
            $cable,
        ]));
    }

    /**
     * The fields of one circuit: start and end point, AC or DC, the fields of that kind, and its result.
     *
     * @return array<int, mixed>
     */
    public static function circuitSchema(): array
    {
        $number = fn (string $name, ?string $suffix = null): TextInput => TextInput::make($name)
            ->label(__("erp.cable_calculator.fields.{$name}"))
            ->numeric()
            ->step('any')
            ->suffix($suffix)
            ->live(onBlur: true);

        return [
            Grid::make(1)
                ->columnSpan(['xl' => 2])
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('from_point')
                            ->label(__('erp.cable_calculator.fields.from_point'))
                            ->placeholder(__('erp.cable_calculator.fields.from_point_placeholder'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),
                        TextInput::make('to_point')
                            ->label(__('erp.cable_calculator.fields.to_point'))
                            ->placeholder(__('erp.cable_calculator.fields.to_point_placeholder'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),
                        // AC or DC first: each has its own fields and formula.
                        ToggleButtons::make('kind')
                            ->label(__('erp.cable_calculator.fields.kind'))
                            ->options(self::options('kinds_short', ['ac', 'dc']))
                            ->icons(['ac' => Heroicon::OutlinedBolt, 'dc' => Heroicon::OutlinedBattery100])
                            ->inline()
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (ToggleButtons $component, ?CableCalculationCircuit $record) => $component->state($record === null ? null : ($record->isDc() ? 'dc' : 'ac')))
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $system = $state === 'dc' ? 'dc' : 'ac3n';
                                $set('system', $system);
                                $set('voltage', CableCalculator::VOLTAGES[$system]);
                            }),
                    ]),
                    // What AC and DC share (each field once, so a hidden part never drops its value).
                    Section::make(__('erp.cable_calculator.sections.circuit'))
                        ->compact()
                        ->columns(3)
                        ->visible(fn (Get $get): bool => filled($get('kind')))
                        ->schema([
                            ToggleButtons::make('system')
                                ->label(__('erp.cable_calculator.fields.system'))
                                ->options(fn (Get $get): array => $get('kind') === 'dc' ? self::options('systems', ['dc']) : self::options('systems', CableCalculator::AC_SYSTEMS))
                                // Old single-phase calculations: 1F+N.
                                ->formatStateUsing(fn (?string $state): ?string => $state === 'ac1' ? 'ac1n' : $state)
                                ->default('ac3n')
                                ->inline()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (?string $state, Set $set) => $set('voltage', CableCalculator::VOLTAGES[$state] ?? 400)),
                            $number('voltage', 'V')
                                ->helperText(fn (Get $get): ?string => $get('kind') === 'dc' ? __('erp.cable_calculator.fields.dc_voltage_help') : null)
                                ->default(400)
                                ->required()
                                ->minValue(1)
                                ->maxValue(1500),
                            $number('length', 'm')
                                ->helperText(fn (Get $get): ?string => $get('kind') === 'dc' ? __('erp.cable_calculator.fields.dc_length_help') : null)
                                ->required()
                                ->minValue(0.1)
                                ->maxValue(10000),
                            $number('protective_device', 'A')
                                ->label(fn (Get $get): string => $get('kind') === 'dc' ? __('erp.cable_calculator.fields.dc_device') : __('erp.cable_calculator.fields.protective_device'))
                                ->helperText(fn (Get $get): string => $get('kind') === 'dc' ? __('erp.cable_calculator.fields.dc_device_help') : __('erp.cable_calculator.fields.protective_device_help'))
                                ->required(fn (Get $get): bool => $get('kind') === 'dc')
                                ->minValue(1)
                                ->maxValue(6300),
                            $number('parallel_conductors')
                                ->label(fn (Get $get): string => $get('kind') === 'dc' ? __('erp.cable_calculator.fields.cables_per_pole') : __('erp.cable_calculator.fields.parallel_conductors'))
                                ->integer()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(10)
                                ->required(),
                        ]),
                    Section::make(__('erp.cable_calculator.sections.load'))
                        ->compact()
                        ->columns(3)
                        ->visible(fn (Get $get): bool => $get('kind') === 'ac')
                        ->schema([
                            ToggleButtons::make('earthing')
                                ->label(__('erp.cable_calculator.fields.earthing'))
                                ->helperText(__('erp.cable_calculator.fields.earthing_help'))
                                ->options(self::options('earthings', CableCalculator::EARTHINGS))
                                ->default('undefined')
                                ->inline()
                                ->required()
                                ->live()
                                ->columnSpan(2),
                            $number('frequency', 'Hz')
                                ->default(50)
                                ->required()
                                ->minValue(1)
                                ->maxValue(400),
                            ToggleButtons::make('load_type')
                                ->label(__('erp.cable_calculator.fields.load_type'))
                                ->options(self::options('load_types', ['current', 'power', 'apparent']))
                                ->default('current')
                                ->inline()
                                ->required()
                                ->live(),
                            $number('load_current', 'A')
                                ->minValue(0.1)
                                ->maxValue(5000)
                                ->required(fn (Get $get): bool => ! in_array($get('load_type'), ['power', 'apparent'], true))
                                ->visible(fn (Get $get): bool => ! in_array($get('load_type'), ['power', 'apparent'], true)),
                            $number('load_power', 'kW')
                                ->minValue(0.01)
                                ->maxValue(5000)
                                ->required(fn (Get $get): bool => $get('load_type') === 'power')
                                ->visible(fn (Get $get): bool => $get('load_type') === 'power'),
                            $number('load_kva', 'kVA')
                                ->helperText(__('erp.cable_calculator.fields.load_kva_help'))
                                ->minValue(0.01)
                                ->maxValue(10000)
                                ->required(fn (Get $get): bool => $get('load_type') === 'apparent')
                                ->visible(fn (Get $get): bool => $get('load_type') === 'apparent'),
                            $number('power_factor')->default(0.85)->minValue(0.1)->maxValue(1),
                        ]),
                    Section::make(__('erp.cable_calculator.sections.cable'))
                        ->compact()
                        ->columns(3)
                        ->visible(fn (Get $get): bool => $get('kind') === 'ac')
                        ->schema([
                            ToggleButtons::make('material')
                                ->label(__('erp.cable_calculator.fields.material'))
                                ->options(self::options('materials', CableCalculator::MATERIALS))
                                ->default('cu')
                                ->inline()
                                ->required()
                                ->live(),
                            ToggleButtons::make('insulation')
                                ->label(__('erp.cable_calculator.fields.insulation'))
                                ->options(self::options('insulations', CableCalculator::INSULATIONS))
                                ->default('xlpe')
                                ->inline()
                                ->required()
                                ->live(),
                            Select::make('installation_method')
                                ->label(__('erp.cable_calculator.fields.installation_method'))
                                ->options(self::options('methods', CableCalculator::METHODS))
                                ->default('C')
                                ->required()
                                ->live()
                                // Buried cables: the ground temperature, 20 °C as reference.
                                ->afterStateUpdated(fn (?string $state, ?string $old, Set $set, Get $get) => ($state === 'D1') !== ($old === 'D1') && in_array((int) $get('ambient_temperature'), [20, 30], true)
                                    ? $set('ambient_temperature', $state === 'D1' ? 20 : 30)
                                    : null),
                            $number('ambient_temperature', '°C')
                                ->label(fn (Get $get): string => $get('installation_method') === 'D1' ? __('erp.cable_calculator.fields.ground_temperature') : __('erp.cable_calculator.fields.ambient_temperature'))
                                ->integer()
                                ->default(30)
                                ->minValue(-20)
                                ->maxValue(90)
                                ->required(),
                            $number('grouped_circuits')
                                ->helperText(__('erp.cable_calculator.fields.grouped_circuits_help'))
                                ->integer()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(50)
                                ->required(),
                            $number('max_voltage_drop', '%')->default(4)->minValue(0)->maxValue(25)->required(),
                            Select::make('max_section')
                                ->label(__('erp.cable_calculator.fields.max_section'))
                                ->helperText(__('erp.cable_calculator.fields.max_section_help'))
                                ->options(collect(CableCalculator::SECTIONS)->mapWithKeys(fn (float|int $section): array => [(string) $section => CableCalculator::number((float) $section).' mm²'])->all())
                                ->placeholder('300 mm²')
                                ->formatStateUsing(fn (mixed $state): ?string => is_numeric($state) ? (string) (0 + $state) : null)
                                ->live(),
                            Toggle::make('earth_in_cable')
                                ->label(__('erp.cable_calculator.fields.earth_in_cable'))
                                ->helperText(__('erp.cable_calculator.fields.earth_in_cable_help'))
                                ->default(true)
                                ->inline(false)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('earthing') !== 'tnc'),
                            $number('short_circuit_ka', 'kA')->helperText(__('erp.cable_calculator.fields.short_circuit_help'))->minValue(0)->maxValue(200),
                            $number('short_circuit_time', 's')->minValue(0)->maxValue(5),
                        ]),
                    // DC after the 48 V calculation note: S = Imax × L × 2 × ρ / ΔU.
                    Section::make(__('erp.cable_calculator.sections.dc'))
                        ->description(__('erp.cable_calculator.dc_formula'))
                        ->compact()
                        ->columns(3)
                        ->visible(fn (Get $get): bool => $get('kind') === 'dc')
                        ->schema([
                            ToggleButtons::make('protection_type')
                                ->label(__('erp.cable_calculator.fields.protection_type'))
                                ->options(self::options('protection_types', CableCalculator::PROTECTION_TYPES))
                                ->default('breaker')
                                ->inline()
                                ->required(),
                            TextInput::make('protection_size')
                                ->label(__('erp.cable_calculator.fields.protection_size'))
                                ->placeholder('T00')
                                ->maxLength(10),
                            $number('resistivity', 'Ω·mm²/m')
                                ->helperText(__('erp.cable_calculator.fields.resistivity_help'))
                                ->default(CableCalculator::DC_RESISTIVITY)
                                ->required()
                                ->minValue(0.001)
                                ->maxValue(1),
                            $number('max_drop_volts', 'V')
                                ->helperText(__('erp.cable_calculator.fields.max_drop_volts_help'))
                                ->default(CableCalculator::DC_MAX_DROP_VOLTS)
                                ->required()
                                ->minValue(0.01)
                                ->maxValue(100),
                            Select::make('chosen_section')
                                ->label(__('erp.cable_calculator.fields.chosen_section'))
                                ->helperText(__('erp.cable_calculator.fields.chosen_section_help'))
                                ->options(collect(CableCalculator::SECTIONS)->mapWithKeys(fn (float|int $section): array => [(string) $section => CableCalculator::number((float) $section).' mm²'])->all())
                                ->placeholder(__('erp.cable_calculator.result.auto'))
                                // Saved as "95.0": the option is "95".
                                ->formatStateUsing(fn (mixed $state): ?string => is_numeric($state) ? (string) (0 + $state) : null)
                                ->live(),
                        ]),
                ]),
            Section::make(__('erp.cable_calculator.sections.result'))
                ->compact()
                ->columnSpan(['xl' => 1])
                ->visible(fn (Get $get): bool => filled($get('kind')))
                ->schema([
                    View::make('filament.cable-calculations.result')
                        ->viewData(fn (Get $get): array => ['result' => CableCalculator::calculate(self::inputsFrom($get)), 'input' => self::inputsFrom($get)]),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project', 'creator', 'circuits']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('erp.cable_calculator.fields.name'))->weight(FontWeight::Bold)->searchable()
                    ->description(fn (CableCalculation $record): ?string => $record->project?->reference),
                // Each circuit: "Krachtbord → Batterij 1 · 95 mm² per pool".
                TextColumn::make('circuits_overview')
                    ->label(__('erp.cable_calculator.circuits'))
                    ->state(fn (CableCalculation $record): array => $record->circuits->map(fn (CableCalculationCircuit $circuit): string => $circuit->route().' · '.($circuit->isDc() ? 'DC' : 'AC').' · '.($circuit->cableLabel() ?? __('erp.cable_calculator.result.none')))->all())
                    ->listWithLineBreaks()
                    ->limitList(4)
                    ->expandableLimitedList()
                    ->fontFamily(FontFamily::Mono)
                    ->size('sm'),
                TextColumn::make('check')
                    ->label(__('erp.cable_calculator.result.check'))
                    ->state(fn (CableCalculation $record): string => ($failing = $record->failingCircuits()) > 0
                        ? __('erp.cable_calculator.result.failing', ['count' => $failing])
                        : __('erp.cable_calculator.result.all_ok', ['count' => $record->circuits->count()]))
                    ->badge()
                    ->color(fn (CableCalculation $record): string => $record->failingCircuits() > 0 ? 'danger' : 'success'),
                TextColumn::make('creator.name')->label(__('erp.cable_calculator.fields.created_by'))->toggleable()->placeholder('—'),
                TextColumn::make('updated_at')->label(__('erp.cable_calculator.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label(__('erp.cable_calculator.fields.kind'))
                    ->options(self::options('kinds_short', ['ac', 'dc']))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('circuits', fn (Builder $query) => $data['value'] === 'dc' ? $query->where('system', 'dc') : $query->where('system', '!=', 'dc'))
                        : $query),
            ])
            ->recordActions([
                self::pdfAction(),
                ReplicateAction::make()
                    ->label(__('erp.cable_calculator.copy'))
                    ->beforeReplicaSaved(function (CableCalculation $replica): void {
                        $replica->name = __('erp.cable_calculator.copy_of', ['name' => $replica->name]);
                        $replica->created_by = auth()->id();
                    })
                    // With all its circuits.
                    ->after(function (CableCalculation $record, CableCalculation $replica): void {
                        foreach ($record->circuits as $circuit) {
                            $replica->circuits()->create($circuit->replicate(['cable_calculation_id', 'result'])->getAttributes());
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function pdfAction(): Action
    {
        return Action::make('pdf')
            ->label(__('erp.cable_calculator.pdf'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'cable_calculations'))
            ->url(fn (CableCalculation $record): string => route('cable-calculations.pdf', $record), shouldOpenInNewTab: true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCableCalculations::route('/'),
            'create' => CreateCableCalculation::route('/create'),
            'edit' => EditCableCalculation::route('/{record}/edit'),
        ];
    }
}
