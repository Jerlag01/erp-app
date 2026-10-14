<?php

namespace App\Support;

use App\Enums\AssetLogType;
use App\Enums\ExpenseStatus;
use App\Enums\InterventionReportStatus;
use App\Enums\ProjectStatus;
use App\Enums\RequestStatus;
use App\Enums\VacationStatus;
use App\Filament\Admin\Pages\TrainingMatrix as TrainingMatrixPage;
use App\Filament\Admin\Resources\EmployeeChangeRequests\EmployeeChangeRequestResource;
use App\Filament\Admin\Resources\InterventionReports\InterventionReportResource;
use App\Filament\Pages\Planning;
use App\Filament\Resources\AssetChecks\AssetCheckResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\BatteryDeliveries\BatteryDeliveryResource;
use App\Filament\Resources\BatteryDossiers\BatteryDossierResource;
use App\Filament\Resources\ChargingStations\ChargingStationResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Resources\VacationRequests\VacationRequestResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Asset;
use App\Models\AssetCheck;
use App\Models\AssetLog;
use App\Models\EmployeeCertificate;
use App\Models\EmployeeCertificateRequest;
use App\Models\EmployeeChangeRequest;
use App\Models\Invoice;
use App\Models\PlanningItem;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\AssetCheckPolicy;
use Closure;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * The counters a user can put on their dashboard. Each one only counts what the user may see, and is only
 * offered when its module is on and the user has the right for it.
 */
class DashboardCounters
{
    /**
     * @return array<string, array{icon: Heroicon, color: string, visible: Closure(User): bool, count: Closure(User): int, url: Closure(): ?string}>
     */
    public static function all(): array
    {
        $can = fn (string $resource): bool => rescue(fn (): bool => $resource::canViewAny(), false, false);
        $query = fn (string $resource): Builder => $resource::getEloquentQuery();

        return [
            'my_planning_today' => [
                'icon' => Heroicon::OutlinedCalendarDays, 'color' => 'primary',
                'visible' => fn (User $user): bool => modules()->planning(),
                'count' => fn (User $user): int => PlanningItem::query()->where('user_id', $user->id)->whereDate('date', today())->count(),
                'url' => fn (): ?string => Planning::getUrl(),
            ],
            'open_projects' => [
                'icon' => Heroicon::OutlinedBriefcase, 'color' => 'primary',
                'visible' => fn (User $user): bool => $can(ProjectResource::class),
                'count' => fn (User $user): int => $query(ProjectResource::class)->whereNotIn('status', [ProjectStatus::Executed, ProjectStatus::InvoicingToDo, ProjectStatus::Invoiced])->count(),
                'url' => fn (): ?string => ProjectResource::getUrl(),
            ],
            'projects_to_invoice' => [
                'icon' => Heroicon::OutlinedCurrencyEuro, 'color' => 'danger',
                'visible' => fn (User $user): bool => $can(ProjectResource::class),
                'count' => fn (User $user): int => $query(ProjectResource::class)->where('status', ProjectStatus::InvoicingToDo)->count(),
                'url' => fn (): ?string => ProjectResource::getUrl(),
            ],
            'vacation_requests_pending' => [
                'icon' => Heroicon::OutlinedSun, 'color' => 'warning',
                'visible' => fn (User $user): bool => modules()->vacations() && $user->hasPermission('vacations.approve') && $can(VacationRequestResource::class),
                'count' => fn (User $user): int => $query(VacationRequestResource::class)->where('status', VacationStatus::Pending)->count(),
                'url' => fn (): ?string => VacationRequestResource::getUrl(),
            ],
            'vehicle_controls_due' => [
                'icon' => Heroicon::OutlinedTruck, 'color' => 'warning',
                'visible' => fn (User $user): bool => $can(VehicleResource::class),
                'count' => fn (User $user): int => Vehicle::visibleTo($user)->controlDue()->count(),
                'url' => fn (): ?string => VehicleResource::getUrl(),
            ],
            'asset_inspections_due' => [
                'icon' => Heroicon::OutlinedBolt, 'color' => 'warning',
                'visible' => fn (User $user): bool => $can(AssetResource::class),
                'count' => fn (User $user): int => Asset::visibleTo($user)->inspectionDue()->count(),
                'url' => fn (): ?string => AssetResource::getUrl(),
            ],
            'open_damages' => [
                'icon' => Heroicon::OutlinedExclamationTriangle, 'color' => 'danger',
                'visible' => fn (User $user): bool => $can(AssetResource::class),
                'count' => fn (User $user): int => AssetLog::visibleTo($user)->where('type', AssetLogType::Damage)->whereNull('resolved_at')->count(),
                'url' => fn (): ?string => AssetResource::getUrl(),
            ],
            'asset_checks_open' => [
                'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'color' => 'warning',
                'visible' => fn (User $user): bool => AssetCheckPolicy::enabled() && $can(AssetCheckResource::class),
                'count' => fn (User $user): int => AssetCheck::query()->open()->whereIn('team_id', $user->teamIds())->whereHas('items', fn (Builder $query) => $query->whereNull('result'))->count(),
                'url' => fn (): ?string => AssetCheckResource::getUrl(),
            ],
            'low_stock' => [
                'icon' => Heroicon::OutlinedArchiveBox, 'color' => 'danger',
                'visible' => fn (User $user): bool => $can(StockItemResource::class),
                'count' => fn (User $user): int => StockLevel::query()->visibleTo($user)->low()->count(),
                'url' => fn (): ?string => StockItemResource::getUrl(),
            ],
            'battery_dossiers_open' => [
                'icon' => Heroicon::OutlinedBattery50, 'color' => 'primary',
                'visible' => fn (User $user): bool => $can(BatteryDossierResource::class),
                'count' => fn (User $user): int => $query(BatteryDossierResource::class)->whereNull('finished_at')->count(),
                'url' => fn (): ?string => BatteryDossierResource::getUrl(),
            ],
            'battery_deliveries_pending' => [
                'icon' => Heroicon::OutlinedTruck, 'color' => 'warning',
                'visible' => fn (User $user): bool => $can(BatteryDeliveryResource::class),
                'count' => fn (User $user): int => $query(BatteryDeliveryResource::class)->where('delivered', false)->count(),
                'url' => fn (): ?string => BatteryDeliveryResource::getUrl(),
            ],
            'charging_offline' => [
                'icon' => Heroicon::OutlinedSignalSlash, 'color' => 'danger',
                'visible' => fn (User $user): bool => $can(ChargingStationResource::class),
                'count' => fn (User $user): int => $query(ChargingStationResource::class)->where('online', false)->count(),
                'url' => fn (): ?string => ChargingStationResource::getUrl(),
            ],
            'invoices_outstanding' => [
                'icon' => Heroicon::OutlinedDocumentText, 'color' => 'warning',
                'visible' => fn (User $user): bool => $can(InvoiceResource::class),
                'count' => fn (User $user): int => Invoice::query()->outstanding()->count(),
                'url' => fn (): ?string => InvoiceResource::getUrl(),
            ],
            'expenses_to_validate' => [
                'icon' => Heroicon::OutlinedInboxArrowDown, 'color' => 'warning',
                'visible' => fn (User $user): bool => $can(ExpenseResource::class) && $user->hasPermission('expenses.validate'),
                'count' => fn (User $user): int => $query(ExpenseResource::class)->where('status', ExpenseStatus::Pending)->count(),
                'url' => fn (): ?string => ExpenseResource::getUrl(),
            ],
            'interventions_to_review' => [
                'icon' => Heroicon::OutlinedWrenchScrewdriver, 'color' => 'warning',
                'visible' => fn (User $user): bool => $user->isAdmin() && $can(InterventionReportResource::class),
                'count' => fn (User $user): int => $query(InterventionReportResource::class)->where('status', InterventionReportStatus::Pending)->count(),
                'url' => fn (): ?string => InterventionReportResource::getUrl(panel: 'admin'),
            ],
            'employee_requests' => [
                'icon' => Heroicon::OutlinedPencilSquare, 'color' => 'warning',
                'visible' => fn (User $user): bool => $user->isAdmin() && modules()->employees(),
                'count' => fn (User $user): int => EmployeeChangeRequest::query()->where('status', RequestStatus::Pending)->count()
                    + EmployeeCertificateRequest::query()->where('status', RequestStatus::Pending)->count(),
                'url' => fn (): ?string => EmployeeChangeRequestResource::getUrl(panel: 'admin'),
            ],
            'certificates_expiring' => [
                'icon' => Heroicon::OutlinedAcademicCap, 'color' => 'danger',
                'visible' => fn (User $user): bool => $user->isAdmin() && modules()->employees(),
                'count' => fn (User $user): int => EmployeeCertificate::query()
                    ->whereHas('employee', fn (Builder $query) => $query->employed())
                    ->whereNotNull('expires_on')
                    ->whereDate('expires_on', '<=', today()->addMonths(TrainingMatrix::SOON_MONTHS))
                    ->count(),
                'url' => fn (): ?string => TrainingMatrixPage::getUrl(panel: 'admin'),
            ],
        ];
    }

    /**
     * The counters this user may put on their dashboard: key => label.
     *
     * @return array<string, string>
     */
    public static function available(User $user): array
    {
        return collect(self::all())
            ->filter(fn (array $counter): bool => (bool) rescue(fn (): bool => ($counter['visible'])($user), false, false))
            ->mapWithKeys(fn (array $counter, string $key): array => [$key => __('erp.dashboard.counters.'.$key)])
            ->all();
    }
}
