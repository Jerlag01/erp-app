<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use App\Support\PrivatePhotos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a photo from the idea/complaint box to administrators.
 */
class SuggestionPhotoController
{
    public function __invoke(Suggestion $suggestion, int $index): StreamedResponse
    {
        abort_unless(Gate::allows('view', $suggestion), 403);

        $path = $suggestion->photoPath($index);
        abort_if($path === null || ! Storage::disk(PrivatePhotos::DISK)->exists($path), 404);

        return Storage::disk(PrivatePhotos::DISK)->response($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
