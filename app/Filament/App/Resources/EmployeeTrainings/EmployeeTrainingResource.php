<?php

namespace App\Filament\App\Resources\EmployeeTrainings;

use App\Filament\Admin\Pages\TrainingMatrix;
use App\Filament\Admin\Resources\Employees\RelationManagers\CertificatesRelationManager;
use App\Filament\Admin\Resources\Employees\RelationManagers\MedicalChecksRelationManager;
use App\Filament\App\Resources\EmployeeTrainings\Pages\ListEmployeeTrainings;
use App\Filament\App\Resources\EmployeeTrainings\Pages\ViewEmployeeTraining;
use App\Models\Employee;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The overview "Trainings & medical check-ups" in the employee portal, per employee: only their certificates
 * and medical check-ups (nothing else from the employee register), for whoever has "certificates.*" or
 * "medical_checks.*". Each tab follows its own permission (EmployeeCertificatePolicy, EmployeeMedicalCheckPolicy).
 */
class EmployeeTrainingResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $slug = 'training-overview/employees';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('erp.resources.employee.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.training_matrix.title');
    }

    public static function canViewAny(): bool
    {
        return TrainingMatrix::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return TrainingMatrix::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label(__('erp.training_matrix.name'))->state(fn (Employee $record): string => $record->fullName()),
                    TextEntry::make('user.job_title')->label(__('erp.training_matrix.function'))->placeholder('—'),
                    TextEntry::make('is_mentor')->label(__('erp.employees.is_mentor'))->formatStateUsing(fn (bool $state): string => $state ? '✓' : '—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->employed())
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')->label(__('erp.training_matrix.name'))->state(fn (Employee $record): string => $record->fullName())->searchable(['first_name', 'last_name'])->sortable(),
                TextColumn::make('user.job_title')->label(__('erp.training_matrix.function'))->placeholder('—'),
            ])
            ->recordUrl(fn (Employee $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            CertificatesRelationManager::class,
            MedicalChecksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeTrainings::route('/'),
            'view' => ViewEmployeeTraining::route('/{record}'),
        ];
    }
}
