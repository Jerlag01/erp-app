<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The Android app (APK) uploaded in Settings > Mobile app, which signed-in employees download from the
 * platform; and whether to suggest it to someone browsing on an Android phone.
 */
class MobileApp
{
    public const DISK = 'local';

    public const DIRECTORY = 'mobile-app';

    public const MAX_KILOBYTES = 256000;

    /** An APK is a zip file; servers and browsers report it under any of these types. */
    public const MIME_TYPES = [
        'application/vnd.android.package-archive',
        'application/java-archive',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    public function available(): bool
    {
        $path = settings()->get('mobile_app.apk');

        return filled($path) && Storage::disk(self::DISK)->exists($path);
    }

    public function path(): ?string
    {
        return $this->available() ? (string) settings()->get('mobile_app.apk') : null;
    }

    public function version(): ?string
    {
        return filled($version = settings()->get('mobile_app.version')) ? (string) $version : null;
    }

    /**
     * "PI-ERP-1.2.0.apk": the site name and version, so phones keep the versions apart.
     */
    public function downloadName(): string
    {
        return Str::slug(settings()->siteName(), '-').($this->version() ? '-'.Str::slug($this->version(), '.') : '').'.apk';
    }

    /**
     * Whether to suggest the app: switched on, an APK is there, and the browser is an Android phone.
     */
    public function shouldPrompt(?string $userAgent): bool
    {
        return (bool) settings()->get('mobile_app.prompt', true) && $this->available() && self::isAndroidPhone($userAgent);
    }

    public static function isAndroidPhone(?string $userAgent): bool
    {
        return $userAgent !== null && str_contains($userAgent, 'Android') && str_contains($userAgent, 'Mobile');
    }
}
