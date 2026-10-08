<?php

namespace Database\Seeders;

use App\Enums\ItCategoryType;
use App\Enums\ItStatusType;
use App\Models\AssetCategory;
use App\Models\Distributor;
use App\Models\ItCategory;
use App\Models\ItManufacturer;
use App\Models\ItStatusLabel;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Role;
use App\Models\StockCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Starting data for a fresh install: default roles, equipment categories and stock master data.
 * Create the first administrator with `php artisan erp:create-user --admin`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Beheerder' => ['description' => 'Volledige toegang, ook tot de beheerkant.', 'is_admin' => true, 'permissions' => []],
            'Ploegleider' => ['description' => 'Beheert de eigen ploeg, voertuigen en materieel.', 'is_admin' => false, 'permissions' => [
                'teams' => ['view', 'update'],
                'vehicles' => ['view', 'update'],
                'assets' => ['view', 'update'],
                'asset_logs' => ['view', 'create', 'update'],
                'vacations' => ['view', 'approve'],
                'order_references' => ['view', 'create', 'update'],
                'projects' => ['view', 'create', 'update', 'files', 'parts'],
                'locations' => ['view'],
                'stock_items' => ['view'],
                'stock' => ['adjust'],
                'it_assets' => ['view'],
            ]],
            'Medewerker' => ['description' => 'Ziet het materieel van de ploeg en kan schade melden.', 'is_admin' => false, 'permissions' => [
                'asset_logs' => ['create'],
                'stock_items' => ['view'],
                'stock' => ['adjust'],
                'projects' => ['view', 'files'],
            ]],
            'Gast / Magazijn' => ['description' => 'Medewerkers zonder ploeg: geen rechten.', 'is_admin' => false, 'is_guest' => true, 'permissions' => []],
        ];

        foreach ($roles as $name => $attributes) {
            Role::firstOrCreate(['name' => $name], $attributes);
        }

        $categories = [
            'Meettoestellen' => 12,
            'Elektrisch handgereedschap' => 12,
            'Verlengkabels & haspels' => 12,
            'Ladders & stellingen' => 12,
            'Persoonlijke beschermingsmiddelen' => 6,
            'Werfverlichting & werfkasten' => 12,
        ];

        foreach ($categories as $name => $months) {
            AssetCategory::firstOrCreate(['name' => $name], ['inspection_interval_months' => $months]);
        }

        $this->seedStock();
        $this->seedIt();
    }

    /**
     * Status labels and categories in the spirit of Snipe-IT's defaults.
     */
    protected function seedIt(): void
    {
        foreach ([
            ['Gebruiksklaar', ItStatusType::Deployable, '#16a34a'],
            ['In bestelling', ItStatusType::Pending, '#f7941d'],
            ['In herstelling', ItStatusType::Undeployable, '#dc2626'],
            ['Defect', ItStatusType::Undeployable, '#991b1b'],
            ['Afgeschreven', ItStatusType::Archived, '#64748b'],
        ] as [$name, $type, $color]) {
            ItStatusLabel::firstOrCreate(['name' => $name], ['type' => $type, 'color' => $color]);
        }

        foreach ([
            ItCategoryType::Asset->value => ['Laptops', 'Desktops', 'Monitoren', 'Smartphones', 'Tablets', 'Printers', 'Netwerk'],
            ItCategoryType::Accessory->value => ['Muizen', 'Toetsenborden', 'Headsets', 'Dockingstations', 'Kabels & adapters'],
            ItCategoryType::Consumable->value => ['Toner & inkt', 'Batterijen'],
            ItCategoryType::Component->value => ['Geheugen (RAM)', 'Opslag (SSD/HDD)'],
            ItCategoryType::License->value => ['Kantoorsoftware', 'Besturingssystemen', 'Beveiliging', 'Vakspecifieke software'],
        ] as $type => $names) {
            foreach ($names as $name) {
                ItCategory::firstOrCreate(['type' => $type, 'name' => $name]);
            }
        }

        foreach ([
            'Dell' => 'https://www.dell.com/nl-be',
            'HP' => 'https://www.hp.com/be-nl',
            'Lenovo' => 'https://www.lenovo.com/be/nl',
            'Apple' => 'https://www.apple.com/befr',
            'Microsoft' => 'https://www.microsoft.com/nl-be',
            'Samsung' => 'https://www.samsung.com/be',
            'Ubiquiti' => 'https://www.ui.com',
        ] as $name => $website) {
            ItManufacturer::firstOrCreate(['name' => $name], ['website' => $website]);
        }
    }

    /**
     * A first location, units, categories and the usual brands and wholesalers. All editable afterwards.
     */
    protected function seedStock(): void
    {
        if (! Location::query()->exists()) {
            Location::create(['name' => 'Hoofdzetel', 'code' => 'HQ', 'country' => 'België']);
        }

        foreach ([['Meter', 'm', true], ['Stuk', 'st', false], ['Rol', 'rol', false], ['Doos', 'doos', false]] as [$name, $abbreviation, $decimals]) {
            Unit::firstOrCreate(['abbreviation' => $abbreviation], ['name' => $name, 'allows_decimals' => $decimals]);
        }

        $categories = [
            'Kabels' => ['XVB', 'XGB', 'EXVB', 'Monokabel (VOB)', 'Multikabel', 'Signaalkabel', 'Datakabel'],
            'Zekeringen' => [],
            'Automaten' => ['Automaat 1P+N', 'Automaat 3P', 'Automaat 3P+N'],
            'Differentieelschakelaars' => [],
            'Schakelmateriaal' => ['Schakelaars', 'Stopcontacten'],
            'Verdeelborden' => [],
            'Installatiemateriaal' => ['Dozen', 'Buizen', 'Kabelgoten', 'Bevestiging'],
        ];
        $sort = 0;

        foreach ($categories as $name => $children) {
            $parent = StockCategory::firstOrCreate(['name' => $name, 'parent_id' => null], ['sort' => $sort++]);

            foreach ($children as $childSort => $child) {
                StockCategory::firstOrCreate(['name' => $child, 'parent_id' => $parent->id], ['sort' => $childSort]);
            }
        }

        $manufacturers = collect([
            'Schneider Electric' => 'https://www.se.com/be',
            'ABB' => 'https://new.abb.com/be',
            'Hager' => 'https://hager.com/be-nl',
            'Legrand' => 'https://www.legrand.be',
            'Niko' => 'https://www.niko.eu',
            'Siemens' => 'https://www.siemens.com/be',
        ])->map(fn (string $website, string $name): Manufacturer => Manufacturer::firstOrCreate(['name' => $name], ['website' => $website]));

        foreach (['Cebeo' => ['https://www.cebeo.be', 'cebeo'], 'Rexel' => ['https://www.rexel.be', 'rexel']] as $name => [$website, $provider]) {
            $distributor = Distributor::firstOrCreate(['name' => $name], ['website' => $website, 'store_url' => $website, 'price_provider' => $provider]);
            $distributor->manufacturers()->syncWithoutDetaching($manufacturers->pluck('id'));
        }
    }
}
