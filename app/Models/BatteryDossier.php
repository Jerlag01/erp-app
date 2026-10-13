<?php

namespace App\Models;

use App\Enums\BatteryDeliveryMode;
use App\Enums\BatteryInvoiceKind;
use App\Enums\BatteryMounting;
use App\Enums\BatteryTechnology;
use App\Enums\ProjectStatus;
use App\Support\BatteryTemplates;
use Database\Factories\BatteryDossierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A battery replacement dossier on a 604 project: the Excel "Online Dossier BAE NL" as a record.
 * The texts and amounts its documents show are built here, following the Excel formulas.
 */
#[Fillable([
    'project_id', 'po_number', 'contact_person', 'order_date', 'truck_plate', 'waste_type', 'execution_date',
    'old_capacity_ah', 'old_technology', 'old_count', 'removal', 'old_room',
    'new_capacity_ah', 'new_technology', 'new_count', 'delivery_mode', 'supplier_location', 'new_room',
    'volts_per_cell', 'first_id_number', 'brand', 'battery_model_id', 'mounting', 'cells_per_battery', 'production_date',
    'vertical_install_m', 'vertical_removal_m', 'ppe_sets', 'maintenance_sets', 'load_plates', 'man_hours',
    'powerflex_length_m', 'powerflex_section', 'battery_cable_id', 'frotek_count', 'lug_count', 'lug_section',
    'fuse_count', 'fuse_amperage', 'fuse_type', 'tray_length_m', 'tray_type', 'picto_plates', 'doc_holders',
    'battery_total_price', 'hourly_rate', 'fuse_price', 'powerflex_price', 'lug_price', 'tray_price', 'doc_holder_price', 'picto_price',
    'invoice_kind', 'invoice_percentage', 'measured_on', 'average_temperature', 'inspection', 'inspection_remarks',
    'floor_plan', 'floor_plan_png', 'photos', 'photos_remark', 'finished_at', 'finished_by', 'acid_boxes', 'empty_boxes_returned', 'packages', 'weight_kg', 'created_by',
])]
class BatteryDossier extends Model
{
    /** @use HasFactory<BatteryDossierFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * Most photos of the completed works per dossier.
     */
    public const MAX_PHOTOS = 40;

    /**
     * Documents that get a written signature. Once signed, their fields are locked for everyone but
     * administrators.
     *
     * @var array<string, list<string>>
     */
    public const SIGNED_FIELDS = [
        'delivery' => [
            'vertical_install_m', 'vertical_removal_m', 'ppe_sets', 'maintenance_sets', 'load_plates', 'man_hours',
            'picto_plates', 'doc_holders', 'powerflex_length_m', 'powerflex_section', 'battery_cable_id', 'frotek_count',
            'lug_count', 'lug_section', 'tray_length_m', 'tray_type', 'fuse_count', 'fuse_amperage', 'fuse_type',
            'fuse_price', 'powerflex_price', 'lug_price', 'tray_price', 'doc_holder_price', 'picto_price',
        ],
        'measurement' => ['measured_on', 'average_temperature'],
        'inspection' => ['inspection', 'inspection_remarks'],
    ];

    /**
     * Most batteries in one dossier (the Excel had room for 6).
     */
    public const MAX_BATTERIES = 12;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<BatteryModel, $this>
     */
    public function batteryModel(): BelongsTo
    {
        return $this->belongsTo(BatteryModel::class);
    }

    /**
     * @return BelongsTo<BatteryCable, $this>
     */
    public function cable(): BelongsTo
    {
        return $this->belongsTo(BatteryCable::class, 'battery_cable_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /**
     * A finished dossier is locked: only administrators and "batteries.edit_finished" (for the invoicing)
     * still change it.
     */
    public function isEditableBy(?User $user): bool
    {
        return ! $this->isFinished() || (bool) $user?->hasPermission('batteries.edit_finished');
    }

    /**
     * The technician finishes the works (the photos are required): the dossier is locked and the 604
     * project goes to "invoicing to do".
     */
    public function finish(User $by): void
    {
        if (count((array) $this->photos) === 0) {
            throw new \DomainException('A battery dossier needs photos of the completed works before it is finished.');
        }

        DB::transaction(function () use ($by): void {
            $this->update(['finished_at' => now(), 'finished_by' => $by->id]);
            $this->project?->update(['status' => ProjectStatus::InvoicingToDo]);
        });
    }

    /**
     * Opens a finished dossier again; the project keeps its status.
     */
    public function reopen(): void
    {
        $this->update(['finished_at' => null, 'finished_by' => null]);
    }

    /**
     * @return HasMany<BatteryDossierSignature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(BatteryDossierSignature::class);
    }

    public function signature(string $document): ?BatteryDossierSignature
    {
        return $this->signatures->firstWhere('document', $document);
    }

    public function isSigned(string $document): bool
    {
        return $this->signature($document) !== null;
    }

    /**
     * Signed documents can no longer be changed, except by administrators.
     */
    public function isLockedFor(string $document, ?User $user): bool
    {
        return $this->isSigned($document) && ! $user?->isAdmin();
    }

    /**
     * Puts the written signature on a document (PNG data URL from the signature pad).
     */
    public function sign(string $document, string $name, string $dataUrl, User $by): BatteryDossierSignature
    {
        abort_unless(array_key_exists($document, self::SIGNED_FIELDS), 404);

        $png = str_starts_with($dataUrl, 'data:image/png;base64,') ? base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true) : false;

        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw new \InvalidArgumentException('The signature is not a valid PNG image.');
        }

        $path = "batteries/{$this->id}/signature-{$document}-".Str::random(12).'.png';
        Storage::disk(self::DISK)->put($path, $png);

        $this->signature($document)?->delete();
        $signature = $this->signatures()->create([
            'document' => $document,
            'signer_name' => mb_substr(trim($name), 0, 150),
            'path' => $path,
            'signed_by' => $by->id,
            'signed_at' => now(),
        ]);
        $this->unsetRelation('signatures');

        return $signature;
    }

    /**
     * @return HasMany<BatteryDossierBattery, $this>
     */
    public function batteries(): HasMany
    {
        return $this->hasMany(BatteryDossierBattery::class)->orderBy('position');
    }

    /**
     * Dossiers on projects the user can see.
     *
     * @param  Builder<BatteryDossier>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('project', fn (Builder $query) => $query->visibleTo($user));
    }

    /**
     * Keeps one battery row per new battery: rows are added or removed when the number changes.
     * Empty ID numbers are filled in counting up from the first ID number.
     */
    public function syncBatteries(): void
    {
        $count = max(1, min(self::MAX_BATTERIES, (int) $this->new_count));

        $this->batteries()->where('position', '>', $count)->delete();

        for ($position = 1; $position <= $count; $position++) {
            $battery = $this->batteries()->firstOrCreate(['position' => $position], ['fuse_type' => BatteryDossierBattery::DEFAULT_FUSE_TYPE]);

            if (blank($battery->id_number) && ctype_digit((string) $this->first_id_number)) {
                $battery->update(['id_number' => (string) ((int) $this->first_id_number + $position - 1)]);
            }
        }

        $this->unsetRelation('batteries');
    }

    public function reference(): string
    {
        return $this->project?->reference ?? '—';
    }

    public function cowCode(): ?string
    {
        return $this->project?->workSite?->cow_code ?? $this->project?->cow_code;
    }

    /**
     * The sender on the shipping note: the address of the project's COW site, or the address of
     * Settings > Batteries when the site has none.
     *
     * @return list<string>
     */
    public function senderAddressLines(): array
    {
        $site = $this->project?->workSite;
        $lines = array_values(array_filter([
            trim(implode(' ', array_filter([$site?->street, $site?->house_number, $site?->addition]))),
            trim(implode(' ', array_filter([$site?->postal_code, $site?->city]))),
        ]));

        return $lines !== [] ? $lines : BatteryTemplates::lines(settings()->get('batteries.shipping_sender'));
    }

    public function cellsTotal(): int
    {
        return (int) $this->new_count * (int) $this->cells_per_battery;
    }

    /**
     * "19311812 - 19311813".
     */
    public function idNumbers(): string
    {
        return $this->batteries->pluck('id_number')->filter()->implode(' - ');
    }

    /**
     * "Vervangen van 2 sets batterijen van 800 Ah door 2 sets batterijen van 500 Ah".
     */
    public function description(): string
    {
        return "Vervangen van {$this->old_count} sets batterijen van {$this->old_capacity_ah} Ah door {$this->new_count} sets batterijen van {$this->new_capacity_ah} Ah";
    }

    /**
     * First line of the delivery note and invoice request, depending on who brings the batteries.
     */
    public function deliveryLine(): string
    {
        return match ($this->delivery_mode) {
            BatteryDeliveryMode::Delivery => "{$this->new_count} sets batterijen van {$this->new_capacity_ah} Ah worden bij u geleverd door {$this->brand}",
            BatteryDeliveryMode::OnSite => "{$this->new_count} sets batterijen van {$this->new_capacity_ah} ter plaatse aanwezig : {$this->supplier_location}",
            default => "Plaatsen van {$this->new_count} nieuwe batterij(en), {$this->batteryModel?->name} Ah van {$this->brand}",
        };
    }

    public function batteryDescription(): string
    {
        return $this->batteryModel?->description((int) $this->cells_per_battery) ?? '';
    }

    public function rack(): ?BatteryRack
    {
        return $this->batteryModel?->rackFor($this->mounting);
    }

    /**
     * "Stelling voor OPzV batterij BAE 500Ah -  Kast".
     */
    public function rackLine(): string
    {
        return "Stelling voor {$this->new_technology?->value} batterij {$this->brand} {$this->new_capacity_ah}Ah -  ".($this->mounting?->getLabel() ?? '');
    }

    /**
     * "Vernietigen van (2 batterijen 800 Ah OpzV)".
     */
    public function destroyLine(): string
    {
        return "Vernietigen van ({$this->old_count} batterijen {$this->old_capacity_ah} Ah {$this->old_technology?->value})";
    }

    public function pricePerCell(): float
    {
        return $this->cellsTotal() > 0 ? round((float) $this->battery_total_price / $this->cellsTotal(), 4) : 0.0;
    }

    /**
     * Price for removing and destroying the old batteries: base + (factor × Ah/100 × batteries × cells),
     * e.g. 300 + (1,3 × 8 × 2 × 24) for OPzV.
     *
     * @return array{price: float, formula: string}
     */
    public function removalPrice(): array
    {
        $opzv = $this->old_technology === BatteryTechnology::OPzV;
        $base = (float) settings()->get($opzv ? 'batteries.removal_base_opzv' : 'batteries.removal_base_other');
        $factor = (float) settings()->get($opzv ? 'batteries.removal_factor_opzv' : 'batteries.removal_factor_other');
        $hundreds = (float) $this->old_capacity_ah / 100;
        $format = fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');

        return [
            'price' => round($base + $factor * $hundreds * (int) $this->old_count * (int) $this->cells_per_battery, 2),
            'formula' => "Formule: {$format($base)} + ({$format($factor)} x {$format($hundreds)} x {$this->old_count} x {$this->cells_per_battery})",
        ];
    }

    /**
     * The lines of the invoice request ("Fact Aanv NL"), with the prices of the settings and the dossier.
     *
     * @return list<array{post: ?string, description: string, detail: ?string, quantity: float, unit_price: float, total: float}>
     */
    public function invoiceLines(): array
    {
        $price = fn (string $key): float => (float) settings()->get("batteries.{$key}");
        $cells = $this->cellsTotal();
        $removal = $this->removalPrice();
        $line = fn (?string $post, string $description, ?string $detail, float $quantity, float $unitPrice, ?float $total = null): array => [
            'post' => $post, 'description' => $description, 'detail' => $detail, 'quantity' => $quantity, 'unit_price' => $unitPrice,
            'total' => round($total ?? $quantity * $unitPrice, 2),
        ];

        return [
            $line('post 1', $this->deliveryLine(), $this->batteryDescription(), $cells, $this->pricePerCell(), round((float) $this->battery_total_price, 2)),
            $line('post 2', $this->rackLine(), 'Stelling type: '.($this->rack()?->code ?? '—'), $cells, (float) ($this->rack()?->price ?? 0)),
            $line('post 3', 'Verticaal transport: montage', null, (float) $this->vertical_install_m, $price('vertical_transport_price'), (float) $this->vertical_install_m * $price('vertical_transport_price') * $cells),
            $line('post 4', $this->destroyLine(), $removal['formula'], $this->removal ? 1 : 0, $removal['price']),
            $line('post 5', 'Verticaal transport: demontage', null, (float) $this->vertical_removal_m, $price('vertical_transport_price'), (float) $this->vertical_removal_m * $price('vertical_transport_price') * (int) $this->cells_per_battery * (int) $this->old_count),
            $line('post 6', 'Set persoonlijke beschermingsmiddelen', null, (float) $this->ppe_sets, $price('ppe_set_price')),
            $line('post 7', 'Set onderhoudsmateriaal', null, (float) $this->maintenance_sets, $price('maintenance_set_price')),
            $line('post 8', 'Plaatjes voor belastings herverdeling', null, (float) $this->load_plates, $price('load_plate_price')),
            $line('post 9', 'Manuren: Aansluiting kabels', null, (float) $this->man_hours, (float) $this->hourly_rate),
            $line(null, 'Soepele kabel: '.($this->cable?->name ?? 'Frötek'), null, (float) $this->frotek_count, (float) ($this->cable?->price ?? 0)),
            $line(null, trim('Soepele kabel: PowerflexPlus 1x'.($this->powerflex_section ? " {$this->powerflex_section}mm²" : '')), null, (float) $this->powerflex_length_m, (float) $this->powerflex_price),
            $line(null, trim('Kabelschoenen DIN '.($this->lug_section ? "{$this->lug_section}mm²" : '')), null, (float) $this->lug_count, (float) $this->lug_price),
            $line(null, trim('Batterijzekeringen '.trim(($this->fuse_amperage ? "{$this->fuse_amperage}A " : '').($this->fuse_type ?? ''))), null, (float) $this->fuse_count, (float) $this->fuse_price),
            $line(null, trim('Verdeelkanaal '.($this->tray_type ?? '')), null, (float) $this->tray_length_m, (float) $this->tray_price),
            $line(null, 'Documenthouder', null, (float) $this->doc_holders, (float) $this->doc_holder_price),
            $line(null, 'Plaatje pictogrammen', null, (float) $this->picto_plates, (float) $this->picto_price),
        ];
    }

    public function invoiceTotal(): float
    {
        return round(array_sum(array_column($this->invoiceLines(), 'total')), 2);
    }

    /**
     * The amount of this (partial or final) invoice: total × percentage.
     */
    public function invoiceAmount(): float
    {
        return round($this->invoiceTotal() * (float) $this->invoice_percentage / 100, 2);
    }

    public function inspectionAnswer(string $key): ?string
    {
        return ($this->inspection ?? [])[$key] ?? null;
    }

    public function floorPlanDataUri(): ?string
    {
        if (blank($this->floor_plan_png) || ! Storage::disk(self::DISK)->exists($this->floor_plan_png)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk(self::DISK)->get($this->floor_plan_png));
    }

    public static function money(float $amount): string
    {
        return '€ '.number_format($amount, 2, ',', '.');
    }

    protected static function booted(): void
    {
        static::saved(fn (self $dossier) => $dossier->wasChanged(['new_count', 'first_id_number']) || $dossier->wasRecentlyCreated ? $dossier->syncBatteries() : null);

        static::deleting(fn (self $dossier) => $dossier->signatures->each->delete());

        // Photos taken out of the dossier are removed from the disk.
        static::updated(function (self $dossier): void {
            if ($dossier->wasChanged('photos')) {
                $removed = array_diff((array) json_decode((string) $dossier->getRawOriginal('photos'), true), (array) $dossier->photos);
                Storage::disk(self::DISK)->delete(array_values($removed));
            }
        });

        static::deleted(function (self $dossier): void {
            if (filled($dossier->floor_plan_png)) {
                Storage::disk(self::DISK)->delete($dossier->floor_plan_png);
            }

            Storage::disk(self::DISK)->delete(array_values((array) $dossier->photos));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'execution_date' => 'date',
            'measured_on' => 'date',
            'old_technology' => BatteryTechnology::class,
            'new_technology' => BatteryTechnology::class,
            'delivery_mode' => BatteryDeliveryMode::class,
            'mounting' => BatteryMounting::class,
            'invoice_kind' => BatteryInvoiceKind::class,
            'removal' => 'boolean',
            'old_count' => 'integer',
            'new_count' => 'integer',
            'cells_per_battery' => 'integer',
            'volts_per_cell' => 'decimal:2',
            'inspection' => 'array',
            'floor_plan' => 'array',
            'photos' => 'array',
            'finished_at' => 'datetime',
            'battery_total_price' => 'decimal:2',
            'invoice_percentage' => 'decimal:2',
        ];
    }
}
