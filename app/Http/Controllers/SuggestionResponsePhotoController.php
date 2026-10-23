<?php

namespace App\Http\Controllers;

use App\Models\SuggestionResponse;
use App\Support\PrivatePhotos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a photo of a reply in the idea box to those who take part in the conversation:
 * administrators and the employee who submitted the idea or complaint.
 */
class SuggestionResponsePhotoController
{
    public function __invoke(SuggestionResponse $response, int $index): StreamedResponse
    {
        abort_unless($response->suggestion !== null && Gate::allows('respond', $response->suggestion), 403);

        $path = $response->photoPath($index);
        abort_if($path === null || ! Storage::disk(PrivatePhotos::DISK)->exists($path), 404);

        return Storage::disk(PrivatePhotos::DISK)->response($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
