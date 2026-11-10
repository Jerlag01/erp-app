<?php

namespace App\Http\Controllers;

use App\Actions\Batteries\BatteryDocument;
use App\Models\BatteryDossier;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\BatteryReports;
use App\Support\ClientPortal;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The documents of a finished battery replacement (604) for a client portal user with "battery_reports"
 * who sees the project (see BatteryReports): the dossier's PDFs, its photos and the waste report.
 */
class ClientBatteryReportController
{
    /**
     * Measurement report, delivery note, visual inspection or invoice request, made from the dossier.
     */
    public function document(Project $project, string $document): Response
    {
        $dossier = $this->dossier($project);
        abort_unless(in_array($document, BatteryReports::DOCUMENTS, true), 404);

        return response(app(BatteryDocument::class)->render($dossier, $document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.BatteryDocument::filename($dossier, $document).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function photo(Project $project, int $index): StreamedResponse
    {
        $path = BatteryReports::photos($this->dossier($project))[$index] ?? null;
        abort_if($path === null, 404);

        return Storage::disk(BatteryDossier::DISK)->response($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function wasteReport(Project $project, ProjectFile $file): StreamedResponse
    {
        $this->authorize($project);
        abort_unless(BatteryReports::uploaded($project)->contains($file), 404);

        $stored = $file->latestVersion;
        abort_if($stored === null || ! Storage::disk(ProjectFile::DISK)->exists($stored->path), 404);

        return Storage::disk(ProjectFile::DISK)->response($stored->path, $stored->original_name, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    protected function authorize(Project $project): void
    {
        $user = Auth::guard(ClientPortal::GUARD)->user();
        abort_unless($user instanceof ClientUser && BatteryReports::shows($user, $project), 404);
    }

    protected function dossier(Project $project): BatteryDossier
    {
        $this->authorize($project);

        return BatteryReports::dossier($project) ?? abort(404);
    }
}
