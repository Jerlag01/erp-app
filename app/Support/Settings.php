<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Site-wide settings managed from the admin panel, stored per group
 * (general, modules, mail, appearance, finance, planning) in the settings table.
 */
class Settings
{
    public const CACHE_KEY = 'erp-settings';

    public const GROUPS = ['general', 'banner', 'hr', 'asbuilt', 'modules', 'mail', 'appearance', 'finance', 'planning', 'batteries', 'timesheets', 'charging', 'mobile_app'];

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $values = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            // The Android app (APK) employees download from the platform (Settings > Mobile app).
            'mobile_app' => [
                'apk' => null,
                'apk_name' => null,
                'version' => null,
                'uploaded_at' => null,
                // Suggest the app to employees who open the platform on an Android phone.
                'prompt' => true,
                // The text of the suggestion per language; empty = the standard text.
                'message_nl' => null,
                'message_fr' => null,
                'message_en' => null,
                // The app's name, and the controller and contact in its privacy policy (/privacy).
                'name' => 'PI ERP',
                'privacy_company' => null,
                'privacy_email' => null,
            ],
            // HR calculations: the monthly advance (start value per statute, counted up to this day of the month).
            'hr' => [
                'advance_worker' => 620,
                'advance_servant' => 1000,
                'advance_until_day' => 15,
            ],
            // As-built files: project categories without as-built.
            'asbuilt' => [
                'excluded_categories' => ['600', '604', '605', '606', '607', '608.1'],
            ],
            // A message for the employees, in the top bar of their side of the platform.
            'banner' => [
                'enabled' => false,
                'color' => 'info',
                'message_nl' => null,
                'message_fr' => null,
                'message_en' => null,
            ],
            'general' => [
                'site_name' => 'ERP',
                'company_name' => null,
                'warning_days' => 30,
                'it_audit_months' => 12,
            ],
            'modules' => [
                Modules::FLEET => true,
                Modules::TEAMS => true,
                Modules::ASSETS => true,
                Modules::VACATIONS => true,
                Modules::ORDERS => true,
                Modules::LOCATIONS => true,
                Modules::STOCK => true,
                Modules::IT => true,
                Modules::WORK_SITES => true,
                Modules::INCIDENTS => true,
                Modules::SUGGESTIONS => true,
                Modules::PROJECTS => true,
                Modules::KNOWLEDGE_BASE => true,
                Modules::EMPLOYEES => true,
                Modules::WASTE => true,
                Modules::FINANCE => true,
                Modules::PLANNING => true,
                Modules::INTERVENTIONS => true,
                Modules::BATTERIES => true,
                Modules::TIMESHEETS => true,
                Modules::CHARGING => true,
                Modules::CABLE_STRIPPING => true,
                Modules::TOOLBOX => true,
                Modules::ANNOUNCEMENTS => true,
                Modules::ASBUILT => true,
                Modules::HR_CALCULATIONS => true,
                Modules::ENERSYS => true,
            ],
            'mail' => [
                'mailer' => null,
                'host' => null,
                'port' => 587,
                'username' => null,
                'password' => null,
                'scheme' => null,
                'from_address' => null,
                'from_name' => null,
            ],
            'appearance' => [
                'primary' => '#002b45',
                'accent' => '#f7941d',
                'logo' => null,
                'favicon' => null,
            ],
            // Company details on invoices, numbering, reminders and Peppol (Settings > Finance).
            'finance' => [
                'company_name' => null,
                'vat_number' => null,
                'street' => null,
                'house_number' => null,
                'postal_code' => null,
                'city' => null,
                'country_code' => 'BE',
                'email' => null,
                'phone' => null,
                'iban' => null,
                'bic' => null,
                'invoice_prefix' => '',
                'credit_note_prefix' => 'CN',
                'payment_term_days' => 30,
                'invoice_footer' => null,
                'reminders_enabled' => false,
                'reminder_1_days' => 7,
                'reminder_2_days' => 21,
                'reminder_3_days' => 35,
                'peppol_enabled' => false,
                'peppol_id' => null,
                'peppol_provider_url' => null,
                'peppol_api_key' => null,
                'peppol_inbound_secret' => null,
            ],
            // Preset timeslots and who gets a copy of planning mails (Settings > Planning).
            'planning' => [
                'slots' => [
                    ['label' => 'Volledige dag', 'start' => '07:30', 'end' => '16:00'],
                    ['label' => 'Voormiddag', 'start' => '07:30', 'end' => '12:00'],
                    ['label' => 'Namiddag', 'start' => '12:30', 'end' => '16:00'],
                ],
                'mail_default' => true,
                'notify_emails' => [],
                // Planning items in the employees' Outlook calendars (Microsoft 365, see OutlookCalendar).
                'outlook_enabled' => false,
                'outlook_tenant_id' => null,
                'outlook_client_id' => null,
                'outlook_client_secret' => null,
            ],
            // Charging stations and 4G routers belong to the projects of this category.
            'charging' => [
                'project_category' => '608',
            ],
            // Prestation sheets: hours and who receives the weekly PDFs (Settings > Prestations).
            'timesheets' => [
                'expected_hours' => 40,
                'day_hours' => 8,
                'lunch_hours' => 0.5,
                // Mobility allowance per km in the monthly overview.
                'mobility_rate' => 0.1929,
                'auto_send' => true,
                'send_day' => 5,
                'send_time' => '18:00',
                'recipients' => [],
                // Every employee also gets their own sheet by e-mail.
                'send_to_employee' => true,
            ],
            // Battery dossiers: templates, client, prices and the fixed texts of the shipping note (Settings > Batteries).
            'batteries' => [
                'background' => null,
                'inspection_header' => null,
                'project_category' => '604',
                // Language of the battery documents for COW sites in Brussels (Flanders Dutch, Wallonia French).
                'brussels_locale' => 'fr',
                // Enersys dossiers (Excel "12V enersys") on 605 projects.
                'enersys_project_category' => '605',
                'enersys_brand' => 'Enersys',
                'enersys_types' => "12V62FS | 62\n12V100FS | 100\n12V155FS | 155",
                'enersys_removal_price' => 334,
                'enersys_placement_base' => 338,
                'enersys_placement_per_battery' => 4,
                'enersys_disposal_per_battery' => 6,
                'enersys_leak_tray_price' => 70,
                // Status of the 604 project once its batteries are delivered ("ready to plan").
                'delivered_project_status' => 'to_be_executed',
                'client_name' => 'Proximus NV',
                'client_short' => 'Proximus',
                'client_vat' => '202 239 951',
                'framework_contract' => '4600001931',
                'brand' => 'BAE',
                'supplier_location' => 'Power Installation NV',
                'contacts' => ['Kevin Cnop', 'Bavo Bonte', 'Pascal Callebaut', 'Patrick Aneca', 'Timon Bouttelgier', 'Gérald Faway', 'Didier Noël'],
                'waste_types' => [
                    'UN2794, AFVAL, BATTERIJEN (ACCUMULATOREN), NAT, GEVULD MET ZURE ELEKTROLYT, 8, (E)',
                    'UN2800, AFVAL, BATTERIJEN (ACCUMULATOREN), NAT, VAN HET GESLOTEN TYPE, 8, (E)',
                ],
                'hourly_rate' => 64.68,
                'vertical_transport_price' => 3.55,
                'ppe_set_price' => 154,
                'maintenance_set_price' => 16,
                'load_plate_price' => 10.7,
                'lug_price' => 5.24,
                'tray_price' => 88.77,
                'doc_holder_price' => 10.5,
                'picto_price' => 10,
                'removal_base_opzv' => 300,
                'removal_base_other' => 371,
                'removal_factor_opzv' => 1.3,
                'removal_factor_other' => 2.1,
                'company_block' => "Power Installation nv\nDrukpersstraat 4\n1000  Brussel\nBTW 462 401 968",
                'seat_block' => "Exploitatiezetel: Power Installation NV\nGalileilaan 92 B19\n2845  Niel",
                'shipping_carrier' => 'MCR  Brandekensweg 54 2627 Schelle',
                'shipping_collector' => '10695',
                'shipping_sender' => "Romeinse Steenweg Paleis 5\n1020 Brussel",
                'shipping_transporter' => "Power Installation nv\nDrukpersstraat 4\n1000  Brussel",
                'shipping_transporter_signature' => "Power Installation nv\nGalileilaan 92 B19\n2845 Niel",
                'shipping_registrations' => "SPW | DSD/POWERINRB1/21/10643/T/DD | Wallonië\nBIM | AGR/CNC-DD/001486370 | Brussel\nOVAM | 38089/R/12351 | Vlaanderen",
                'shipping_destination' => "MCR bvba\nBrandekensweg 54\n2627 Schelle",
                'shipping_destination_registration' => 'B-10695/1',
                'shipping_description' => 'Loodbatterijen',
                'shipping_physical_state' => 'Vaste stof',
                'shipping_packaging' => 'Accuboxen',
                'shipping_adr' => 'Vervoer volgens 1.1.3.4.1 van het ADR: niet onderworpen aan de voorschriften van het ADR gelet op de bijzondere bepaling 598',
                'shipping_eural' => '16 06 01*',
                'shipping_rd_code' => 'R13',
                // The BAE order (Excel calc.xlsx, sheet Order): defaults of every new order.
                'bae_contract' => 'Belgacom CSL/PUR/GAE/4600001931',
                'bae_delivery_date' => 'PRIO ASAP',
                'bae_delivery_address' => 'Power Installation NV - Galileilaan 90 Unit A11 - 2845 Niel',
                'bae_contact_name' => 'Jeroen Lagaet',
                'bae_contact_phone' => '00 32 3 844 44 32',
                'bae_contact_fax' => '00 32 3 844 47 55',
                'bae_contact_mobile' => '00 32 77 62 77 63',
                'bae_contact_email' => 'j.lagaet@power-installation.be',
                'bae_billing_block' => "Power Installation nv\nDrukpersstraat 4\n1000  Brussel\nBelgium\nVAT    BE - 0462 401 968",
                'bae_remarks' => 'Orders will be checked by BAE',
                // Every BAE order that is mailed to the supplier also goes in copy to these addresses.
                'bae_order_cc' => [],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        // Only the stored values are cached; defaults are merged on top, so new groups and keys work at once.
        // Before the first migrate (no cache or settings table yet, e.g. during "composer install") or
        // without a database, the defaults are used and nothing is cached.
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
        } catch (QueryException) {
            $stored = [];
        }

        return $this->values = collect(static::defaults())
            ->map(fn (array $defaults, string $group): array => [...$defaults, ...((array) ($stored[$group] ?? []))])
            ->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(string $group, array $values): void
    {
        Setting::updateOrCreate(['key' => $group], ['value' => $values]);

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    public function siteName(): string
    {
        return filled($name = $this->get('general.site_name')) ? $name : config('app.name');
    }

    /**
     * Days before a control or inspection date that it is flagged as "due soon".
     */
    public function warningDays(): int
    {
        return max(0, (int) $this->get('general.warning_days', 30));
    }

    /**
     * Override the mail configuration with the values set in the admin panel.
     * When no mailer is chosen there, the .env configuration stays in effect.
     */
    public function applyMailConfig(): void
    {
        $mail = $this->get('mail');

        if (blank($mail['mailer'] ?? null)) {
            return;
        }

        config(array_filter([
            'mail.default' => $mail['mailer'],
            'mail.mailers.smtp.host' => $mail['host'],
            'mail.mailers.smtp.port' => $mail['port'],
            'mail.mailers.smtp.username' => $mail['username'],
            'mail.mailers.smtp.password' => filled($mail['password']) ? Crypt::decryptString($mail['password']) : null,
            'mail.mailers.smtp.scheme' => $mail['scheme'],
            'mail.from.address' => $mail['from_address'],
            'mail.from.name' => $mail['from_name'] ?: $this->siteName(),
        ], fn (mixed $value): bool => filled($value)));
    }

    public function logoUrl(): ?string
    {
        $logo = $this->get('appearance.logo');

        return filled($logo) ? Storage::disk('public')->url($logo) : null;
    }

    public function faviconUrl(): ?string
    {
        $favicon = $this->get('appearance.favicon');

        return filled($favicon) ? Storage::disk('public')->url($favicon) : null;
    }

    /**
     * Months between two audits of an IT asset.
     */
    public function itAuditMonths(): int
    {
        return max(1, (int) $this->get('general.it_audit_months', 12));
    }

    public function color(string $name): string
    {
        $fallback = static::defaults()['appearance'][$name];
        $value = (string) $this->get("appearance.{$name}");

        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : $fallback;
    }
}
