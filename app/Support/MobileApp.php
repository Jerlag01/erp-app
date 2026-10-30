<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The Android app (APK) uploaded in Settings > Mobile app, which signed-in employees download from the
 * platform; and whether to suggest it to someone browsing on a phone. The download can be switched off;
 * then the links to Google Play and the App Store (both optional) take its place.
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

    /**
     * Whether the APK can be downloaded from the platform: not switched off, and uploaded.
     */
    public function available(): bool
    {
        if ($this->downloadDisabled()) {
            return false;
        }

        $path = settings()->get('mobile_app.apk');

        return filled($path) && Storage::disk(self::DISK)->exists($path);
    }

    public function downloadDisabled(): bool
    {
        return (bool) settings()->get('mobile_app.download_disabled', false);
    }

    public function playStoreUrl(): ?string
    {
        return filled($url = settings()->get('mobile_app.play_store_url')) ? (string) $url : null;
    }

    public function appStoreUrl(): ?string
    {
        return filled($url = settings()->get('mobile_app.app_store_url')) ? (string) $url : null;
    }

    /**
     * Where to get the app on this device: the App Store on an iPhone or iPad; elsewhere the APK when it
     * can be downloaded, else Google Play (or the App Store). Null when there is nothing to offer.
     */
    public function linkFor(?string $userAgent): ?string
    {
        if (self::isApplePhone($userAgent)) {
            return $this->appStoreUrl();
        }

        if ($this->available()) {
            return route('mobile-app.download');
        }

        return $this->playStoreUrl() ?? (self::isAndroidPhone($userAgent) ? null : $this->appStoreUrl());
    }

    /**
     * The link points to a store, not to the APK.
     */
    public function isStoreLink(?string $link): bool
    {
        return $link !== null && in_array($link, [$this->playStoreUrl(), $this->appStoreUrl()], true);
    }

    /**
     * "Open in the App Store" or "Open in Google Play" for a store link; "Download the app" for the APK.
     */
    public function linkLabel(?string $link): string
    {
        return match (true) {
            $link !== null && $link === $this->appStoreUrl() && $link !== $this->playStoreUrl() => __('erp.mobile_app.open_app_store'),
            $this->isStoreLink($link) => __('erp.mobile_app.open_play_store'),
            default => __('erp.mobile_app.download'),
        };
    }

    public function path(): ?string
    {
        return $this->available() ? (string) settings()->get('mobile_app.apk') : null;
    }

    /**
     * The name of the app, as on the phone ("PI ERP").
     */
    public function name(): string
    {
        return filled($name = settings()->get('mobile_app.name')) ? (string) $name : 'PI ERP';
    }

    /**
     * The data controller in the privacy policy: set in Settings > Mobile app, else the company block of
     * the documents, else the site name.
     */
    public function privacyCompany(): string
    {
        return (string) (settings()->get('mobile_app.privacy_company') ?: settings()->get('batteries.company_block') ?: settings()->siteName());
    }

    public function privacyEmail(): ?string
    {
        $email = settings()->get('mobile_app.privacy_email') ?: settings()->get('finance.email');

        return filled($email) ? (string) $email : null;
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
     * What identifies the current upload: the version, or else when it was uploaded. A new release shows
     * the suggestion again, also to who chose "don't show again".
     */
    public function release(): string
    {
        return (string) ($this->version() ?? settings()->get('mobile_app.uploaded_at') ?? '');
    }

    /**
     * Whether to suggest the app: switched on, the browser is a phone, there is something to offer it (the
     * APK or a store link) and the user did not choose "don't show again" for this release.
     */
    public function shouldPrompt(?string $userAgent, ?User $user = null): bool
    {
        return (bool) settings()->get('mobile_app.prompt', true)
            && (self::isAndroidPhone($userAgent) || self::isApplePhone($userAgent))
            && $this->linkFor($userAgent) !== null
            && ($user === null || $user->mobile_app_dismissed_release !== $this->release());
    }

    /**
     * The message of Settings > Mobile app in the user's language, or the standard text.
     */
    public function message(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $custom = settings()->get("mobile_app.message_{$locale}") ?: settings()->get('mobile_app.message_nl');

        return filled($custom) ? (string) $custom : __('erp.mobile_app.prompt_body', [], $locale);
    }

    public static function isAndroidPhone(?string $userAgent): bool
    {
        return $userAgent !== null && str_contains($userAgent, 'Android') && str_contains($userAgent, 'Mobile');
    }

    public static function isApplePhone(?string $userAgent): bool
    {
        return $userAgent !== null && (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') || str_contains($userAgent, 'iPod'));
    }
}
