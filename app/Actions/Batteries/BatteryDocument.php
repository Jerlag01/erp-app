<?php

namespace App\Actions\Batteries;

use App\Models\BatteryDossier;
use App\Support\BatteryTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * The documents of a battery dossier as PDF, laid out like the Excel sheets. The delivery note,
 * measurement report and shipping note are printed on the letterhead background.
 */
class BatteryDocument
{
    /**
     * Document => [view, uses the letterhead background, landscape].
     *
     * @var array<string, array{0: string, 1: bool, 2: bool}>
     */
    public const DOCUMENTS = [
        'delivery' => ['batteries.pdf.delivery', true, false],
        'measurement' => ['batteries.pdf.measurement', true, false],
        'inspection' => ['batteries.pdf.inspection', false, false],
        'floor_plan' => ['batteries.pdf.floor-plan', false, true],
        'single_line' => ['batteries.pdf.single-line', false, true],
        'shipping' => ['batteries.pdf.shipping', true, false],
        'invoice_request' => ['batteries.pdf.invoice-request', false, false],
    ];

    public function render(BatteryDossier $dossier, string $document): string
    {
        [$view, $background, $landscape] = self::DOCUMENTS[$document];

        $dossier->loadMissing(['project.workSite', 'project.category', 'batteryModel.racks', 'cable', 'batteries', 'signatures']);

        return Pdf::loadView($view, [
            'dossier' => $dossier,
            'background' => $background ? BatteryTemplates::backgroundDataUri() : null,
        ])->setPaper('a4', $landscape ? 'landscape' : 'portrait')->setOption('isFontSubsettingEnabled', true)->output();
    }

    public static function filename(BatteryDossier $dossier, string $document): string
    {
        return Str::slug(__('erp.batteries.documents.'.$document).' '.$dossier->reference()).'.pdf';
    }

    /**
     * All documents of the dossier in one ZIP file; returns the path of a temporary file.
     *
     * @param  list<string>  $documents
     */
    public function zip(BatteryDossier $dossier, array $documents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'battery').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($documents as $document) {
            $zip->addFromString(self::filename($dossier, $document), $this->render($dossier, $document));
        }

        $zip->close();

        return $path;
    }
}
