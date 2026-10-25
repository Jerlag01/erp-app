<?php

namespace App\Http\Controllers;

use App\Support\MobileApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public GDPR statement of the whole ERP platform (employees, client portal, contacts, private
 * clients), in Dutch, French or English: ?lang=, else the browser's language. The contact addresses
 * are set under Settings > General.
 */
class GdprController
{
    /** When the statement last changed; update it together with lang/{nl,fr,en}/gdpr.php. */
    public const UPDATED = '2026-10-25';

    public function __invoke(Request $request, MobileApp $app): View
    {
        $locale = in_array($request->query('lang'), PrivacyPolicyController::LOCALES, true)
            ? (string) $request->query('lang')
            : ($request->getPreferredLanguage(PrivacyPolicyController::LOCALES) ?? 'nl');

        app()->setLocale($locale);

        return view('privacy.gdpr', [
            'locale' => $locale,
            'company' => $app->privacyCompany(),
            'emails' => self::contactEmails(),
            'updated' => CarbonImmutable::parse(self::UPDATED)->locale($locale)->translatedFormat('j F Y'),
        ]);
    }

    /**
     * @return list<string>
     */
    public static function contactEmails(): array
    {
        return array_values(array_filter(array_map('trim', (array) settings()->get('general.gdpr_emails')), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }
}
