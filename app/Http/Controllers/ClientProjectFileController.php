<?php

namespace App\Http\Controllers;

use App\Models\ClientUser;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\ClientPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file of a closed battery room (609) for a client portal user who sees that project: the latest
 * version, never the invoices, electrical schematics or plans. Pictures a browser can show open inline.
 */
class ClientProjectFileController
{
    public function __invoke(Project $project, ProjectFile $file): StreamedResponse
    {
        $user = Auth::guard(ClientPortal::GUARD)->user();
        abort_unless($user instanceof ClientUser && $user->is_active && $user->may(ClientPortal::PROJECTS) && modules()->projects(), 403);
        abort_unless($file->project_id === $project->id && $user->restrictProjects(Project::query()->whereKey($project->id))->exists(), 404);
        abort_unless(ClientPortal::sharedFiles($project)->contains($file), 404);

        $stored = $file->latestVersion;
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
