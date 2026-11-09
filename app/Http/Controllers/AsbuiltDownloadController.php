<?php

namespace App\Http\Controllers;

use App\Models\Asbuilt;
use App\Models\AsbuiltFile;
use App\Models\AsbuiltLibraryFile;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\WorkSite;
use App\Support\Asbuilts;
use App\Support\ClientPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads of as-built files and ZIPs: for employees who may open the as-built (the overview
 * permission, or being on the project), and for client portal users with the as-built permission on
 * a project they see. Files of any format are always sent as a download, never shown inline.
 */
class AsbuiltDownloadController
{
    public function file(AsbuiltFile $file): StreamedResponse
    {
        abort_unless($file->asbuilt && Gate::allows('view', $file->asbuilt), 403);

        return $this->send($file->storagePath(), $file->fileName());
    }

    public function library(AsbuiltLibraryFile $file): StreamedResponse
    {
        abort_unless(Gate::allows('view', $file), 403);

        return $this->send($file->path, $file->original_name);
    }

    public function zip(Asbuilt $asbuilt): StreamedResponse
    {
        abort_unless(Gate::allows('view', $asbuilt) && $asbuilt->isClosed(), 403);

        return $this->send($asbuilt->zip_path, $asbuilt->zipName());
    }

    /**
     * The client's ZIP: the final one once the as-built is closed, before that one made on the spot from
     * what is in it now, with the same folders.
     */
    public function clientZip(Asbuilt $asbuilt, Asbuilts $asbuilts): StreamedResponse|BinaryFileResponse
    {
        abort_unless($this->clientMay($asbuilt), 403);

        if ($asbuilt->isClosed() && filled($asbuilt->zip_path) && Storage::disk(Asbuilt::DISK)->exists($asbuilt->zip_path)) {
            return $this->send($asbuilt->zip_path, $asbuilt->zipName());
        }

        return response()->download($asbuilts->buildZip($asbuilt), $asbuilt->zipName(), [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    /**
     * A client downloads any file of an as-built they follow, also while it is still open.
     */
    public function clientFile(AsbuiltFile $file): StreamedResponse
    {
        abort_unless($file->asbuilt && $this->clientMay($file->asbuilt), 403);

        return $this->send($file->storagePath(), $file->fileName());
    }

    /**
     * A standard file of a COW-code the client user maintains (the as-built standard files of the portal).
     */
    public function clientLibrary(AsbuiltLibraryFile $file): StreamedResponse
    {
        $user = Auth::guard(ClientPortal::GUARD)->user();
        abort_unless($user instanceof ClientUser && $user->is_active && modules()->asbuilt() && $user->may(ClientPortal::ASBUILT_LIBRARY)
            && $file->work_site_id !== null && $file->category->perCowCode()
            && $user->restrictWorkSites(WorkSite::query()->whereKey($file->work_site_id))->exists(), 403);

        return $this->send($file->path, $file->original_name);
    }

    public static function clientMay(Asbuilt $asbuilt): bool
    {
        $user = Auth::guard(ClientPortal::GUARD)->user();

        return $user instanceof ClientUser
            && $user->is_active
            && modules()->asbuilt()
            && $user->may(ClientPortal::ASBUILT)
            && $user->restrictProjects(Project::query()->whereKey($asbuilt->project_id))->exists();
    }

    protected function send(?string $path, string $name): StreamedResponse
    {
        abort_if(blank($path) || ! Storage::disk(Asbuilt::DISK)->exists($path), 404);

        return Storage::disk(Asbuilt::DISK)->download($path, $name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
