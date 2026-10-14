<?php

namespace App\Support;

use App\Enums\CertificateType;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * "Opleidingen & medisch onderzoek": every employee in service against their certificates and the
 * medical check-up, like the Excel overview. A certificate with an expiry date shows "valid until" in
 * green, orange within three months and red once expired; one without shows the date it was obtained.
 */
class TrainingMatrix
{
    public const SOON_MONTHS = 3;

    /**
     * The columns in the order of the Excel; the other certificate types follow when anybody has one.
     */
    public const COLUMNS = [
        CertificateType::VcaVol, CertificateType::Vca, CertificateType::PreventionAdvisor3, CertificateType::Nuclear,
        CertificateType::Proximus, CertificateType::ChargingStations, CertificateType::Ba4, CertificateType::Ba5,
        CertificateType::FirstAidFire, CertificateType::FirstAid, CertificateType::ScissorLift, CertificateType::AerialWorkPlatform,
        CertificateType::Forklift, CertificateType::Ladders, CertificateType::LoaderCrane, CertificateType::DriverCode95,
    ];

    /**
     * Never shown in the overview.
     */
    public const LEFT_OUT = [CertificateType::SchoolDegree];

    /**
     * @return array{columns: list<array{key: string, label: string, until: bool}>, rows: list<array<string, mixed>>, expired: int, soon: int}
     */
    public function build(string $search = '', bool $onlyProblems = false): array
    {
        $employees = Employee::query()->employed()->with(['user', 'certificates', 'latestMedicalCheck'])->orderBy('last_name')->orderBy('first_name')->get();
        $columns = $this->columns($employees);
        $rows = $employees->map(fn (Employee $employee): array => $this->row($employee, $columns))->all();

        $states = collect($rows)->flatMap(fn (array $row): array => array_column($row['cells'], 'state'));
        $search = mb_strtolower(trim($search));

        $rows = array_values(array_filter($rows, fn (array $row): bool => ($search === '' || str_contains(mb_strtolower($row['name'].' '.$row['function']), $search))
            && (! $onlyProblems || $row['problems'] > 0)));

        return [
            'columns' => $columns,
            'rows' => $rows,
            'expired' => $states->filter(fn (?string $state): bool => $state === 'expired')->count(),
            'soon' => $states->filter(fn (?string $state): bool => $state === 'soon')->count(),
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{key: string, label: string, until: bool}>
     */
    protected function columns(Collection $employees): array
    {
        $held = $employees->flatMap(fn (Employee $employee) => $employee->certificates->pluck('type'))->unique(fn (CertificateType $type): string => $type->value);
        $extra = array_filter(CertificateType::cases(), fn (CertificateType $type): bool => ! in_array($type, self::COLUMNS, true)
            && ! in_array($type, self::LEFT_OUT, true)
            && $held->contains($type));

        $columns = array_map(fn (CertificateType $type): array => [
            'key' => $type->value,
            'label' => __('erp.training_matrix.columns.'.$type->value) !== 'erp.training_matrix.columns.'.$type->value ? __('erp.training_matrix.columns.'.$type->value) : $type->getLabel(),
            'until' => self::showsValidity($type),
        ], [...self::COLUMNS, ...array_values($extra)]);

        $columns[] = ['key' => 'medical', 'label' => __('erp.training_matrix.columns.medical'), 'until' => true];

        return $columns;
    }

    /**
     * Columns with a validity ("geldig tot") or the date obtained ("vanaf").
     */
    public static function showsValidity(CertificateType $type): bool
    {
        return ! in_array($type, [CertificateType::ChargingStations, CertificateType::Ba4, CertificateType::Ba5, CertificateType::FirstAidFire, CertificateType::LoaderCrane, CertificateType::DrivingLicence], true);
    }

    /**
     * @param  list<array{key: string, label: string, until: bool}>  $columns
     * @return array<string, mixed>
     */
    protected function row(Employee $employee, array $columns): array
    {
        $certificates = $employee->certificates->keyBy(fn (EmployeeCertificate $certificate): string => $certificate->type->value);
        $cells = [];

        foreach ($columns as $column) {
            if ($column['key'] === 'medical') {
                $due = $employee->latestMedicalCheck ? $employee->nextMedicalDue() : null;
                $cells['medical'] = $due ? $this->dated($due) : ['date' => null, 'text' => __('erp.training_matrix.missing'), 'state' => 'expired'];

                continue;
            }

            $certificate = $certificates->get($column['key']);
            $cells[$column['key']] = match (true) {
                $certificate === null => ['date' => null, 'text' => '', 'state' => null],
                $certificate->expires_on !== null => $this->dated($certificate->expires_on),
                default => ['date' => $certificate->obtained_on, 'text' => $certificate->obtained_on?->format('d/m/Y') ?? '✓', 'state' => 'since'],
            };
        }

        return [
            'employee' => $employee,
            'name' => $employee->fullName(),
            'function' => (string) $employee->user?->job_title,
            'mentor' => $employee->is_mentor,
            'cells' => $cells,
            'problems' => count(array_filter($cells, fn (array $cell): bool => in_array($cell['state'], ['expired', 'soon'], true))),
        ];
    }

    /**
     * @return array{date: CarbonInterface, text: string, state: string}
     */
    protected function dated(CarbonInterface $date): array
    {
        return [
            'date' => $date,
            'text' => $date->format('d/m/Y'),
            'state' => match (true) {
                $date->lt(today()) => 'expired',
                $date->lte(today()->addMonths(self::SOON_MONTHS)) => 'soon',
                default => 'valid',
            },
        ];
    }
}
