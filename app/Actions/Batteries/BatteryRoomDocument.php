<?php

namespace App\Actions\Batteries;

use App\Models\BatteryRoomDossier;
use App\Support\BatteryTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The documents of a battery room (609) as PDF, in the language of the region of the COW site: the
 * delivery note in portrait on the letterhead of the battery documents, the checklist in landscape
 * without letterhead.
 */
class BatteryRoomDocument
{
    /**
     * Document => [view, on the letterhead, landscape].
     *
     * @var array<string, array{0: string, 1: bool, 2: bool}>
     */
    public const DOCUMENTS = [
        'delivery_note' => ['battery-rooms.pdf.delivery-note', true, false],
        'checklist' => ['battery-rooms.pdf.checklist', false, true],
    ];

    public function render(BatteryRoomDossier $dossier, string $document): string
    {
        [, , $landscape] = self::DOCUMENTS[$document];

        return Pdf::loadHTML($this->html($dossier, $document))
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function html(BatteryRoomDossier $dossier, string $document): string
    {
        [$view, $background, $landscape] = self::DOCUMENTS[$document];
        $dossier->loadMissing(['project.workSite.area', 'project.category']);

        $previous = [app()->getLocale(), Carbon::getLocale()];
        app()->setLocale($dossier->documentLocale());
        Carbon::setLocale($dossier->documentLocale());

        try {
            return view($view, [
                'dossier' => $dossier,
                'background' => $background ? BatteryTemplates::backgroundDataUri() : null,
                'landscape' => $landscape,
            ])->render();
        } finally {
            app()->setLocale($previous[0]);
            Carbon::setLocale($previous[1]);
        }
    }

    public static function filename(BatteryRoomDossier $dossier, string $document): string
    {
        return Str::slug(($document === 'checklist' ? 'checklist' : 'leveringsnota').' '.$dossier->reference()).'.pdf';
    }
}
