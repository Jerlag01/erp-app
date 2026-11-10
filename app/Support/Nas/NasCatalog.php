<?php

namespace App\Support\Nas;

use App\Models\Asbuilt;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\BaeOrder;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Models\BatteryRoomDossier;
use App\Models\EmployeeCertificateRequest;
use App\Models\EnersysDossier;
use App\Models\Expense;
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
use App\Models\Router;
use App\Models\SignedDocument;
use App\Models\StockItem;
use App\Models\Suggestion;
use App\Models\Vehicle;
use App\Models\WasteEntryLine;
use App\Models\WorkSite;

/**
 * Every other file of the platform for the NAS copy (see NasSync for the layout): the project overview
 * and the documents made for a project (battery dossier, battery room, BAE order, intervention report,
 * as-built ZIP, project archive), the photos of work sites, vehicles, incidents and suggestions, and the
 * files of HR, finance, the knowledge base, IT, equipment, stock, routers and waste.
 */
class NasCatalog
{
    public const SITES = 'Werflocaties';

    public const FINANCE = 'Financieel';

    public const KB = 'Kennisbank';

    public const IT = 'IT';

    public const EQUIPMENT = 'Materieel';

    public const STOCK = 'Magazijn';

    public const ROUTERS = '4G-routers';

    public const WASTE = 'Afval';

    public const ONBOARDING = 'Onboarding';

    public const SUGGESTIONS = 'Suggesties';

    public function __construct(protected NasSync $nas) {}

    /**
     * @return list<string>
     */
    public static function projectFolder(Project $project): array
    {
        return [(string) $project->category?->code, $project->reference];
    }

    /* Projects */

    public function projectOverview(Project $project): void
    {
        $this->nas->put([...self::projectFolder($project), $project->reference.' - projectoverzicht.pdf'], ['pdf' => 'project', 'id' => $project->id]);
    }

    public function batteryDossier(BatteryDossier $dossier): void
    {
        $project = $dossier->project;

        if ($project === null) {
            return;
        }

        $folder = [...self::projectFolder($project), $dossier instanceof MoveDossier ? 'Verplaatsingsdossier' : 'Batterijdossier'];

        foreach ($dossier->availableDocuments() as $document) {
            $this->nas->put([...$folder, __('erp.batteries.documents.'.$document).'.pdf'], ['pdf' => 'battery_document', 'kind' => $dossier::KIND, 'id' => $dossier->id, 'document' => $document]);
        }

        foreach (array_values((array) $dossier->photos) as $index => $path) {
            $this->nas->put([...$folder, "Foto's", sprintf('%02d', $index + 1).'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg')], ['disk' => BatteryDossier::DISK, 'path' => $path]);
        }
    }

    public function batteryRoom(BatteryRoomDossier $room): void
    {
        $project = $room->project;

        if ($project === null) {
            return;
        }

        $folder = [...self::projectFolder($project), 'Batterijlokaal'];
        $this->nas->put([...$folder, 'Leveringsnota.pdf'], ['pdf' => 'battery_room', 'id' => $room->id, 'document' => 'delivery_note']);
        $this->nas->put([...$folder, 'Checklist.pdf'], ['pdf' => 'battery_room', 'id' => $room->id, 'document' => 'checklist']);

        foreach (array_values((array) $room->photos) as $index => $path) {
            $this->nas->put([...$folder, "Foto's", sprintf('%02d', $index + 1).'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg')], ['disk' => BatteryRoomDossier::DISK, 'path' => $path]);
        }

        if (filled($room->btv_file)) {
            $this->nas->put([...$folder, 'BTV', $room->btv_file_name ?: basename($room->btv_file)], ['disk' => BatteryRoomDossier::DISK, 'path' => $room->btv_file]);
        }

        if (filled($room->ventilation_file)) {
            $this->nas->put([...$folder, 'Ventilatie', $room->ventilation_file_name ?: basename($room->ventilation_file)], ['disk' => BatteryRoomDossier::DISK, 'path' => $room->ventilation_file]);
        }
    }

    public function baeOrder(BaeOrder $order): void
    {
        if ($order->project) {
            $this->nas->put([...self::projectFolder($order->project), 'BAE-bestelling.pdf'], ['pdf' => 'bae_order', 'id' => $order->id]);
        }
    }

    public function batteryDelivery(BatteryDelivery $delivery): void
    {
        if ($delivery->project === null) {
            return;
        }

        $folder = [...self::projectFolder($delivery->project), 'Batterijleveringen', 'Levering '.($delivery->order_confirmation ?: $delivery->id)];

        foreach (BatteryDelivery::DOCUMENTS as $kind) {
            if ($path = $delivery->documentPath($kind)) {
                $this->nas->put([...$folder, __('erp.batteries.delivery_documents.'.$kind).' - '.$delivery->documentName($kind)], ['disk' => BatteryDelivery::DISK, 'path' => $path]);
            }
        }
    }

    public function interventionReport(InterventionReport $report): void
    {
        if ($report->project && filled($report->pdf_path)) {
            $this->nas->put([...self::projectFolder($report->project), 'Interventierapport.pdf'], ['disk' => InterventionReport::DISK, 'path' => $report->pdf_path]);
        }
    }

    public function asbuiltZip(Asbuilt $asbuilt): void
    {
        if ($asbuilt->project && filled($asbuilt->zip_path)) {
            $this->nas->put([...self::projectFolder($asbuilt->project), 'As-built', $asbuilt->zipName()], ['disk' => Asbuilt::DISK, 'path' => $asbuilt->zip_path]);
        }
    }

    public function projectArchive(Project $project): void
    {
        if (filled($project->archive_path)) {
            $this->nas->put([...self::projectFolder($project), 'Archief', basename($project->archive_path)], ['disk' => 'local', 'path' => $project->archive_path]);
        }
    }

    /* Sites, vehicles, incidents, suggestions */

    public function workSite(WorkSite $site): void
    {
        foreach (array_values((array) $site->images) as $path) {
            $this->nas->put([self::SITES, $site->cow_code, "Foto's", basename($path)], ['disk' => 'public', 'path' => $path]);
        }
    }

    public function vehiclePhoto(Vehicle $vehicle): void
    {
        if (filled($vehicle->photo)) {
            $this->nas->put([NasSync::FLEET, $vehicle->plate_number, 'Foto.'.(pathinfo($vehicle->photo, PATHINFO_EXTENSION) ?: 'jpg')], ['disk' => 'public', 'path' => $vehicle->photo]);
        }
    }

    public function incidentPhotos(Incident $incident): void
    {
        foreach (array_values((array) $incident->photos) as $index => $path) {
            $this->nas->put([NasSync::INCIDENTS, "Foto's", ($incident->reported_at ?? $incident->created_at)->format('Y-m-d').' - incident '.$incident->id, sprintf('%02d', $index + 1).'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg')],
                ['disk' => Incident::DISK, 'path' => $path]);
        }
    }

    public function suggestion(Suggestion $suggestion): void
    {
        $folder = [self::SUGGESTIONS, ($suggestion->submitted_at ?? $suggestion->created_at)->format('Y-m-d').' - '.$suggestion->id];
        $paths = [...array_values((array) $suggestion->photos), ...$suggestion->responses()->get()->flatMap(fn ($response): array => array_values((array) $response->photos))->all()];

        foreach ($paths as $index => $path) {
            $this->nas->put([...$folder, sprintf('%02d', $index + 1).'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg')], ['disk' => 'local', 'path' => $path]);
        }
    }

    /* People */

    public function certificateRequest(EmployeeCertificateRequest $request): void
    {
        if ($request->employee && filled($request->document)) {
            $this->nas->put([NasSync::EMPLOYEES, $request->employee->fullName(), 'Certificaataanvragen', $request->created_at->format('Y-m-d').' '.($request->document_name ?: basename($request->document))],
                ['disk' => 'local', 'path' => $request->document]);
        }
    }

    public function parentalLeave(ParentalLeave $leave): void
    {
        if ($leave->employee && filled($leave->document)) {
            $this->nas->put([NasSync::EMPLOYEES, $leave->employee->fullName(), 'Ouderschapsverlof', $leave->starts_on?->format('Y-m-d').' '.($leave->document_name ?: basename($leave->document))],
                ['disk' => ParentalLeave::DISK, 'path' => $leave->document]);
        }
    }

    public function signedDocument(SignedDocument $document): void
    {
        $employee = $document->user?->employee;
        $folder = $employee ? [NasSync::EMPLOYEES, $employee->fullName()] : [NasSync::EMPLOYEES, $document->user?->name ?? 'Onbekend'];

        $this->nas->put([...$folder, 'Getekende documenten', basename($document->path)], ['disk' => SignedDocument::DISK, 'path' => $document->path]);
    }

    public function onboardingDocument(OnboardingDocument $document): void
    {
        $this->nas->put([self::ONBOARDING, strtoupper((string) $document->locale).' - '.($document->original_name ?: basename($document->path))], ['disk' => OnboardingDocument::DISK, 'path' => $document->path]);
    }

    /* Finance */

    public function invoice(Invoice $invoice): void
    {
        if (filled($invoice->number)) {
            $this->nas->put([self::FINANCE, 'Verkoopfacturen', $invoice->issue_date?->format('Y') ?? 'Zonder datum', $invoice->number.' - '.($invoice->client?->name ?? '').'.pdf'], ['pdf' => 'invoice', 'id' => $invoice->id]);
        }
    }

    public function expense(Expense $expense): void
    {
        $folder = [self::FINANCE, 'Aankoopfacturen', $expense->invoice_date?->format('Y') ?? 'Zonder datum'];
        $name = trim(($expense->invoice_date?->format('Y-m-d') ?? '').' '.$expense->supplier_name.' '.$expense->supplier_invoice_number);

        if (filled($expense->document)) {
            $this->nas->put([...$folder, $name.' - '.($expense->document_name ?: basename($expense->document))], ['disk' => Expense::DISK, 'path' => $expense->document]);
        }

        if (filled($expense->ubl_path)) {
            $this->nas->put([...$folder, $name.'.xml'], ['disk' => Expense::DISK, 'path' => $expense->ubl_path]);
        }
    }

    public function peppolDocument(PeppolDocument $document): void
    {
        if (filled($document->xml_path)) {
            $this->nas->put([self::FINANCE, 'Peppol', $document->direction === PeppolDocument::IN ? 'Ontvangen' : 'Verstuurd', $document->created_at->format('Y-m-d').' '.$document->id.'.xml'], ['disk' => 'local', 'path' => $document->xml_path]);
        }
    }

    /* Knowledge base, IT, equipment, stock, routers, waste */

    public function kbArticle(KbArticle $article): void
    {
        foreach ((array) $article->attachments as $path) {
            $this->nas->put([self::KB, $article->category?->translate('name', 'nl') ?: '-', $article->translate('title', 'nl'), (string) (((array) $article->attachment_names)[$path] ?? basename($path))], ['disk' => KbArticle::FILE_DISK, 'path' => $path]);
        }
    }

    public function itAsset(ItAsset $asset): void
    {
        if (filled($asset->image)) {
            $this->nas->put([self::IT, 'Hardware', trim($asset->asset_tag.' '.$asset->name), basename($asset->image)], ['disk' => 'public', 'path' => $asset->image]);
        }
    }

    public function itModel(ItModel $model): void
    {
        if (filled($model->image)) {
            $this->nas->put([self::IT, 'Modellen', $model->name, basename($model->image)], ['disk' => 'public', 'path' => $model->image]);
        }
    }

    public function itItem(ItItem $item): void
    {
        if (filled($item->image)) {
            $this->nas->put([self::IT, 'Artikelen', $item->name, basename($item->image)], ['disk' => 'public', 'path' => $item->image]);
        }
    }

    public function asset(Asset $asset): void
    {
        $folder = [self::EQUIPMENT, trim($asset->asset_tag.' '.$asset->name)];

        if (filled($asset->photo)) {
            $this->nas->put([...$folder, 'Foto.'.(pathinfo($asset->photo, PATHINFO_EXTENSION) ?: 'jpg')], ['disk' => 'public', 'path' => $asset->photo]);
        }
    }

    public function assetLog(AssetLog $log): void
    {
        if ($log->asset === null) {
            return;
        }

        foreach (array_values((array) $log->attachments) as $path) {
            $this->nas->put([self::EQUIPMENT, trim($log->asset->asset_tag.' '.$log->asset->name), 'Logboek', trim($log->date?->format('Y-m-d').' '.$log->title), basename($path)], ['disk' => 'public', 'path' => $path]);
        }
    }

    public function stockItem(StockItem $item): void
    {
        if (filled($item->image)) {
            $this->nas->put([self::STOCK, (string) $item->name, basename($item->image)], ['disk' => 'public', 'path' => $item->image]);
        }
    }

    public function router(Router $router): void
    {
        if (filled($router->vpn_file)) {
            $this->nas->put([self::ROUTERS, trim(($router->workSite?->cow_code ?? '').' '.$router->serial_number), $router->vpn_file_name ?: basename($router->vpn_file)], ['disk' => Router::DISK, 'path' => $router->vpn_file]);
        }
    }

    public function wasteLine(WasteEntryLine $line): void
    {
        if (filled($line->pdf_path) && $line->entry) {
            $this->nas->put([self::WASTE, $line->entry->date?->format('Y') ?? 'Zonder datum', trim($line->entry->date?->format('Y-m-d').' '.($line->pdf_name ?: basename($line->pdf_path)))], ['disk' => WasteEntryLine::DISK, 'path' => $line->pdf_path]);
        }
    }

    /**
     * Everything of these kinds that exists now (for NasSync::queueAll).
     */
    public function queueEverything(): void
    {
        Project::query()->with('category')->lazyById()->each(function (Project $project): void {
            $this->projectOverview($project);
            $this->projectArchive($project);
        });
        foreach ([BatteryDossier::class, EnersysDossier::class, MoveDossier::class] as $class) {
            $class::query()->with('project.category')->lazyById()->each(fn (BatteryDossier $dossier) => $this->batteryDossier($dossier));
        }
        BatteryRoomDossier::query()->with('project.category')->lazyById()->each(fn (BatteryRoomDossier $room) => $this->batteryRoom($room));
        BaeOrder::query()->with('project.category')->lazyById()->each(fn (BaeOrder $order) => $this->baeOrder($order));
        BatteryDelivery::query()->with('project.category')->lazyById()->each(fn (BatteryDelivery $delivery) => $this->batteryDelivery($delivery));
        InterventionReport::query()->whereNotNull('pdf_path')->with('project.category')->lazyById()->each(fn (InterventionReport $report) => $this->interventionReport($report));
        Asbuilt::query()->whereNotNull('zip_path')->with('project.category')->lazyById()->each(fn (Asbuilt $asbuilt) => $this->asbuiltZip($asbuilt));
        WorkSite::query()->whereNotNull('images')->lazyById()->each(fn (WorkSite $site) => $this->workSite($site));
        Vehicle::query()->whereNotNull('photo')->lazyById()->each(fn (Vehicle $vehicle) => $this->vehiclePhoto($vehicle));
        Incident::query()->whereNotNull('photos')->lazyById()->each(fn (Incident $incident) => $this->incidentPhotos($incident));
        Suggestion::query()->lazyById()->each(fn (Suggestion $suggestion) => $this->suggestion($suggestion));
        EmployeeCertificateRequest::query()->whereNotNull('document')->with('employee')->lazyById()->each(fn (EmployeeCertificateRequest $request) => $this->certificateRequest($request));
        ParentalLeave::query()->whereNotNull('document')->with('employee')->lazyById()->each(fn (ParentalLeave $leave) => $this->parentalLeave($leave));
        SignedDocument::query()->with('user.employee')->lazyById()->each(fn (SignedDocument $document) => $this->signedDocument($document));
        OnboardingDocument::query()->lazyById()->each(fn (OnboardingDocument $document) => $this->onboardingDocument($document));
        Invoice::query()->whereNotNull('number')->with('client')->lazyById()->each(fn (Invoice $invoice) => $this->invoice($invoice));
        Expense::query()->lazyById()->each(fn (Expense $expense) => $this->expense($expense));
        PeppolDocument::query()->whereNotNull('xml_path')->lazyById()->each(fn (PeppolDocument $document) => $this->peppolDocument($document));
        KbArticle::query()->with('category')->lazyById()->each(fn (KbArticle $article) => $this->kbArticle($article));
        ItAsset::query()->whereNotNull('image')->lazyById()->each(fn (ItAsset $asset) => $this->itAsset($asset));
        ItModel::query()->whereNotNull('image')->lazyById()->each(fn (ItModel $model) => $this->itModel($model));
        ItItem::query()->whereNotNull('image')->lazyById()->each(fn (ItItem $item) => $this->itItem($item));
        Asset::query()->lazyById()->each(fn (Asset $asset) => $this->asset($asset));
        AssetLog::query()->whereNotNull('attachments')->with('asset')->lazyById()->each(fn (AssetLog $log) => $this->assetLog($log));
        StockItem::query()->whereNotNull('image')->lazyById()->each(fn (StockItem $item) => $this->stockItem($item));
        Router::query()->whereNotNull('vpn_file')->with('workSite')->lazyById()->each(fn (Router $router) => $this->router($router));
        WasteEntryLine::query()->whereNotNull('pdf_path')->with('entry')->lazyById()->each(fn (WasteEntryLine $line) => $this->wasteLine($line));
    }
}
