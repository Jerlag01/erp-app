<?php

namespace App\Actions\Batteries;

use App\Models\BatteryDossier;
use App\Support\BatteryTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * The documents of a battery dossier as PDF, laid out like the Excel sheets. The delivery note,
 * measurement report and shipping note are printed on the letterhead background. Which documents a
 * dossier has depends on its kind (BatteryDossier::documents(): BAE or Enersys).
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

    /**
     * The PDF, in the language of the region of the COW site (internal documents in Dutch).
     */
    public function render(BatteryDossier $dossier, string $document): string
    {
        [, , $landscape] = $dossier::documents()[$document];

        return Pdf::loadHTML($this->html($dossier, $document))
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * The HTML of a document, rendered in its language.
     */
    public function html(BatteryDossier $dossier, string $document): string
    {
        [$view, $background] = $dossier::documents()[$document];

        $dossier->loadMissing(['project.workSite.area', 'project.category', 'batteryModel.racks', 'cable', 'batteries', 'signatures']);

        return $this->inLocale($this->locale($dossier, $document), fn (): string => view($view, [
            'dossier' => $dossier,
            'background' => $background ? BatteryTemplates::backgroundDataUri() : null,
        ])->render());
    }

    public function locale(BatteryDossier $dossier, string $document): string
    {
        return in_array($document, $dossier::INTERNAL_DOCUMENTS, true) ? 'nl' : $dossier->documentLocale();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function inLocale(string $locale, callable $callback): mixed
    {
        $previous = [app()->getLocale(), Carbon::getLocale()];
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous[0]);
            Carbon::setLocale($previous[1]);
        }
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
