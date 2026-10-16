<?php

namespace App\Models;

use App\Enums\BatteryDeliveryMode;
use App\Support\BatteryInspection;
use App\Support\BatteryTemplates;
use Database\Factories\EnersysDossierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * An Enersys battery dossier on a 605 project: the Excel "12V enersys". It works like the BAE dossier
 * (input, delivery note, measurement report, visual inspection, shipping note, photos, signatures, finish),
 * without single-line diagram and floor plan, with 12 V blocks measured disconnected and floating, and with
 * an order ("Bestelling PI") instead of the invoice request.
 */
class EnersysDossier extends BatteryDossier
{
    /** @use HasFactory<EnersysDossierFactory> */
    use HasFactory;

    public const KIND = 'enersys';

    public const AREA = 'enersys';

    public const CATEGORY_SETTING = 'batteries.enersys_project_category';

    public const PRICE_DOCUMENT = 'order';

    /**
     * Document => [view, letterhead background, landscape].
     */
    public const DOCUMENTS = [
        'delivery' => ['batteries.enersys.delivery', true, false],
        'measurement' => ['batteries.enersys.measurement', true, false],
        'inspection' => ['batteries.pdf.inspection', false, false],
        'shipping' => ['batteries.pdf.shipping', true, false],
        'order' => ['batteries.enersys.order', false, false],
    ];

    public const INPUT_FIELDS = [
        'project_id', 'po_number', 'contact_person', 'order_date', 'execution_date', 'truck_plate', 'waste_type',
        'old_capacity_ah', 'old_count', 'old_room', 'removal',
        'new_capacity_label', 'new_capacity_ah', 'new_count', 'new_room', 'delivery_mode', 'supplier_location',
        'brand', 'battery_type', 'mounting', 'cells_per_battery', 'production_date', 'first_id_number',
    ];

    public const SIGNED_FIELDS = [
        'delivery' => [
            'vertical_install_m', 'vertical_removal_m', 'man_hours', 'powerflex_length_m', 'powerflex_section',
            'lug_count', 'lug_section', 'fuse_count', 'fuse_amperage', 'fuse_type', 'leak_trays',
        ],
        'measurement' => ['measured_on', 'average_temperature'],
        'inspection' => ['inspection', 'inspection_remarks'],
    ];

    /**
     * The amounts of the order, as in the Excel: key => default.
     */
    public const ORDER_KEYS = ['pickup', 'removal', 'placement', 'disposal', 'other', 'file_costs'];

    public static function documents(): array
    {
        return self::DOCUMENTS;
    }

    public static function documentRoute(): string
    {
        return 'enersys.document';
    }

    public function inspectionSections(): array
    {
        return BatteryInspection::enersysSections();
    }

    public function typeName(): ?string
    {
        return $this->battery_type;
    }

    /**
     * The capacity of the new batteries as on the order: "155/100" or "155".
     */
    public function newCapacity(): string
    {
        return (string) ($this->new_capacity_label ?: $this->new_capacity_ah);
    }

    /**
     * Enersys types from Settings > Batteries: type => capacity (Ah).
     *
     * @return array<string, int>
     */
    public static function types(): array
    {
        $types = [];

        foreach (BatteryTemplates::lines(settings()->get('batteries.enersys_types')) as $line) {
            [$type, $capacity] = array_pad(array_map('trim', explode('|', $line)), 2, null);

            if (filled($type)) {
                $types[$type] = (int) $capacity;
            }
        }

        return $types;
    }

    /**
     * "Vervangen van 2 sets batterijen van 0 Ah door 2 sets batterijen van 155/100 Ah".
     */
    public function description(): string
    {
        return "Vervangen van {$this->old_count} sets batterijen van ".((int) $this->old_capacity_ah)." Ah door {$this->new_count} sets batterijen van {$this->newCapacity()} Ah";
    }

    /**
     * The first line of the delivery note and the order, depending on who brings the batteries.
     */
    public function deliveryLine(): string
    {
        return match ($this->delivery_mode) {
            BatteryDeliveryMode::Delivery => "{$this->new_count} sets batterijen van {$this->newCapacity()} Ah worden bij u geleverd door {$this->brand}",
            BatteryDeliveryMode::OnSite => "{$this->new_count} sets batterijen van {$this->newCapacity()} ter plaatse aanwezig : {$this->supplier_location}",
            default => "Afhalen van {$this->new_count} sets batterijen van {$this->newCapacity()} Ah te {$this->supplier_location}",
        };
    }

    public function removalLine(): string
    {
        return "Wegnemen batterijen ({$this->old_count} sets ".((int) $this->old_capacity_ah).' Ah)';
    }

    public function placementLine(): string
    {
        return "Terugplaatsen nieuwe batterijen ({$this->new_count} sets {$this->newCapacity()} Ah)";
    }

    public function destroyLine(): string
    {
        return "Afvoeren van {$this->old_count} sets oude batterijen van ".((int) $this->old_capacity_ah).' Ah';
    }

    /**
     * The default amounts of the order (Settings > Batteries), as in the Excel: placing 338 + 4 per battery,
     * disposing 6 per old battery (only when the old ones are taken away).
     *
     * @return array<string, float>
     */
    public function defaultOrderAmounts(): array
    {
        $price = fn (string $key): float => (float) settings()->get("batteries.enersys_{$key}", 0);

        return [
            'pickup' => 0.0,
            'removal' => $price('removal_price'),
            'placement' => $price('placement_base') + $price('placement_per_battery') * (int) $this->new_count,
            'disposal' => $this->removal ? $price('disposal_per_battery') * (int) $this->old_count : 0.0,
            'other' => $price('leak_tray_price') * (int) $this->leak_trays,
            'file_costs' => 0.0,
        ];
    }

    /**
     * The amounts of the order: the ones filled in, else the defaults.
     *
     * @return array<string, float>
     */
    public function orderAmounts(): array
    {
        $own = (array) $this->order_amounts;

        return collect($this->defaultOrderAmounts())
            ->map(fn (float $default, string $key): float => is_numeric($own[$key] ?? null) ? (float) $own[$key] : $default)
            ->all();
    }

    public function orderOtherLabel(): string
    {
        return (string) (((array) $this->order_amounts)['other_label'] ?? 'Andere: lekbakken');
    }

    /**
     * Total of the order; disposing only counts when the old batteries are taken away.
     */
    public function orderTotal(): float
    {
        $amounts = $this->orderAmounts();

        if (! $this->removal) {
            unset($amounts['disposal']);
        }

        return round(array_sum($amounts), 2);
    }

    protected static function newFactory(): EnersysDossierFactory
    {
        return EnersysDossierFactory::new();
    }
}
