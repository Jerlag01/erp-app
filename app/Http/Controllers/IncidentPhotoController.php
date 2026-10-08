<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an incident photo from the private disk to the reporter and to administrators.
 */
class IncidentPhotoController
{
    public function __invoke(Incident $incident, int $index): StreamedResponse
    {
        abort_unless(Gate::allows('view', $incident), 403);

        $path = $incident->photoPath($index);
        abort_if($path === null || ! Storage::disk(Incident::DISK)->exists($path), 404);

        return Storage::disk(Incident::DISK)->response($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
