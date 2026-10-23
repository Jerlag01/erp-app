<?php

namespace App\Http\Controllers;

use App\Models\Asbuilt;
use App\Models\AsbuiltFile;
use App\Models\AsbuiltLibraryFile;
use App\Models\ClientUser;
use App\Models\Project;
use App\Support\ClientPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

    public function clientZip(Asbuilt $asbuilt): StreamedResponse
    {
        abort_unless($this->clientMay($asbuilt) && $asbuilt->isClosed(), 403);

        return $this->send($asbuilt->zip_path, $asbuilt->zipName());
    }

    /**
     * A client downloads what they handed in themselves.
     */
    public function clientFile(AsbuiltFile $file): StreamedResponse
    {
        abort_unless($file->asbuilt && $this->clientMay($file->asbuilt) && ($file->uploaded_by_client_user_id ?? $file->libraryFile?->uploaded_by_client_user_id) !== null, 403);

        return $this->send($file->storagePath(), $file->fileName());
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
