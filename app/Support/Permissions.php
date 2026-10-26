<?php

namespace App\Support;

/**
 * Every permission a role can be granted on the employee side, grouped per area.
 * For "vacations", view and approve cover the requests of the user's team members;
 * requesting your own vacation needs no permission. "stock.adjust" lets employees raise or lower
 * quantities (e.g. after scanning a QR code) at the locations of their teams. "stock_prices.view"
 * shows market prices and stock values, which hold the special prices agreed with distributors.
 * The finance areas are not granted to any role by default: employees only see finance once
 * given these rights. "credit_notes.override" allows changing what a credit note copied from
 * its invoice (after switching that on per credit note); "expenses.validate" accepts or rejects
 * incoming invoices. The client's internal memo is for administrators only.
 * "projects" only reaches the projects a user leads or one of their teams is assigned to;
 * "projects.files" and "projects.parts" allow uploading/replacing/removing files and editing the
 * part list. "project_finance" shows (view) or edits (manage) offers and invoices of those projects;
 * project leaders always have it for their own projects.
 * "planning.plan" fills in everyone's planning (open projects and warehouse jobs) and so also sees
 * everyone's; "planning.view_all" only sees everyone's; "planning.other" adds items that are not a
 * project or warehouse job. Project leaders plan their own open projects without any of these.
 * "batteries" covers the battery dossiers of the 604 projects the user can see; "batteries.prices" shows
 * and edits prices and the invoice request; "batteries.input" sees and fills in the Input tab (the others get
 * a short overview of the project instead); "batteries.edit_finished" changes (and reopens) dossiers the
 * technician finished, e.g. for the invoicing. "batteries.bae_orders" sees, fills in and exports the BAE orders
 * of the 604 projects (with purchase prices) and mails them to the supplier; "batteries.suppliers"
 * manages the battery suppliers. The Enersys delivery follow-up uses the "enersys" permissions.
 * "board_deliveries" covers the follow-up of electrical boards per supplier (610–650 projects the user
 * can see); "board_deliveries.manage_suppliers" manages those suppliers.
 * "battery_rooms" covers the battery room dossiers of the 609 projects the user can see.
 * "access_control" covers the badge & electronic key list and the security tag list (deleting needs
 * "access_control.delete"). "onboarding.manage" manages the onboarding documents,
 * "onboarding.view_signatures" sees who signed them.
 * "planning.trainings" plans trainings. Everyone fills in their own prestation sheet; "timesheets.see_all"
 * sees, "timesheets.edit_all" changes everyone's (also after sending) and "timesheets.send" sends weeks.
 * "charging" covers the charging stations, "charging.secrets" shows their passwords and recovery codes.
 * "routers" covers the 4G routers, "routers.secrets" shows their login credentials and VPN files. The latter is
 * one of the EXPLICIT permissions: administrator roles do not get it automatically, it has to be ticked
 * on the role (also on an administrator role).
 * "generate_pdf" makes PDFs from the data of a module (documents, reports, overviews, exports) and
 * "download_pdf" downloads the PDF files stored in it (uploads, attachments); see PdfAccess. Employees
 * need them explicitly, no role gets them by default.
 * Users can have their own permissions per area; they overrule the role's for that area (also to take
 * rights away, and also for explicit permissions such as "routers.secrets"). Only an administrator role
 * overrules everything (except the explicit permissions).
 * A role stores them as {"vehicles": ["view", "update"], "assets": ["view"], ...}.
 * Administrator roles are granted everything and may also use the admin panel.
 */
class Permissions
{
    /**
     * Area => [module the area belongs to, abilities].
     *
     * @var array<string, array{module: string, abilities: list<string>}>
     */
    public const AREAS = [
        'teams' => ['module' => Modules::TEAMS, 'abilities' => ['view', 'create', 'update', 'delete']],
        'vehicles' => ['module' => Modules::FLEET, 'abilities' => ['view', 'create', 'update', 'delete']],
        'assets' => ['module' => Modules::ASSETS, 'abilities' => ['view', 'create', 'update', 'delete', 'control']],
        'asset_logs' => ['module' => Modules::ASSETS, 'abilities' => ['view', 'create', 'update', 'delete']],
        'vacations' => ['module' => Modules::VACATIONS, 'abilities' => ['view', 'approve']],
        'order_references' => ['module' => Modules::ORDERS, 'abilities' => ['view', 'create', 'update', 'delete']],
        'locations' => ['module' => Modules::LOCATIONS, 'abilities' => ['view', 'create', 'update', 'delete']],
        'stock_items' => ['module' => Modules::STOCK, 'abilities' => ['view', 'create', 'update', 'delete']],
        'stock' => ['module' => Modules::STOCK, 'abilities' => ['adjust']],
        'stock_prices' => ['module' => Modules::STOCK, 'abilities' => ['view']],
        'stock_master_data' => ['module' => Modules::STOCK, 'abilities' => ['view', 'create', 'update', 'delete']],
        'it_assets' => ['module' => Modules::IT, 'abilities' => ['view', 'create', 'update', 'delete', 'checkout', 'audit']],
        'it_licenses' => ['module' => Modules::IT, 'abilities' => ['view', 'create', 'update', 'delete', 'checkout', 'view_keys']],
        'it_items' => ['module' => Modules::IT, 'abilities' => ['view', 'create', 'update', 'delete', 'checkout']],
        'it_master_data' => ['module' => Modules::IT, 'abilities' => ['view', 'create', 'update', 'delete']],
        'work_sites' => ['module' => Modules::WORK_SITES, 'abilities' => ['view', 'create', 'update', 'delete', 'change_type', 'remarks']],
        'work_site_master_data' => ['module' => Modules::WORK_SITES, 'abilities' => ['view', 'create', 'update', 'delete']],
        'projects' => ['module' => Modules::PROJECTS, 'abilities' => ['view', 'create', 'update', 'delete', 'files', 'parts', 'generate_pdf', 'download_pdf', 'copy', 'gpw', 'intervention_po']],
        'project_finance' => ['module' => Modules::PROJECTS, 'abilities' => ['view', 'manage']],
        'knowledge_base' => ['module' => Modules::KNOWLEDGE_BASE, 'abilities' => ['create', 'update', 'delete', 'download_pdf']],
        'waste' => ['module' => Modules::WASTE, 'abilities' => ['view', 'create', 'update', 'delete', 'generate_pdf']],
        'waste_master_data' => ['module' => Modules::WASTE, 'abilities' => ['view', 'create', 'update', 'delete']],
        'finance' => ['module' => Modules::FINANCE, 'abilities' => ['view']],
        'clients' => ['module' => Modules::FINANCE, 'abilities' => ['view', 'create', 'update', 'delete']],
        'invoices' => ['module' => Modules::FINANCE, 'abilities' => ['view', 'create', 'update', 'delete', 'send', 'generate_pdf']],
        'credit_notes' => ['module' => Modules::FINANCE, 'abilities' => ['view', 'create', 'update', 'delete', 'send', 'override', 'generate_pdf']],
        'expenses' => ['module' => Modules::FINANCE, 'abilities' => ['view', 'create', 'update', 'delete', 'validate', 'download_pdf']],
        'finance_master_data' => ['module' => Modules::FINANCE, 'abilities' => ['view', 'create', 'update', 'delete']],
        'planning' => ['module' => Modules::PLANNING, 'abilities' => ['view_all', 'plan', 'other', 'trainings']],
        'timesheets' => ['module' => Modules::TIMESHEETS, 'abilities' => ['see_all', 'edit_all', 'send', 'generate_pdf']],
        'charging' => ['module' => Modules::CHARGING, 'abilities' => ['view', 'create', 'update', 'delete', 'secrets', 'generate_pdf']],
        'routers' => ['module' => Modules::CHARGING, 'abilities' => ['view', 'create', 'update', 'delete', 'secrets', 'generate_pdf']],
        'batteries' => ['module' => Modules::BATTERIES, 'abilities' => ['view', 'create', 'update', 'delete', 'input', 'prices', 'documents', 'documents_upload', 'bae_orders', 'suppliers', 'generate_pdf', 'edit_finished']],
        'enersys' => ['module' => Modules::ENERSYS, 'abilities' => ['view', 'create', 'update', 'delete', 'input', 'prices', 'generate_pdf', 'edit_finished']],
        'cable_stripping' => ['module' => Modules::CABLE_STRIPPING, 'abilities' => ['view', 'create', 'update', 'delete', 'generate_pdf']],
        'announcements' => ['module' => Modules::ANNOUNCEMENTS, 'abilities' => ['update']],
        'asbuilt' => ['module' => Modules::ASBUILT, 'abilities' => ['view', 'manage', 'library', 'reopen', 'delete']],
        'hr_calculations' => ['module' => Modules::HR_CALCULATIONS, 'abilities' => ['view', 'manage']],
        'toolbox' => ['module' => Modules::TOOLBOX, 'abilities' => ['create', 'update', 'delete', 'view_signatures', 'generate_pdf']],
        'battery_master_data' => ['module' => Modules::BATTERIES, 'abilities' => ['view', 'create', 'update', 'delete']],
        'board_deliveries' => ['module' => Modules::BOARD_DELIVERIES, 'abilities' => ['view', 'create', 'update', 'delete', 'manage_suppliers']],
        // Resetting a user's two-step verification (explicit: also administrators need it ticked).
        'security' => ['module' => null, 'abilities' => ['reset_two_factor']],
        'access_control' => ['module' => Modules::EMPLOYEES, 'abilities' => ['view', 'create', 'update', 'delete', 'generate_pdf']],
        'onboarding' => ['module' => Modules::ONBOARDING, 'abilities' => ['manage', 'view_signatures', 'generate_pdf']],
        'battery_rooms' => ['module' => Modules::BATTERY_ROOMS, 'abilities' => ['view', 'create', 'update', 'delete', 'generate_pdf', 'download_pdf']],
    ];

    /**
     * Permissions an administrator role is not granted automatically: they must be ticked on the role.
     *
     * @var list<string>
     */
    public const EXPLICIT = ['routers.secrets', 'hr_calculations.view', 'hr_calculations.manage', 'asbuilt.delete', 'security.reset_two_factor'];

    public static function isExplicit(string $permission): bool
    {
        return in_array($permission, self::EXPLICIT, true);
    }

    /**
     * The explicit permissions per area, e.g. ['routers' => ['secrets']].
     *
     * @return array<string, list<string>>
     */
    public static function explicit(): array
    {
        $areas = [];

        foreach (self::EXPLICIT as $permission) {
            [$area, $ability] = explode('.', $permission, 2);
            $areas[$area][] = $ability;
        }

        return $areas;
    }

    /**
     * Every permission, e.g. ['vehicles' => ['view', 'create', ...], ...].
     *
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        return array_map(fn (array $area): array => $area['abilities'], self::AREAS);
    }

    /**
     * A user's own permissions: per known area the known abilities. Unlike a role, an area with an
     * empty list is kept: it takes away what the role grants in that area.
     *
     * @param  array<string, mixed>  $permissions
     * @return array<string, list<string>>
     */
    public static function sanitizeOverrides(array $permissions): array
    {
        $clean = [];

        foreach (self::all() as $area => $abilities) {
            if (array_key_exists($area, $permissions)) {
                $clean[$area] = array_values(array_intersect($abilities, (array) $permissions[$area]));
            }
        }

        return $clean;
    }

    public static function moduleOf(string $area): ?string
    {
        return self::AREAS[$area]['module'] ?? null;
    }

    /**
     * Keep only known areas and abilities, so a crafted request cannot store
     * permissions that do not exist. Administrator roles only store the explicit ones.
     *
     * @param  array<string, mixed>  $permissions
     * @return array<string, list<string>>
     */
    public static function sanitize(array $permissions, bool $explicitOnly = false): array
    {
        $clean = [];

        foreach ($explicitOnly ? self::explicit() : self::all() as $area => $abilities) {
            $granted = array_values(array_intersect($abilities, (array) ($permissions[$area] ?? [])));

            if ($granted !== []) {
                $clean[$area] = $granted;
            }
        }

        return $clean;
    }
}
