<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\CertificateTypes\CertificateTypeResource;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Filament\App\Resources\EmployeeTrainings\EmployeeTrainingResource;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\EmployeeMedicalCheck;
use App\Support\TrainingMatrix as Matrix;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * "Opleidingen & medisch onderzoek": the certificates and medical check-ups of all employees at a glance. In the
 * admin panel and, for whoever has "certificates.view" or "medical_checks.view", in the employee portal; the
 * columns follow those permissions and a name opens the employee's certificates and check-ups.
 */
class TrainingMatrix extends Page
{
    protected string $view = 'filament.admin.pages.training-matrix';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'training-overview';

    #[Url]
    public string $search = '';

    #[Url(as: 'problems')]
    public bool $onlyProblems = false;

    public static function canAccess(): bool
    {
        return modules()->employees() && (Gate::allows('viewAny', EmployeeCertificate::class) || Gate::allows('viewAny', EmployeeMedicalCheck::class));
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.training_matrix.title');
    }

    public function getTitle(): string
    {
        return __('erp.training_matrix.title');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * "Attesttypes": add and manage the kinds of certificates (with "certificates.types").
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('certificateTypes')
                ->label(__('erp.certificate_types.manage'))
                ->icon(Heroicon::OutlinedAcademicCap)
                ->color('gray')
                ->visible(fn (): bool => CertificateTypeResource::canViewAny())
                ->url(fn (): string => CertificateTypeResource::getUrl('index')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'matrix' => app(Matrix::class)->build($this->search, $this->onlyProblems, Gate::allows('viewAny', EmployeeCertificate::class), Gate::allows('viewAny', EmployeeMedicalCheck::class)),
            'employeeUrl' => fn (Employee $employee): string => Filament::getCurrentPanel()?->getId() === 'admin'
                ? EmployeeResource::getUrl('view', ['record' => $employee], panel: 'admin')
                : EmployeeTrainingResource::getUrl('view', ['record' => $employee], panel: 'app'),
        ];
    }
}
