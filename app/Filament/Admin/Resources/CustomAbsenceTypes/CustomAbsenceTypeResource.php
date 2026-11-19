<?php

namespace App\Filament\Admin\Resources\CustomAbsenceTypes;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\CustomAbsenceTypes\Pages\ManageCustomAbsenceTypes;
use App\Models\CustomAbsenceType;
use App\Support\TimesheetReport;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * "Absence types": the absence types administration adds themselves, next to the fixed ones (sickness,
 * economically unemployed, weerverlet, …). Each has a short code for the prestation overviews and says
 * how the prestation sheet counts a day of it. A type in use cannot be deleted, only made inactive.
 */
class CustomAbsenceTypeResource extends Resource
{
    protected static ?string $model = CustomAbsenceType::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'absence-types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 4;

    /**
     * Who manages all leave (administrators, e.g. Administratie).
     */
    public static function canViewAny(): bool
    {
        return modules()->vacations() && (bool) auth()->user()?->managesLeave();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function getModelLabel(): string
    {
        return __('erp.custom_absence_types.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.custom_absence_types.plural');
    }

    /**
     * The codes of the fixed types and days (V, Z, EW, WV, N …), which a custom type cannot use.
     *
     * @return list<string>
     */
    public static function reservedCodes(): array
    {
        return array_values(array_unique([...array_values(TimesheetReport::CODES), TimesheetReport::NIGHT, 'OB']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')
                    ->label(__('erp.custom_absence_types.code'))
                    ->helperText(__('erp.custom_absence_types.code_help'))
                    ->required()
                    ->maxLength(4)
                    ->regex('/^[A-Za-z0-9]{1,4}$/')
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state)))
                    ->unique(ignoreRecord: true)
                    ->notIn(fn (): array => [...self::reservedCodes(), ...array_map('mb_strtolower', self::reservedCodes())])
                    ->validationMessages(['not_in' => __('erp.custom_absence_types.code_reserved')]),
                ColorPicker::make('color')
                    ->label(__('erp.custom_absence_types.color'))
                    ->default('#64748b')
                    ->regex('/^#[0-9a-fA-F]{6}$/')
                    ->required(),
                Grid::make(3)->columnSpanFull()->schema([
                    TextInput::make('name')->label(__('erp.custom_absence_types.name_nl'))->required()->maxLength(100),
                    TextInput::make('name_fr')->label(__('erp.custom_absence_types.name_fr'))->maxLength(100),
                    TextInput::make('name_en')->label(__('erp.custom_absence_types.name_en'))->maxLength(100),
                ]),
                ToggleButtons::make('counting')
                    ->label(__('erp.custom_absence_types.counting'))
                    ->options(collect(CustomAbsenceType::COUNTINGS)->mapWithKeys(fn (string $counting): array => [$counting => __('erp.custom_absence_types.countings.'.$counting)])->all())
                    ->default(CustomAbsenceType::UNWORKED)
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('blocks_planning')->label(__('erp.custom_absence_types.blocks_planning'))->helperText(__('erp.custom_absence_types.blocks_planning_help')),
                Toggle::make('is_private')->label(__('erp.custom_absence_types.is_private'))->helperText(__('erp.custom_absence_types.is_private_help'))->default(true),
                Toggle::make('takes_document')->label(__('erp.custom_absence_types.takes_document'))->helperText(__('erp.custom_absence_types.takes_document_help')),
                Toggle::make('is_active')->label(__('erp.fields.active'))->default(true),
                TextInput::make('sort')->label(__('erp.interventions.sort'))->integer()->minValue(0)->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->paginated(false)
            ->emptyStateHeading(__('erp.custom_absence_types.none'))
            ->emptyStateDescription(__('erp.custom_absence_types.none_help'))
            ->modifyQueryUsing(fn ($query) => $query->withCount('absences'))
            ->columns([
                TextColumn::make('code')->label(__('erp.custom_absence_types.code'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->searchable(),
                ColorColumn::make('color')->label(__('erp.custom_absence_types.color')),
                TextColumn::make('name')->label(__('erp.fields.name'))->state(fn (CustomAbsenceType $record): string => $record->label())->searchable(),
                TextColumn::make('counting')->label(__('erp.custom_absence_types.counting'))->formatStateUsing(fn (string $state): string => __('erp.custom_absence_types.countings.'.$state))->badge()->color('gray'),
                IconColumn::make('blocks_planning')->label(__('erp.custom_absence_types.blocks_planning'))->boolean(),
                IconColumn::make('is_private')->label(__('erp.custom_absence_types.is_private'))->boolean(),
                TextColumn::make('absences_count')->label(__('erp.resources.absence.plural'))->badge(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (CustomAbsenceType $record): bool => static::canDelete($record)),
            ]);
    }

    /**
     * A type with absences stays (they keep their type); it can be made inactive instead.
     */
    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && ! $record->absences()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomAbsenceTypes::route('/'),
        ];
    }
}
