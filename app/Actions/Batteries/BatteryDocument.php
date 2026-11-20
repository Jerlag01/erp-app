<?php

namespace App\Actions\Batteries;

use App\Models\BatteryDossier;
use App\Models\MoveDossier;
use App\Support\BatteryTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * The documents of a battery dossier as PDF, laid out like the Excel sheets. The delivery note,
 * measurement report and shipping note are printed on the letterhead background. Which documents a
 * dossier has depends on its kind (BatteryDossier::documents(): BAE, Enersys or a move).
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

        if ($dossier instanceof MoveDossier) {
            $dossier->loadMissing(['fromSite', 'toSite.area']);
        }

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
     * All documents of the dossier in one ZIP file, with the photos of the works in "fotos/"; returns
     * the path of a temporary file.
     *
     * @param  list<string>  $documents
     */
    public function zip(BatteryDossier $dossier, array $documents, bool $withPhotos = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'battery').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($documents as $document) {
            $zip->addFromString(self::filename($dossier, $document), $this->render($dossier, $document));
        }

        if ($withPhotos) {
            $this->addPhotos($zip, $dossier, 'fotos/');
        }

        $zip->close();

        return $path;
    }

    /**
     * Only the photos of the works in a ZIP file; returns the path of a temporary file.
     */
    public function photosZip(BatteryDossier $dossier): string
    {
        $path = tempnam(sys_get_temp_dir(), 'battery-photos').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->addPhotos($zip, $dossier);
        $zip->close();

        return $path;
    }

    /**
     * The photos still on the disk, named after the dossier in their order: "batterijdossier-…-foto-01.jpg" => path.
     *
     * @return array<string, string>
     */
    public static function photos(BatteryDossier $dossier): array
    {
        $disk = Storage::disk($dossier::DISK);
        $photos = [];
        $number = 0;

        foreach (array_values((array) $dossier->photos) as $path) {
            if (! is_string($path) || ! $disk->exists($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
            $photos[Str::slug($dossier->reference()).'-foto-'.str_pad((string) ++$number, 2, '0', STR_PAD_LEFT).'.'.$extension] = $path;
        }

        return $photos;
    }

    public static function photosFilename(BatteryDossier $dossier): string
    {
        return Str::slug(__('erp.batteries.photos_zip').' '.$dossier->reference()).'.zip';
    }

    /**
     * Adds the photos to an open ZIP under the prefix; returns how many.
     */
    public function addPhotos(ZipArchive $zip, BatteryDossier $dossier, string $prefix = ''): int
    {
        $disk = Storage::disk($dossier::DISK);
        $photos = self::photos($dossier);

        foreach ($photos as $name => $path) {
            $zip->addFromString($prefix.$name, (string) $disk->get($path));
        }

        return count($photos);
    }
}
