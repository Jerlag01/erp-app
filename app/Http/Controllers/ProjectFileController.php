<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a project file (the latest or a given version) from the private disk. Pictures a browser
 * can show open inline; everything else is downloaded under its original name. Offers and
 * invoices need the finance permission or being the project leader.
 */
class ProjectFileController
{
    public function __invoke(Project $project, ProjectFile $file, ?int $version = null): StreamedResponse
    {
        abort_unless($file->project_id === $project->id, 404);
        abort_unless(Gate::allows('view', $project), 403);
        abort_if($file->sectionEnum()->isFinancial() && Gate::denies('viewFinance', $project), 403);

        $stored = $version === null
            ? $file->latestVersion
            : $file->versions()->where('version', $version)->first();

        abort_if($stored === null || ! Storage::disk(ProjectFile::DISK)->exists($stored->path), 404);

        return Storage::disk(ProjectFile::DISK)->response(
            $stored->path,
            $stored->original_name,
            [
                'Content-Type' => $stored->isPreviewableImage() ? $stored->mime_type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'",
                'Cache-Control' => 'private, max-age=300',
            ],
            $stored->isPreviewableImage() ? 'inline' : 'attachment',
        );
    }
}
