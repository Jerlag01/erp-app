<?php

namespace App\Models;

use App\Enums\BuildingType;
use Database\Factories\MoveDossierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A battery move on a 605 project: batteries taken from one COW site (the source) and placed at another
 * (the destination, by default the project's site). It works like the BAE dossier (delivery note,
 * measurement report, visual inspection, floor plan, single-line diagram, signatures, photos, finish),
 * with two shipping notes: the move itself, and the old batteries when they are taken away at the
 * destination. When the source is decommissioned, finishing changes its building type (logged).
 */
class MoveDossier extends BatteryDossier
{
    /** @use HasFactory<MoveDossierFactory> */
    use HasFactory;

    public const KIND = 'move';

    /** The 605 module and its permissions, as the Enersys dossiers. */
    public const AREA = 'enersys';

    public const CATEGORY_SETTING = 'batteries.enersys_project_category';

    /** No price document. */
    public const PRICE_DOCUMENT = '';

    public const INTERNAL_DOCUMENTS = [];

    /**
     * Document => [view, letterhead background, landscape].
     */
    public const DOCUMENTS = [
        'delivery' => ['batteries.move.delivery', true, false],
        'measurement' => ['batteries.pdf.measurement', true, false],
        'inspection' => ['batteries.pdf.inspection', false, false],
        'floor_plan' => ['batteries.pdf.floor-plan', false, true],
        'single_line' => ['batteries.pdf.single-line', false, true],
        'shipping_move' => ['batteries.move.shipping', true, false],
        'shipping' => ['batteries.pdf.shipping', true, false],
    ];

    public const INPUT_FIELDS = [
        'document_locale', 'project_id', 'po_number', 'contact_person', 'order_date', 'execution_date', 'truck_plate', 'waste_type',
        'move_from_work_site_id', 'move_to_work_site_id', 'decommission_source',
        'old_capacity_ah', 'old_technology', 'old_count', 'old_room', 'removal',
        'new_capacity_ah', 'new_technology', 'new_count', 'new_room',
        'brand', 'battery_model_id', 'mounting', 'cells_per_battery', 'volts_per_cell', 'production_date', 'first_id_number',
    ];

    public const SIGNED_FIELDS = [
        'delivery' => [
            'vertical_pickup_m', 'vertical_install_m', 'vertical_removal_m', 'ppe_sets', 'maintenance_sets', 'load_plates', 'man_hours',
            'picto_plates', 'doc_holders', 'powerflex_length_m', 'powerflex_section', 'battery_cable_id', 'frotek_count',
            'lug_count', 'lug_section', 'tray_length_m', 'tray_type', 'fuse_count', 'fuse_amperage', 'fuse_type',
        ],
        'measurement' => ['measured_on', 'average_temperature'],
        'inspection' => ['inspection', 'inspection_remarks'],
    ];

    /** The move's own shipping note (packages, weight) and that of the old batteries. */
    public const SHIPPING_FIELDS = ['move_packages', 'move_weight_kg', 'acid_boxes', 'empty_boxes_returned', 'packages', 'weight_kg'];

    public static function documents(): array
    {
        return self::DOCUMENTS;
    }

    public static function documentRoute(): string
    {
        return 'moves.document';
    }

    /**
     * The shipping note of the old batteries only when they are taken away at the destination.
     */
    public function availableDocuments(): array
    {
        return array_values(array_filter(array_keys(self::DOCUMENTS), fn (string $document): bool => $document !== 'shipping' || $this->removal));
    }

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class, 'move_from_work_site_id');
    }

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function toSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class, 'move_to_work_site_id');
    }

    /**
     * Where the batteries go: the chosen site, else the project's.
     */
    public function destination(): ?WorkSite
    {
        return $this->toSite ?? $this->project?->workSite;
    }

    /**
     * The destination's COW code: where the work (and the removal of old batteries) is done.
     */
    public function cowCode(): ?string
    {
        return $this->destination()?->cow_code ?? $this->project?->cow_code;
    }

    public function sourceCowCode(): ?string
    {
        return $this->fromSite?->cow_code;
    }

    /**
     * The address of a site as lines, for the documents.
     *
     * @return list<string>
     */
    public static function siteLines(?WorkSite $site): array
    {
        return array_values(array_filter([
            trim(implode(' ', array_filter([$site?->street, $site?->house_number, $site?->addition]))),
            trim(implode(' ', array_filter([$site?->postal_code, $site?->city]))),
        ]));
    }

    /**
     * The old batteries' shipping note goes from the destination.
     */
    public function senderAddressLines(): array
    {
        return self::siteLines($this->destination()) ?: parent::senderAddressLines();
    }

    /**
     * The language of the documents: that of the destination's region unless chosen.
     */
    public function documentLocale(): string
    {
        if (in_array($this->document_locale, self::DOCUMENT_LOCALES, true)) {
            return $this->document_locale;
        }

        $site = $this->destination();

        return self::regionLocale($site?->postal_code ?? $this->project?->postal_code, $site?->area?->name);
    }

    /**
     * "Verplaatsen van 2 batterijen 7 OPzV 490 (490 Ah) van 02GAM naar 14EIN".
     */
    public function description(): string
    {
        return __('battery_documents.move.description', [
            'count' => (int) $this->new_count,
            'type' => trim(implode(' ', array_filter([$this->brand, $this->typeName()]))) ?: '—',
            'ah' => (int) $this->new_capacity_ah,
            'from' => $this->sourceCowCode() ?? '—',
            'to' => $this->cowCode() ?? '—',
        ]);
    }

    /**
     * The source is decommissioned once the work is finished: its building type changes, in its log.
     */
    public function finish(User $by): void
    {
        DB::transaction(function () use ($by): void {
            parent::finish($by);
            $this->decommissionSource($by);
        });
    }

    public function decommissionSource(User $by): void
    {
        $site = $this->fromSite;

        if (! $this->decommission_source || ! $site || $this->decommissioned_at !== null) {
            return;
        }

        if (! $site->isDecommissioned()) {
            $site->changeBuildingType(
                BuildingType::Decommissioned,
                today()->toDateString(),
                __('battery_documents.move.decommission_reason', ['reference' => $this->reference()], 'nl'),
                __('battery_documents.move.decommission_remarks', ['count' => (int) $this->new_count, 'to' => $this->cowCode() ?? '—'], 'nl'),
                $by->id,
            );
        }

        $this->forceFill(['decommissioned_at' => now()])->saveQuietly();
    }

    protected static function newFactory(): MoveDossierFactory
    {
        return MoveDossierFactory::new();
    }
}
