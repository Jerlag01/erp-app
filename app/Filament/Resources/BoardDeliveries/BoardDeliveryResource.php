<?php

namespace App\Filament\Resources\BoardDeliveries;

use App\Enums\NavigationGroup;
use App\Filament\Resources\BoardDeliveries\Pages\ManageBoardDeliveries;
use App\Filament\Resources\EnersysDeliveries\EnersysDeliveryResource;
use App\Filament\Resources\EnersysDeliveries\FollowUpProjects;
use App\Models\BoardSupplier;
use App\Models\DeliveryFollowUp;
use App\Policies\DeliveryFollowUpPolicy;
use App\Support\ProjectProgress;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Follow-up of the electrical boards of the 610–650 projects, one tab per board supplier. ELP and
 * P&V panels ("simple"): project, PO number, delivery date, delivered. CE+T and Automation LP
 * ("configured"): PO number, project, expected and actual delivery, configuration date and completed
 * (installed and configured). No project status changes here.
 */
class BoardDeliveryResource extends Resource
{
    protected static ?string $model = DeliveryFollowUp::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'board-deliveries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::BoardDeliveries;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('erp.follow_ups.board_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.follow_ups.board_plural');
    }

    public static function canViewAny(): bool
    {
        return ($user = auth()->user()) !== null && DeliveryFollowUpPolicy::allows($user, DeliveryFollowUp::BOARD, 'view');
    }

    public static function canCreate(): bool
    {
        return ($user = auth()->user()) !== null && DeliveryFollowUpPolicy::allows($user, DeliveryFollowUp::BOARD, 'create');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows('delete', $record);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->kind(DeliveryFollowUp::BOARD)
            ->when($user, fn (Builder $query) => $query->visibleTo($user), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    /**
     * The supplier of the open tab ("supplier-3"), or of the record.
     */
    public static function supplierOf(mixed $livewire): ?BoardSupplier
    {
        $id = is_string($livewire?->activeTab ?? null) ? (int) str_replace('supplier-', '', $livewire->activeTab) : null;

        return $id ? BoardSupplier::query()->find($id) : null;
    }

    protected static function configured(Get $get): bool
    {
        return (bool) BoardSupplier::query()->find($get('board_supplier_id'))?->isConfigured();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('board_supplier_id')
                    ->label(__('erp.follow_ups.supplier'))
                    ->options(fn (?DeliveryFollowUp $record): array => BoardSupplier::query()
                        ->where(fn (Builder $query) => $query->where('is_active', true)->when($record?->board_supplier_id, fn (Builder $query, int $id) => $query->orWhere('id', $id)))
                        ->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn ($livewire): ?int => self::supplierOf($livewire)?->id)
                    ->required()
                    ->live(),
                Select::make('project_id')
                    ->label(__('erp.follow_ups.project'))
                    ->options(fn (?DeliveryFollowUp $record): array => FollowUpProjects::options(ProjectProgress::CATEGORIES, $record?->project_id))
                    ->searchable()
                    ->required()
                    ->validationMessages(['in' => __('erp.follow_ups.project_category')])
                    ->disabledOn('edit')
                    ->live()
                    ->afterStateUpdated(fn (?int $state, Set $set) => $set('po_number', FollowUpProjects::poNumber($state))),
                TextInput::make('po_number')->label(__('erp.follow_ups.po_number'))->maxLength(30)->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                DatePicker::make('delivery_date')
                    ->label(fn (Get $get): string => self::configured($get) ? __('erp.follow_ups.expected_delivery') : __('erp.follow_ups.delivery_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                DatePicker::make('delivered_on')->label(__('erp.follow_ups.actual_delivery'))->native(false)->displayFormat('d/m/Y')->visible(fn (Get $get): bool => self::configured($get)),
                DatePicker::make('configured_on')->label(__('erp.follow_ups.configured_on'))->native(false)->displayFormat('d/m/Y')->visible(fn (Get $get): bool => self::configured($get)),
                Toggle::make('completed')->label(__('erp.follow_ups.completed'))->visible(fn (Get $get): bool => self::configured($get))->inline(false),
                Textarea::make('notes')->label(__('erp.follow_ups.notes'))->rows(3)->maxLength(2000)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $configured = fn ($livewire): bool => (bool) self::supplierOf($livewire)?->isConfigured();
        $simple = fn ($livewire): bool => ! $configured($livewire);
        $date = fn (string $name, string $label): TextInputColumn => TextInputColumn::make($name)
            ->label($label)
            ->type('date')
            // The browser's date field only takes Y-m-d, not the full date of the cast.
            ->getStateUsing(fn (DeliveryFollowUp $record): ?string => $record->{$name}?->format('Y-m-d'))
            ->rules(['nullable', 'date'])
            ->disabled(fn (DeliveryFollowUp $record): bool => ! Gate::allows('update', $record))
            ->visible($configured);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite', 'project.category', 'supplier']))
            ->defaultSort('delivery_date')
            ->columns([
                // CE+T and Automation LP: the PO number comes first.
                TextColumn::make('po_lead')
                    ->label(__('erp.follow_ups.po_number'))
                    ->state(fn (DeliveryFollowUp $record): ?string => $record->po_number)
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->placeholder('—')
                    ->visible($configured),
                TextColumn::make('project.reference')
                    ->label(__('erp.follow_ups.project'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (DeliveryFollowUp $record): string => $record->location())
                    ->searchable(),
                TextColumn::make('po_number')->label(__('erp.follow_ups.po_number'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—')->visible($simple),
                TextColumn::make('delivery_date')
                    ->label(fn ($livewire): string => $configured($livewire) ? __('erp.follow_ups.expected_delivery') : __('erp.follow_ups.delivery_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                EnersysDeliveryResource::deliveredColumn()->visible($simple),
                $date('delivered_on', __('erp.follow_ups.actual_delivery')),
                $date('configured_on', __('erp.follow_ups.configured_on')),
                CheckboxColumn::make('completed')
                    ->label(__('erp.follow_ups.completed'))
                    ->disabled(fn (DeliveryFollowUp $record): bool => ! Gate::allows('update', $record))
                    ->updateStateUsing(function (DeliveryFollowUp $record, bool $state): bool {
                        abort_unless(Gate::allows('update', $record), 403);
                        $record->markCompleted($state);

                        return $state;
                    })
                    ->visible($configured),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data, DeliveryFollowUp $record): array => [
                    ...$data,
                    'completed_at' => ($data['completed'] ?? false) ? ($record->completed_at ?? now()) : null,
                ]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBoardDeliveries::route('/'),
        ];
    }
}
