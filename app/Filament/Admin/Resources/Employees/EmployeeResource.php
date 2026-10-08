<?php

namespace App\Filament\Admin\Resources\Employees;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Admin\Resources\Employees\Pages\EditEmployee;
use App\Filament\Admin\Resources\Employees\Pages\ListEmployees;
use App\Filament\Admin\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Admin\Resources\Employees\RelationManagers\CertificatesRelationManager;
use App\Filament\Admin\Resources\Employees\RelationManagers\MedicalChecksRelationManager;
use App\Filament\Admin\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Admin\Resources\Employees\Schemas\EmployeeInfolist;
use App\Filament\Admin\Resources\Employees\Tables\EmployeesTable;
use App\Models\Employee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The employee register (administrators only). Login accounts stay under Users, so accounts
 * can also be made without this module; adding an employee here creates or links one.
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'employees';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'last_name';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['first_name', 'last_name', 'private_email'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.employee.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.employee.plural');
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->fullName();
    }

    /**
     * Employees whose yearly medical check-up is due.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Employee::query()->employed()->with('latestMedicalCheck')->get()->filter(fn (Employee $employee): bool => $employee->medicalOverdue())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.employees.medical_due_badge');
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
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
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'view' => ViewEmployee::route('/{record}'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
