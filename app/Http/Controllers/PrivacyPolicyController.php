<?php

namespace App\Http\Controllers;

use App\Support\MobileApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public privacy policy of the mobile app (also linked from Google Play and from the app), in Dutch,
 * French or English: ?lang=, else the browser's language.
 */
class PrivacyPolicyController
{
    /** When the policy text last changed; update it together with lang/{nl,fr,en}/privacy.php. */
    public const UPDATED = '2026-10-19';

    public const LOCALES = ['nl', 'fr', 'en'];

    public function __invoke(Request $request, MobileApp $app): View
    {
        $locale = in_array($request->query('lang'), self::LOCALES, true)
            ? (string) $request->query('lang')
            : ($request->getPreferredLanguage(self::LOCALES) ?? 'nl');

        app()->setLocale($locale);

        return view('privacy.policy', [
            'locale' => $locale,
            'appName' => $app->name(),
            'company' => $app->privacyCompany(),
            'email' => $app->privacyEmail(),
            'updated' => CarbonImmutable::parse(self::UPDATED)->locale($locale)->translatedFormat('j F Y'),
        ]);
    }
}
