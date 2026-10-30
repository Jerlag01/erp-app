<?php

namespace App\Filament\Admin\Resources\ParentalLeaves;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\Employees\RelationManagers\ParentalLeavesRelationManager;
use App\Filament\Admin\Resources\ParentalLeaves\Pages\ManageParentalLeaves;
use App\Models\Employee;
use App\Models\ParentalLeave;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * All parental leaves (ouderschapsverlof) of the employees, for administrators and those who manage all
 * leave (e.g. the role Administratie); also on the employee page. The OV days are entered under Absences.
 */
class ParentalLeaveResource extends Resource
{
    protected static ?string $model = ParentalLeave::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'parental-leaves';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('erp.parental_leave.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.parental_leave.plural');
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->managesLeave();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('employee_id')
                ->label(__('erp.absences.employee'))
                ->options(fn (?ParentalLeave $record): array => Employee::query()
                    ->where(fn (Builder $query) => $query->whereNull('left_on')->when($record, fn (Builder $query) => $query->orWhere('id', $record->employee_id)))
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get()
                    ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->fullName()])
                    ->all())
                ->searchable()
                ->required()
                ->columnSpanFull(),
            ...ParentalLeavesRelationManager::fields(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee'))
            ->defaultSort('starts_on', 'desc')
            ->emptyStateHeading(__('erp.parental_leave.none'))
            ->columns([
                TextColumn::make('employee.last_name')->label(__('erp.absences.employee'))->formatStateUsing(fn (ParentalLeave $record): string => (string) $record->employee?->fullName())->weight('medium')->searchable(['first_name', 'last_name']),
                TextColumn::make('form')->label(__('erp.parental_leave.form'))->formatStateUsing(fn (string $state): string => __("erp.parental_leave.forms.{$state}"))->badge(),
                TextColumn::make('child_name')->label(__('erp.parental_leave.child_name'))->description(fn (ParentalLeave $record): string => $record->child_birth_date->format('d/m/Y')),
                TextColumn::make('starts_on')->label(__('erp.fields.period'))->formatStateUsing(fn (ParentalLeave $record): string => $record->starts_on->format('d/m/Y').' – '.$record->ends_on->format('d/m/Y'))->sortable(),
                TextColumn::make('days_taken')
                    ->label(__('erp.parental_leave.days_taken'))
                    ->state(fn (ParentalLeave $record): string => rtrim(rtrim(number_format((float) $record->absences()->sum('days'), 1, ',', ''), '0'), ',')),
            ])
            ->recordActions([
                Action::make('document')
                    ->label(__('erp.parental_leave.document'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (ParentalLeave $record): bool => filled($record->document))
                    ->url(fn (ParentalLeave $record): string => route('parental-leaves.document', $record), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageParentalLeaves::route('/'),
        ];
    }
}
