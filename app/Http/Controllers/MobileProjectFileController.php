<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\V1\ProjectController;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The latest version of a project file through a short-lived signed link, made by the mobile app for an
 * employee whose team executes the project (the phone's browser has no session).
 */
class MobileProjectFileController
{
    public function __invoke(Project $project, ProjectFile $file): StreamedResponse
    {
        abort_unless($file->project_id === $project->id, 404);
        abort_unless(in_array($file->sectionEnum(), ProjectController::SECTIONS, true), 404);

        $stored = $file->latestVersion;
        $disk = Storage::disk(ProjectFile::DISK);
        abort_if($stored === null || ! $disk->exists($stored->path), 404);

        return $disk->response(
            $stored->path,
            $stored->original_name,
            [
                'Content-Type' => $stored->isPreviewableImage() || $stored->mime_type === 'application/pdf' ? $stored->mime_type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'",
                'Cache-Control' => 'private, no-store',
            ],
            $stored->isPreviewableImage() || $stored->mime_type === 'application/pdf' ? 'inline' : 'attachment',
        );
    }
}
