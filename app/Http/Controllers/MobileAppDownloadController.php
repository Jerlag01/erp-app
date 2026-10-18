<?php

namespace App\Http\Controllers;

use App\Support\MobileApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Android app (APK) for signed-in, active users.
 */
class MobileAppDownloadController
{
    public function __invoke(Request $request, MobileApp $app): StreamedResponse
    {
        abort_unless((bool) $request->user()?->is_active, 403);
        abort_unless($app->available(), 404);

        return Storage::disk(MobileApp::DISK)->download((string) $app->path(), $app->downloadName(), [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
