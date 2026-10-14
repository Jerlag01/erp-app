<?php

namespace App\Http\Controllers;

use App\Actions\Projects\CloseProject;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The ZIP with all files of a closed (archived) project.
 */
class ProjectArchiveController
{
    public function __invoke(Project $project): StreamedResponse
    {
        abort_unless(Gate::allows('downloadArchive', $project), 403);
        abort_unless(Storage::disk(CloseProject::DISK)->exists((string) $project->archive_path), 404);

        return Storage::disk(CloseProject::DISK)->download($project->archive_path, Str::slug($project->reference).'.zip');
    }
}
