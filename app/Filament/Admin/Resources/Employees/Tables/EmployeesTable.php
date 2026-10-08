<?php

namespace App\Filament\Admin\Resources\Employees\Tables;

use App\Enums\EmploymentCategory;
use App\Models\Employee;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['user', 'latestMedicalCheck'])
                ->withCount([
                    'certificates as expiring_certificates_count' => fn (Builder $query) => $query
                        ->whereNotNull('expires_on')
                        ->whereDate('expires_on', '<=', today()->addDays(Employee::EXPIRY_WARNING_DAYS)),
                ]))
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label(__('erp.fields.name'))
                    ->state(fn (Employee $record): string => $record->fullName())
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Employee $record): ?string => $record->user?->email)
                    ->searchable(['first_name', 'last_name', 'private_email'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('employment_category')
                    ->label(__('erp.employees.employment_category'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('employment_date')
                    ->label(__('erp.employees.employment_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('medical_due')
                    ->label(__('erp.employees.medical_due'))
                    ->state(fn (Employee $record): ?string => $record->nextMedicalDue()?->format('d/m/Y'))
                    ->badge()
                    ->color(fn (Employee $record): string => $record->medicalOverdue() ? 'danger' : (($due = $record->nextMedicalDue()) && $due->lte(today()->addDays(30)) ? 'warning' : 'success'))
                    ->placeholder('—'),
                TextColumn::make('expiring_certificates_count')
                    ->label(__('erp.employees.certificates_attention'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
                    ->tooltip(__('erp.employees.certificates_attention_help', ['days' => Employee::EXPIRY_WARNING_DAYS])),
                TextColumn::make('left_on')
                    ->label(__('erp.employees.status'))
                    ->state(fn (Employee $record): string => $record->isEmployed() ? __('erp.employees.employed') : __('erp.employees.left_on_date', ['date' => $record->left_on->format('d/m/Y')]))
                    ->badge()
                    ->color(fn (Employee $record): string => $record->isEmployed() ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('employment_category')
                    ->label(__('erp.employees.employment_category'))
                    ->options(EmploymentCategory::class),
                Filter::make('certificates_attention')
                    ->label(__('erp.employees.filter_certificates'))
                    ->query(fn (Builder $query) => $query->whereHas('certificates', fn (Builder $query) => $query
                        ->whereNotNull('expires_on')
                        ->whereDate('expires_on', '<=', today()->addDays(Employee::EXPIRY_WARNING_DAYS)))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
