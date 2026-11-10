<?php

namespace App\Support\Nas;

use App\Models\Absence;
use App\Models\AsbuiltFile;
use App\Models\AsbuiltLibraryFile;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\EmployeeMedicalCheck;
use App\Models\FuelPass;
use App\Models\Incident;
use App\Models\ProjectFileVersion;
use App\Models\TimesheetWeek;
use App\Models\ToolboxMeeting;
use App\Models\ToolboxMeetingParticipant;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * What puts a file in the NAS outbox (see NasSync): a new upload, or a change to what one of the PDFs
 * shows. Nothing happens while the NAS link is off.
 *
 * The listeners never return a value: a model event listener that returns false stops the listeners
 * after it (e.g. the Outlook calendar sync).
 */
class NasTriggers
{
    public static function register(): void
    {
        self::on(ProjectFileVersion::class, 'created', fn (NasSync $nas, ProjectFileVersion $version) => $nas->projectFile($version));
        self::on(AsbuiltFile::class, 'created', fn (NasSync $nas, AsbuiltFile $file) => $nas->asbuiltFile($file));
        // A standard file replaced: in every as-built that uses it.
        self::on(AsbuiltLibraryFile::class, 'updated', function (NasSync $nas, AsbuiltLibraryFile $file): void {
            if ($file->wasChanged('path')) {
                $file->usages()->with('asbuilt.project.category')->get()->each(fn (AsbuiltFile $usage) => $nas->asbuiltFile($usage));
            }
        });

        self::on(Employee::class, 'saved', fn (NasSync $nas, Employee $employee) => $nas->employee($employee->loadMissing(['certificates', 'medicalChecks'])));
        self::on(EmployeeCertificate::class, 'saved', function (NasSync $nas, EmployeeCertificate $certificate): void {
            if ($certificate->employee) {
                $nas->certificate($certificate);
                // The certificates are listed in the personal details too.
                $nas->put([NasSync::EMPLOYEES, $certificate->employee->fullName(), $certificate->employee->fullName().' - persoonsgegevens.pdf'], ['pdf' => 'employee', 'id' => $certificate->employee_id]);
            }
        });
        self::on(EmployeeMedicalCheck::class, 'saved', function (NasSync $nas, EmployeeMedicalCheck $check): void {
            if ($check->wasChanged('documents') || $check->wasRecentlyCreated) {
                $nas->medicalCheck($check);
            }
        });
        self::on(TimesheetWeek::class, 'saved', function (NasSync $nas, TimesheetWeek $week): void {
            if (filled($week->pdf_path) && ($week->wasChanged('pdf_path') || $week->wasRecentlyCreated)) {
                $nas->timesheetWeek($week);
            }
        });
        self::on(Absence::class, 'saved', function (NasSync $nas, Absence $absence): void {
            if (filled($absence->document) && ($absence->wasChanged('document') || $absence->wasRecentlyCreated)) {
                $nas->absence($absence);
            }
        });

        self::on(Vehicle::class, 'saved', fn (NasSync $nas, Vehicle $vehicle) => $nas->vehicle($vehicle));
        foreach (['saved', 'deleted'] as $event) {
            self::on(FuelPass::class, $event, function (NasSync $nas, FuelPass $pass): void {
                if ($pass->vehicle) {
                    $nas->vehicle($pass->vehicle);
                }
            });
        }

        self::on(Incident::class, 'saved', fn (NasSync $nas, Incident $incident) => $nas->incident($incident));

        self::on(ToolboxMeeting::class, 'saved', fn (NasSync $nas, ToolboxMeeting $meeting) => $nas->toolbox($meeting));
        // A signature: only the attendance list changes.
        self::on(ToolboxMeetingParticipant::class, 'saved', function (NasSync $nas, ToolboxMeetingParticipant $participant): void {
            if (($participant->wasChanged('signature_path') || $participant->wasRecentlyCreated) && $participant->meeting) {
                $nas->toolbox($participant->meeting, withContent: false);
            }
        });
    }

    /**
     * Listens to a model event while the NAS link is on; the listener itself returns nothing.
     *
     * @param  class-string<Model>  $model
     */
    protected static function on(string $model, string $event, callable $handler): void
    {
        $model::{$event}(function ($record) use ($handler): void {
            $nas = app(NasSync::class);

            if ($nas->enabled()) {
                $handler($nas, $record);
            }
        });
    }
}
