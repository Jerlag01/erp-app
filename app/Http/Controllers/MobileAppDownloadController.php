<?php

namespace App\Http\Controllers;

use App\Support\MobileApp;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Android app (APK), public: the app's update message and the link in the platform open it without
 * signing in. The app itself only works with an ERP account.
 */
class MobileAppDownloadController
{
    public function __invoke(MobileApp $app): StreamedResponse
    {
        abort_unless($app->available(), 404);

        return Storage::disk(MobileApp::DISK)->download((string) $app->path(), $app->downloadName(), [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
