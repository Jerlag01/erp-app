<?php

namespace App\Support\Nas;

use App\Models\Absence;
use App\Models\Asbuilt;
use App\Models\AsbuiltFile;
use App\Models\AsbuiltLibraryFile;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\BaeOrder;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Models\BatteryDossierSignature;
use App\Models\BatteryRoomDossier;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\EmployeeCertificateRequest;
use App\Models\EmployeeMedicalCheck;
use App\Models\EnersysDossier;
use App\Models\Expense;
use App\Models\FuelPass;
use App\Models\GpwRequest;
use App\Models\Incident;
use App\Models\InterventionReport;
use App\Models\Invoice;
use App\Models\ItAsset;
use App\Models\ItItem;
use App\Models\ItModel;
use App\Models\KbArticle;
use App\Models\MoveDossier;
use App\Models\OnboardingDocument;
use App\Models\ParentalLeave;
use App\Models\PeppolDocument;
use App\Models\Project;
use App\Models\ProjectFileVersion;
use App\Models\Router;
use App\Models\SignedDocument;
use App\Models\StockItem;
use App\Models\Suggestion;
use App\Models\SuggestionResponse;
use App\Models\TimesheetWeek;
use App\Models\ToolboxMeeting;
use App\Models\ToolboxMeetingParticipant;
use App\Models\Vehicle;
use App\Models\WasteEntryLine;
use App\Models\WorkSite;
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

        self::catalog();
    }

    /**
     * Everything else (see NasCatalog): the project overview and the documents made for a project, photos,
     * and the files of HR, finance, the knowledge base, IT, equipment, stock, routers and waste.
     */
    protected static function catalog(): void
    {
        $catalog = fn (): NasCatalog => app(NasCatalog::class);

        // The overview shows the project, its files, GPW requests and deliveries.
        self::on(Project::class, 'saved', function (NasSync $nas, Project $project) use ($catalog): void {
            $catalog()->projectOverview($project);

            if ($project->wasChanged('archive_path')) {
                $catalog()->projectArchive($project);
            }
        });
        self::on(ProjectFileVersion::class, 'created', fn (NasSync $nas, ProjectFileVersion $version) => $version->file?->project && $catalog()->projectOverview($version->file->project));
        self::on(GpwRequest::class, 'saved', fn (NasSync $nas, GpwRequest $gpw) => $gpw->project && $catalog()->projectOverview($gpw->project));
        self::on(BatteryDelivery::class, 'saved', function (NasSync $nas, BatteryDelivery $delivery) use ($catalog): void {
            $catalog()->batteryDelivery($delivery);

            if ($delivery->project) {
                $catalog()->projectOverview($delivery->project);
            }
        });

        foreach ([BatteryDossier::class, EnersysDossier::class, MoveDossier::class] as $class) {
            self::on($class, 'saved', fn (NasSync $nas, BatteryDossier $dossier) => $catalog()->batteryDossier($dossier));
        }
        // A signature changes the documents of the dossier.
        self::on(BatteryDossierSignature::class, 'saved', function (NasSync $nas, BatteryDossierSignature $signature) use ($catalog): void {
            $dossier = BatteryDossier::query()->withoutGlobalScope('kind')->find($signature->battery_dossier_id);
            $class = match ($dossier?->kind) {
                EnersysDossier::KIND => EnersysDossier::class, MoveDossier::KIND => MoveDossier::class, default => BatteryDossier::class
            };

            if ($dossier && ($typed = $class::query()->find($dossier->id))) {
                $catalog()->batteryDossier($typed);
            }
        });
        self::on(BatteryRoomDossier::class, 'saved', fn (NasSync $nas, BatteryRoomDossier $room) => $catalog()->batteryRoom($room));
        self::on(BaeOrder::class, 'saved', fn (NasSync $nas, BaeOrder $order) => $catalog()->baeOrder($order));
        self::on(InterventionReport::class, 'saved', fn (NasSync $nas, InterventionReport $report) => $report->wasChanged('pdf_path') && $catalog()->interventionReport($report));
        self::on(Asbuilt::class, 'saved', fn (NasSync $nas, Asbuilt $asbuilt) => $asbuilt->wasChanged('zip_path') && $catalog()->asbuiltZip($asbuilt));

        self::on(WorkSite::class, 'saved', fn (NasSync $nas, WorkSite $site) => ($site->wasChanged('images') || $site->wasRecentlyCreated) && $catalog()->workSite($site));
        self::on(Vehicle::class, 'saved', fn (NasSync $nas, Vehicle $vehicle) => $catalog()->vehiclePhoto($vehicle));
        self::on(Incident::class, 'saved', fn (NasSync $nas, Incident $incident) => $catalog()->incidentPhotos($incident));
        self::on(Suggestion::class, 'saved', fn (NasSync $nas, Suggestion $suggestion) => $catalog()->suggestion($suggestion));
        self::on(SuggestionResponse::class, 'saved', fn (NasSync $nas, SuggestionResponse $response) => $response->suggestion && $catalog()->suggestion($response->suggestion));

        self::on(EmployeeCertificateRequest::class, 'saved', fn (NasSync $nas, EmployeeCertificateRequest $request) => $catalog()->certificateRequest($request));
        self::on(ParentalLeave::class, 'saved', fn (NasSync $nas, ParentalLeave $leave) => $catalog()->parentalLeave($leave));
        self::on(SignedDocument::class, 'saved', fn (NasSync $nas, SignedDocument $document) => $catalog()->signedDocument($document));
        self::on(OnboardingDocument::class, 'saved', fn (NasSync $nas, OnboardingDocument $document) => $catalog()->onboardingDocument($document));

        self::on(Invoice::class, 'saved', fn (NasSync $nas, Invoice $invoice) => $catalog()->invoice($invoice));
        self::on(Expense::class, 'saved', fn (NasSync $nas, Expense $expense) => $catalog()->expense($expense));
        self::on(PeppolDocument::class, 'saved', fn (NasSync $nas, PeppolDocument $document) => $catalog()->peppolDocument($document));

        self::on(KbArticle::class, 'saved', fn (NasSync $nas, KbArticle $article) => $catalog()->kbArticle($article));
        self::on(ItAsset::class, 'saved', fn (NasSync $nas, ItAsset $asset) => $catalog()->itAsset($asset));
        self::on(ItModel::class, 'saved', fn (NasSync $nas, ItModel $model) => $catalog()->itModel($model));
        self::on(ItItem::class, 'saved', fn (NasSync $nas, ItItem $item) => $catalog()->itItem($item));
        self::on(Asset::class, 'saved', fn (NasSync $nas, Asset $asset) => $catalog()->asset($asset));
        self::on(AssetLog::class, 'saved', fn (NasSync $nas, AssetLog $log) => $catalog()->assetLog($log));
        self::on(StockItem::class, 'saved', fn (NasSync $nas, StockItem $item) => $catalog()->stockItem($item));
        self::on(Router::class, 'saved', fn (NasSync $nas, Router $router) => $catalog()->router($router));
        self::on(WasteEntryLine::class, 'saved', fn (NasSync $nas, WasteEntryLine $line) => $catalog()->wasteLine($line));
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
                NasSync::inDutch(fn () => $handler($nas, $record));
            }
        });
    }
}
