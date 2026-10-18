<?php

namespace App\Http\Controllers;

use App\Support\MobileApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Don't show again": the suggestion to download the app stays away for this user until a new version
 * is uploaded, on every device.
 */
class MobileAppDismissController
{
    public function __invoke(Request $request, MobileApp $app): Response
    {
        $request->user()->update(['mobile_app_dismissed_release' => $app->release()]);

        return response()->noContent();
    }
}
