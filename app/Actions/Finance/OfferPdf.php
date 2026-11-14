<?php

namespace App\Actions\Finance;

use App\Enums\ProjectFileSection;
use App\Models\Offer;
use App\Models\ProjectFile;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The PDF of an offer (layout of the Proximus offer template), in the client's language, and its copy
 * in the project's offers (a new version each time it is saved again).
 */
class OfferPdf
{
    public function render(Offer $offer): string
    {
        $offer->loadMissing(['client.country', 'lines.category', 'project.workSite']);
        $previous = app()->getLocale();
        app()->setLocale($offer->client?->locale ?: config('app.locale'));

        try {
            return Pdf::loadView('finance.offer-pdf', ['offer' => $offer])->setPaper('a4')->output();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * Saves the PDF in the project's offers, as a new version of the same file when it is there already.
     */
    public function saveToProject(Offer $offer, ?User $user = null): ?ProjectFile
    {
        $project = $offer->project;

        if ($project === null) {
            return null;
        }

        $name = self::filename($offer);
        $path = ProjectFile::directoryFor($project, ProjectFileSection::Offers).'/'.Str::uuid().'.pdf';
        Storage::disk(ProjectFile::DISK)->put($path, $this->render($offer));
        $existing = $project->files()->where('section', ProjectFileSection::Offers->value)->where('name', $name)->first();

        return ProjectFile::store($project, ProjectFileSection::Offers, $path, $name, $user, $existing);
    }

    public static function filename(Offer $offer): string
    {
        return trim(implode(' ', array_filter([$offer->project?->workSite?->cow_code, __('erp.finance.offers.pdf_title', [], 'nl'), $offer->number]))).'.pdf';
    }
}
