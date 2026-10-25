<?php

namespace App\Filament\Resources\AccessBadges;

use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Shared by the badge & key list and the security tag list: one entry per employee of the employee
 * register, and the "active / deactivated" status that follows whether the employee still works here.
 */
class AccessEmployees
{
    /**
     * The employee to choose: those in service, plus the current one of the entry.
     */
    public static function select(string $table): Select
    {
        return Select::make('employee_id')
            ->label(__('erp.access.employee'))
            ->options(fn (?Model $record): array => Employee::query()
                ->where(fn ($query) => $query->whereNull('left_on')->when($record?->employee_id, fn ($query, int $id) => $query->orWhere('id', $id)))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->fullName()])
                ->all())
            ->searchable()
            ->required()
            ->rule(fn (?Model $record) => Rule::unique($table, 'employee_id')->ignore($record?->id))
            ->validationMessages(['unique' => __('erp.access.one_per_employee')])
            ->columnSpanFull();
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('erp.access.status'))
            ->state(fn (Model $record): string => $record->isActive() ? __('erp.access.active') : __('erp.access.deactivated'))
            ->badge()
            ->color(fn (Model $record): string => $record->isActive() ? 'success' : 'gray');
    }

    public static function employeeColumn(): TextColumn
    {
        return TextColumn::make('employee.last_name')
            ->label(__('erp.access.employee'))
            ->formatStateUsing(fn (Model $record): string => (string) $record->employee?->fullName())
            ->searchable(['first_name', 'last_name'])
            ->sortable();
    }
}
