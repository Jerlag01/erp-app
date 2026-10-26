<?php

namespace App\Filament\Resources\CableCalculations;

use App\Enums\NavigationGroup;
use App\Filament\Resources\CableCalculations\Pages\CreateCableCalculation;
use App\Filament\Resources\CableCalculations\Pages\EditCableCalculation;
use App\Filament\Resources\CableCalculations\Pages\ListCableCalculations;
use App\Models\CableCalculation;
use App\Models\Project;
use App\Support\CableCalculator;
use App\Support\PdfAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
 * "Kabelberekening": the cable section as an indication, like Schneider's cable calculator, after
 * IEC 60364-5-52 (see CableCalculator). The result follows the input live; saved calculations can be
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
     * What the form holds now, for the live result.
     *
     * @return array<string, mixed>
     */
    public static function inputsFrom(Get $get): array
    {
        return collect(CableCalculation::INPUTS)->mapWithKeys(fn (string $field): array => [$field => $get($field)])->all();
    }

    public static function form(Schema $schema): Schema
    {
        $number = fn (string $name, ?string $suffix = null): TextInput => TextInput::make($name)
            ->label(__("erp.cable_calculator.fields.{$name}"))
            ->numeric()
            ->step('any')
            ->suffix($suffix)
            ->live(onBlur: true);

        return $schema
            ->columns(['default' => 1, 'xl' => 3])
            ->components([
                Grid::make(1)
                    ->columnSpan(['xl' => 2])
                    ->schema([
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
                            ]),
                        Section::make(__('erp.cable_calculator.sections.circuit'))
                            ->columns(3)
                            ->schema([
                                ToggleButtons::make('system')
                                    ->label(__('erp.cable_calculator.fields.system'))
                                    ->options(self::options('systems', CableCalculator::SYSTEMS))
                                    ->default('ac3')
                                    ->inline()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('voltage', CableCalculator::VOLTAGES[$state] ?? 400))
                                    ->columnSpanFull(),
                                $number('voltage', 'V')->default(400)->required()->minValue(1)->maxValue(1000),
                                ToggleButtons::make('load_type')
                                    ->label(__('erp.cable_calculator.fields.load_type'))
                                    ->options(self::options('load_types', ['current', 'power']))
                                    ->default('current')
                                    ->inline()
                                    ->required()
                                    ->live(),
                                $number('load_current', 'A')
                                    ->minValue(0.1)
                                    ->maxValue(5000)
                                    ->required(fn (Get $get): bool => $get('load_type') !== 'power')
                                    ->visible(fn (Get $get): bool => $get('load_type') !== 'power'),
                                $number('load_power', 'kW')
                                    ->minValue(0.01)
                                    ->maxValue(5000)
                                    ->required(fn (Get $get): bool => $get('load_type') === 'power')
                                    ->visible(fn (Get $get): bool => $get('load_type') === 'power'),
                                $number('power_factor')
                                    ->default(0.85)
                                    ->minValue(0.1)
                                    ->maxValue(1)
                                    ->visible(fn (Get $get): bool => $get('system') !== 'dc'),
                                $number('length', 'm')->required()->minValue(0.1)->maxValue(10000),
                                $number('protective_device', 'A')
                                    ->helperText(__('erp.cable_calculator.fields.protective_device_help'))
                                    ->minValue(1)
                                    ->maxValue(6300),
                            ]),
                        Section::make(__('erp.cable_calculator.sections.cable'))
                            ->columns(3)
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
                                $number('parallel_conductors')->integer()->default(1)->minValue(1)->maxValue(10)->required(),
                                Select::make('installation_method')
                                    ->label(__('erp.cable_calculator.fields.installation_method'))
                                    ->options(self::options('methods', CableCalculator::METHODS))
                                    ->default('C')
                                    ->required()
                                    ->live()
                                    // Buried cables: the ground temperature, 20 °C as reference.
                                    ->afterStateUpdated(fn (?string $state, ?string $old, Set $set, Get $get) => ($state === 'D1') !== ($old === 'D1') && in_array((int) $get('ambient_temperature'), [20, 30], true)
                                        ? $set('ambient_temperature', $state === 'D1' ? 20 : 30)
                                        : null)
                                    ->columnSpan(2),
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
                            ]),
                        Section::make(__('erp.cable_calculator.sections.limits'))
                            ->columns(3)
                            ->schema([
                                $number('max_voltage_drop', '%')->default(3)->minValue(0)->maxValue(25)->required(),
                                $number('short_circuit_ka', 'kA')->helperText(__('erp.cable_calculator.fields.short_circuit_help'))->minValue(0)->maxValue(200),
                                $number('short_circuit_time', 's')->minValue(0)->maxValue(5),
                                Textarea::make('notes')->label(__('erp.cable_calculator.fields.notes'))->rows(2)->maxLength(5000)->columnSpanFull(),
                            ]),
                    ]),
                Section::make(__('erp.cable_calculator.sections.result'))
                    ->columnSpan(['xl' => 1])
                    ->schema([
                        View::make('filament.cable-calculations.result')
                            ->viewData(fn (Get $get): array => ['result' => CableCalculator::calculate(self::inputsFrom($get)), 'input' => self::inputsFrom($get)]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project', 'creator']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('erp.cable_calculator.fields.name'))->weight(FontWeight::Bold)->searchable()
                    ->description(fn (CableCalculation $record): ?string => $record->project?->reference),
                TextColumn::make('system')->label(__('erp.cable_calculator.fields.system'))
                    ->formatStateUsing(fn (string $state, CableCalculation $record): string => __("erp.cable_calculator.systems.{$state}").' · '.CableCalculator::number((float) $record->voltage).' V')
                    ->badge()->color('gray'),
                TextColumn::make('load')
                    ->label(__('erp.cable_calculator.result.ib'))
                    ->state(fn (CableCalculation $record): ?string => isset($record->result['ib']) ? CableCalculator::number((float) $record->result['ib']).' A' : null)
                    ->description(fn (CableCalculation $record): string => CableCalculator::number((float) $record->length).' m'),
                TextColumn::make('cable')
                    ->label(__('erp.cable_calculator.result.cable'))
                    ->state(fn (CableCalculation $record): ?string => $record->cableLabel())
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->placeholder(__('erp.cable_calculator.result.none')),
                TextColumn::make('drop')
                    ->label(__('erp.cable_calculator.result.drop'))
                    ->state(fn (CableCalculation $record): ?string => isset($record->result['drop_pct']) ? CableCalculator::number((float) $record->result['drop_pct']).' %' : null)
                    ->placeholder('—'),
                TextColumn::make('creator.name')->label(__('erp.cable_calculator.fields.created_by'))->toggleable()->placeholder('—'),
                TextColumn::make('updated_at')->label(__('erp.cable_calculator.fields.updated_at'))->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('system')->label(__('erp.cable_calculator.fields.system'))->options(self::options('systems', CableCalculator::SYSTEMS)),
            ])
            ->recordActions([
                self::pdfAction(),
                ReplicateAction::make()
                    ->label(__('erp.cable_calculator.copy'))
                    ->excludeAttributes(['result'])
                    ->beforeReplicaSaved(function (CableCalculation $replica): void {
                        $replica->name = __('erp.cable_calculator.copy_of', ['name' => $replica->name]);
                        $replica->created_by = auth()->id();
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
