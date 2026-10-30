<?php

namespace App\Filament\Resources\BatteryDeliveries;

use App\Actions\Batteries\RegisterSentBaeOrder;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\BatteryDeliveries\Pages\ManageBatteryDeliveries;
use App\Filament\Resources\BatteryDossiers\Schemas\BatteryDossierForm;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\Project;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * "Overzicht Levering Batterijen": one line per 604 project with the battery order. Location and
 * order number come from the project; ticking "delivered" stamps the system date and moves the
 * project to the status of Settings > Batteries.
 */
class BatteryDeliveryResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = BatteryDelivery::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'battery-deliveries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.batteries.delivery');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.batteries.deliveries');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('project_id')
                    ->label(__('erp.batteries.order_number'))
                    ->helperText(__('erp.batteries.order_number_help'))
                    ->options(fn (?BatteryDelivery $record): array => BatteryDossierForm::projectOptions())
                    ->searchable()
                    ->required()
                    ->rule(fn (?BatteryDelivery $record) => Rule::unique('battery_deliveries', 'project_id')->ignore($record?->id))
                    ->disabledOn('edit')
                    ->live()
                    // A dossier of the project already knows the battery type and the number of cells.
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if ($dossier = BatteryDossier::query()->where('project_id', $state)->latest('id')->first()) {
                            $set('battery_model_id', $dossier->battery_model_id);
                            $set('pieces', $dossier->cellsTotal() ?: null);
                        }
                    })
                    ->columnSpan(2),
                TextInput::make('order_confirmation')
                    ->label(__('erp.batteries.order_confirmation'))
                    ->helperText(fn (?string $state): ?string => $state === RegisterSentBaeOrder::UNCONFIRMED_NUMBER ? __('erp.batteries.to_be_confirmed') : null)
                    ->live(onBlur: true)
                    ->maxLength(30),
                Select::make('battery_model_id')
                    ->label(__('erp.batteries.model'))
                    ->options(fn (): array => BatteryModel::query()->orderBy('technology')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                TextInput::make('pieces')->label(__('erp.batteries.pieces'))->integer()->minValue(0)->maxValue(9999),
                DatePicker::make('planned_delivery_date')
                    ->label(__('erp.batteries.planned_delivery_date'))
                    ->live()
                    // Another date than the requested one comes from the supplier: confirmed.
                    ->afterStateUpdated(fn (Set $set) => $set('delivery_date_confirmed', true)),
                Toggle::make('delivery_date_confirmed')
                    ->label(__('erp.batteries.delivery_date_confirmed'))
                    ->helperText(__('erp.batteries.delivery_date_confirmed_help'))
                    ->default(true)
                    ->inline(false),
                TextInput::make('total_cost')
                    ->label(__('erp.batteries.total_cost'))
                    ->numeric()
                    ->minValue(0)
                    ->step('any')
                    ->prefix('€')
                    ->visible(fn (): bool => Gate::allows('viewPrices', BatteryDelivery::class)),
                Toggle::make('executed')->label(__('erp.batteries.executed'))->inline(false),
                Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $canUpdate = fn (BatteryDelivery $record): bool => Gate::allows('update', $record);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite', 'project.category', 'batteryModel']))
            ->defaultSort(fn (Builder $query) => $query->orderBy(
                Project::query()->select('number')->whereColumn('projects.id', 'battery_deliveries.project_id'),
            ))
            ->searchPlaceholder(__('erp.batteries.deliveries_search'))
            ->columns([
                TextColumn::make('location')
                    ->label(__('erp.batteries.location'))
                    ->state(fn (BatteryDelivery $record): string => $record->location())
                    ->weight(FontWeight::Bold)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('project', fn (Builder $query) => $query
                        ->where('cow_code', 'like', "%{$search}%")
                        ->orWhereHas('workSite', fn (Builder $query) => $query->where('city', 'like', "%{$search}%")->orWhere('cow_code', 'like', "%{$search}%")))),
                TextColumn::make('order_number')
                    ->label(__('erp.batteries.order_number'))
                    ->state(fn (BatteryDelivery $record): string => $record->orderNumber())
                    ->description(fn (BatteryDelivery $record): ?string => $record->project?->reference)
                    ->fontFamily(FontFamily::Mono)
                    ->url(fn (BatteryDelivery $record): ?string => $record->project && Gate::allows('view', $record->project) ? ProjectResource::getUrl('view', ['record' => $record->project]) : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('project', fn (Builder $query) => $query->where('reference', 'like', "%{$search}%"))),
                TextColumn::make('order_confirmation')
                    ->label(__('erp.batteries.order_confirmation'))
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->placeholder('—')
                    ->description(fn (BatteryDelivery $record): ?string => $record->confirmationToBeConfirmed() ? __('erp.batteries.to_be_confirmed') : null)
                    ->color(fn (BatteryDelivery $record): ?string => $record->confirmationToBeConfirmed() ? 'warning' : null),
                TextColumn::make('batteryModel.name')->label(__('erp.batteries.model'))->placeholder('—'),
                TextColumn::make('pieces')->label(__('erp.batteries.pieces'))->alignEnd()->placeholder('—'),
                TextColumn::make('planned_delivery_date')
                    ->label(__('erp.batteries.planned_delivery_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn (BatteryDelivery $record): ?string => $record->deliveryDateToBeConfirmed() ? __('erp.batteries.to_be_confirmed') : null)
                    ->color(fn (BatteryDelivery $record): ?string => $record->deliveryDateToBeConfirmed() ? 'warning' : null),
                CheckboxColumn::make('delivered')
                    ->label(__('erp.batteries.delivered'))
                    ->tooltip(fn (BatteryDelivery $record): ?string => $record->delivered_at ? $record->delivered_at->format('d/m/Y H:i').($record->deliverer ? ' · '.$record->deliverer->name : '') : __('erp.batteries.tick_delivered'))
                    ->disabled(fn (BatteryDelivery $record): bool => ! $canUpdate($record))
                    ->updateStateUsing(function (BatteryDelivery $record, bool $state) use ($canUpdate): bool {
                        abort_unless($canUpdate($record), 403);
                        $state ? $record->markDelivered(auth()->user()) : $record->unmarkDelivered();

                        return $state;
                    }),
                TextColumn::make('delivered_at')
                    ->label(__('erp.batteries.actual_delivery_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                CheckboxColumn::make('executed')
                    ->label(__('erp.batteries.executed'))
                    ->disabled(fn (BatteryDelivery $record): bool => ! $canUpdate($record))
                    ->updateStateUsing(function (BatteryDelivery $record, bool $state) use ($canUpdate): bool {
                        abort_unless($canUpdate($record), 403);
                        $record->update(['executed' => $state]);

                        return $state;
                    }),
                DeliveryDocuments::column(),
                TextColumn::make('total_cost')
                    ->label(__('erp.batteries.total_cost'))
                    ->money('EUR', locale: 'nl_BE')
                    ->alignEnd()
                    ->placeholder('—')
                    ->visible(fn (): bool => Gate::allows('viewPrices', BatteryDelivery::class)),
            ])
            ->recordActions([
                DeliveryDocuments::action(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBatteryDeliveries::route('/'),
        ];
    }
}
