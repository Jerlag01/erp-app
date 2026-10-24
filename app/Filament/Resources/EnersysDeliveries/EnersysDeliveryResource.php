<?php

namespace App\Filament\Resources\EnersysDeliveries;

use App\Enums\NavigationGroup;
use App\Filament\Resources\EnersysDeliveries\Pages\ManageEnersysDeliveries;
use App\Models\DeliveryFollowUp;
use App\Models\EnersysDossier;
use App\Policies\DeliveryFollowUpPolicy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Follow-up of the Enersys batteries of the 605 projects (the client orders them itself): project,
 * PO number, delivery date and delivered. Ticking "delivered" moves the project to "to be planned".
 */
class EnersysDeliveryResource extends Resource
{
    protected static ?string $model = DeliveryFollowUp::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'enersys-deliveries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Batteries;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('erp.follow_ups.enersys_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.follow_ups.enersys_plural');
    }

    public static function canViewAny(): bool
    {
        return ($user = auth()->user()) !== null && DeliveryFollowUpPolicy::allows($user, DeliveryFollowUp::ENERSYS, 'view');
    }

    public static function canCreate(): bool
    {
        return ($user = auth()->user()) !== null && DeliveryFollowUpPolicy::allows($user, DeliveryFollowUp::ENERSYS, 'create');
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
            ->kind(DeliveryFollowUp::ENERSYS)
            ->when($user, fn (Builder $query) => $query->visibleTo($user), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return [(string) settings()->get(EnersysDossier::CATEGORY_SETTING)];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('project_id')
                    ->label(__('erp.follow_ups.project'))
                    ->options(fn (?DeliveryFollowUp $record): array => FollowUpProjects::options(self::categories(), $record?->project_id))
                    ->searchable()
                    ->required()
                    ->rule(fn (?DeliveryFollowUp $record) => Rule::unique('delivery_follow_ups', 'project_id')->where('kind', DeliveryFollowUp::ENERSYS)->ignore($record?->id))
                    ->validationMessages(['unique' => __('erp.follow_ups.one_per_project'), 'in' => __('erp.follow_ups.project_category')])
                    ->disabledOn('edit')
                    ->live()
                    ->afterStateUpdated(fn (?int $state, Set $set) => $set('po_number', FollowUpProjects::poNumber($state)))
                    ->columnSpanFull(),
                TextInput::make('po_number')->label(__('erp.follow_ups.po_number'))->maxLength(30)->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                DatePicker::make('delivery_date')->label(__('erp.follow_ups.delivery_date'))->native(false)->displayFormat('d/m/Y'),
                Textarea::make('notes')->label(__('erp.follow_ups.notes'))->rows(3)->maxLength(2000)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite', 'project.category']))
            ->defaultSort('delivery_date')
            ->description(__('erp.follow_ups.enersys_help'))
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.follow_ups.project'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (DeliveryFollowUp $record): string => $record->location())
                    ->searchable(),
                TextColumn::make('po_number')->label(__('erp.follow_ups.po_number'))->fontFamily(FontFamily::Mono)->searchable()->placeholder('—'),
                TextColumn::make('delivery_date')->label(__('erp.follow_ups.delivery_date'))->date('d/m/Y')->sortable()->placeholder('—'),
                self::deliveredColumn(),
                TextColumn::make('delivered_on')->label(__('erp.follow_ups.actual_delivery'))->date('d/m/Y')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Ticking stamps today as actual delivery (and moves an Enersys project on); unticking clears it.
     */
    public static function deliveredColumn(): CheckboxColumn
    {
        return CheckboxColumn::make('delivered')
            ->label(__('erp.follow_ups.delivered'))
            ->disabled(fn (DeliveryFollowUp $record): bool => ! Gate::allows('update', $record))
            ->updateStateUsing(function (DeliveryFollowUp $record, bool $state): bool {
                abort_unless(Gate::allows('update', $record), 403);
                $state ? $record->markDelivered(auth()->user()) : $record->unmarkDelivered();

                return $state;
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnersysDeliveries::route('/'),
        ];
    }
}
