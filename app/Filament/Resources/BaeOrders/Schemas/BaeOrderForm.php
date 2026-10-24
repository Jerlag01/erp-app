<?php

namespace App\Filament\Resources\BaeOrders\Schemas;

use App\Actions\Batteries\CreateBaeOrder;
use App\Enums\BatteryMounting;
use App\Enums\BatteryTechnology;
use App\Models\BaeOrder;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

/**
 * The BAE order as the Excel "calc.xlsx": the Input (order, batteries, lines, delivery) on the left of
 * the workflow and the Order sheet as a live overview below it. Choosing a type and mounting fills in
 * the lines from the battery models (rack and connectors per battery) and the BAE price per cell.
 */
class BaeOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $mono = ['style' => 'font-family: var(--erp-mono);'];

        return $schema
            ->columns(1)
            ->components([
                // A new order only asks for its project; the rest comes from Settings > Batteries (and its dossier).
                Select::make('project_id')
                    ->label(__('erp.batteries.bae_order.fields.project'))
                    ->options(fn (): array => self::projectOptions())
                    ->searchable()
                    ->required()
                    ->visibleOn('create'),
                Section::make(__('erp.batteries.bae_order.sections.order'))
                    ->columns(4)
                    ->hiddenOn('create')
                    ->schema([
                        TextInput::make('order_number')
                            ->label(__('erp.batteries.bae_order.fields.order_number'))
                            ->helperText(__('erp.batteries.bae_order.fields.order_number_help'))
                            ->required()
                            ->maxLength(50)
                            ->live(onBlur: true)
                            ->extraInputAttributes($mono),
                        DatePicker::make('order_date')->label(__('erp.batteries.bae_order.fields.order_date'))->native(false)->displayFormat('d/m/Y')->required()->live(),
                        TextInput::make('contract')->label(__('erp.batteries.bae_order.fields.contract'))->maxLength(100)->live(onBlur: true)->columnSpan(2),
                    ]),
                Section::make(__('erp.batteries.bae_order.sections.batteries'))
                    ->columns(4)
                    ->hiddenOn('create')
                    ->schema([
                        Select::make('battery_model_id')
                            ->label(__('erp.batteries.bae_order.fields.battery_model'))
                            ->options(fn (): array => BatteryModel::query()->where('is_active', true)->orderBy('technology')->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::applyCatalogue($get, $set)),
                        Select::make('mounting')
                            ->label(__('erp.batteries.bae_order.fields.mounting'))
                            ->options(BatteryMounting::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::applyCatalogue($get, $set)),
                        TextInput::make('battery_count')
                            ->label(__('erp.batteries.bae_order.fields.battery_count'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(BaeOrder::MAX_BATTERIES)
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('cells_per_battery')->label(__('erp.batteries.bae_order.fields.cells_per_battery'))->integer()->minValue(1)->maxValue(255)->required()->live(onBlur: true),
                        TextInput::make('volts_per_cell')->label(__('erp.batteries.bae_order.fields.volts_per_cell'))->numeric()->minValue(0)->step('any')->suffix('V')->required()->live(onBlur: true),
                        TextInput::make('capacity_ah')->label(__('erp.batteries.bae_order.fields.capacity_ah'))->integer()->minValue(0)->suffix('Ah')->live(onBlur: true),
                        Select::make('technology')
                            ->label(__('erp.batteries.bae_order.fields.technology'))
                            ->options(collect(BatteryTechnology::cases())->mapWithKeys(fn (BatteryTechnology $technology): array => [$technology->value => $technology->getLabel()])->all())
                            ->live(),
                        TextInput::make('cell_price')
                            ->label(__('erp.batteries.bae_order.fields.cell_price'))
                            ->helperText(__('erp.batteries.bae_order.fields.cell_price_help'))
                            ->numeric()
                            ->minValue(0)
                            ->step('any')
                            ->prefix('€')
                            ->required()
                            ->live(onBlur: true),
                    ]),
                Section::make(__('erp.batteries.bae_order.sections.lines'))
                    ->description(__('erp.batteries.bae_order.sections.lines_help'))
                    ->hiddenOn('create')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 6])->schema([
                            Text::make(__('erp.batteries.bae_order.fields.line'))->weight(FontWeight::Bold)->columnSpan(2),
                            Text::make(__('erp.batteries.bae_order.fields.code'))->weight(FontWeight::Bold)->columnSpan(3),
                            Text::make(__('erp.batteries.bae_order.fields.per_battery'))->weight(FontWeight::Bold),
                            ...collect(BaeOrder::LINES)->flatMap(fn (array $line, string $key): array => [
                                Text::make($line['name'])->columnSpan(2),
                                TextInput::make("lines.{$key}.code")->hiddenLabel()->maxLength(60)->live(onBlur: true)->columnSpan(3),
                                TextInput::make("lines.{$key}.per_battery")->hiddenLabel()->integer()->minValue(0)->maxValue(9999)->required()->live(onBlur: true),
                            ])->all(),
                            Text::make('Rack')->columnSpan(2),
                            TextInput::make('lines.rack.code')->hiddenLabel()->placeholder(__('erp.batteries.bae_order.fields.rack_code'))->maxLength(60)->live(onBlur: true)->columnSpan(3),
                            TextInput::make('lines.rack.price')
                                ->hiddenLabel()
                                ->placeholder(__('erp.batteries.bae_order.fields.rack_price'))
                                ->numeric()
                                ->minValue(0)
                                ->step('any')
                                ->prefix('€')
                                ->live(onBlur: true),
                        ]),
                    ]),
                Section::make(__('erp.batteries.bae_order.sections.delivery'))
                    ->columns(4)
                    ->hiddenOn('create')
                    ->collapsible()
                    ->schema([
                        TextInput::make('delivery_date')->label(__('erp.batteries.bae_order.fields.delivery_date'))->maxLength(100)->live(onBlur: true),
                        TextInput::make('delivery_address')->label(__('erp.batteries.bae_order.fields.delivery_address'))->maxLength(255)->live(onBlur: true)->columnSpan(3),
                        TextInput::make('contact_name')->label(__('erp.batteries.bae_order.fields.contact_name'))->maxLength(100)->live(onBlur: true),
                        TextInput::make('contact_phone')->label(__('erp.batteries.bae_order.fields.contact_phone'))->tel()->maxLength(50)->live(onBlur: true),
                        TextInput::make('contact_mobile')->label(__('erp.batteries.bae_order.fields.contact_mobile'))->tel()->maxLength(50)->live(onBlur: true),
                        TextInput::make('contact_fax')->label(__('erp.batteries.bae_order.fields.contact_fax'))->maxLength(50)->live(onBlur: true),
                        TextInput::make('contact_email')->label(__('erp.batteries.bae_order.fields.contact_email'))->email()->maxLength(150)->live(onBlur: true)->columnSpan(2),
                        Textarea::make('billing_block')->label(__('erp.batteries.bae_order.fields.billing_block'))->rows(5)->maxLength(1000)->live(onBlur: true)->columnSpan(2),
                        Textarea::make('remarks')->label(__('erp.batteries.bae_order.fields.remarks'))->rows(5)->maxLength(2000)->live(onBlur: true)->columnSpanFull(),
                    ]),
                Section::make(__('erp.batteries.bae_order.sections.preview'))
                    ->hiddenOn('create')
                    ->schema([
                        View::make('filament.batteries.bae-order-preview')
                            ->viewData(fn (Get $get, ?BaeOrder $record): array => ['order' => self::preview($get, $record)]),
                    ]),
            ]);
    }

    /**
     * The type or mounting changed: the BAE price per cell, the type and the lines of the catalogue.
     */
    public static function applyCatalogue(Get $get, Set $set): void
    {
        foreach (CreateBaeOrder::fromCatalogue($get('battery_model_id') ? (int) $get('battery_model_id') : null, $get('mounting') instanceof BatteryMounting ? $get('mounting')->value : $get('mounting')) as $key => $value) {
            $set($key, $value);
        }
    }

    /**
     * The order as it stands in the form (not saved yet), for the overview.
     */
    public static function preview(Get $get, ?BaeOrder $record): BaeOrder
    {
        $order = new BaeOrder;
        $order->fill([
            'order_number' => $get('order_number'),
            'order_date' => $get('order_date'),
            'contract' => $get('contract'),
            'battery_model_id' => $get('battery_model_id') ?: null,
            'mounting' => $get('mounting') instanceof BatteryMounting ? $get('mounting')->value : $get('mounting'),
            'capacity_ah' => is_numeric($get('capacity_ah')) ? (int) $get('capacity_ah') : null,
            'technology' => $get('technology'),
            'battery_count' => is_numeric($get('battery_count')) ? (int) $get('battery_count') : 0,
            'cells_per_battery' => is_numeric($get('cells_per_battery')) ? (int) $get('cells_per_battery') : 0,
            'volts_per_cell' => is_numeric($get('volts_per_cell')) ? $get('volts_per_cell') : 0,
            'cell_price' => is_numeric($get('cell_price')) ? $get('cell_price') : 0,
            'lines' => (array) $get('lines'),
        ]);
        $order->setRelation('project', $record?->project);
        $order->setRelation('batteryModel', $order->battery_model_id ? BatteryModel::query()->find($order->battery_model_id) : null);

        return $order;
    }

    /**
     * 604 projects the user can see that have no BAE order yet.
     *
     * @return array<int, string>
     */
    public static function projectOptions(): array
    {
        return Project::query()
            ->visibleTo(auth()->user())
            ->whereHas('category', fn (Builder $query) => $query->where('code', settings()->get(BatteryDossier::CATEGORY_SETTING)))
            ->whereDoesntHave('baeOrder')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' '.($project->subtitle() ? '— '.$project->subtitle() : ''))])
            ->all();
    }
}
