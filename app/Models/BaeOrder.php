<?php

namespace App\Models;

use App\Enums\BatteryMounting;
use App\Support\OutlookCalendar;
use Database\Factories\BaeOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The battery order to BAE of a 604 project: the Excel "calc.xlsx" (sheet Input, printed as sheet
 * Order). Every new 604 project gets one; the amounts follow the Excel formulas: the batteries are
 * count × cells × the BAE price per cell, the rack count × the rack price, the other lines "incl.".
 */
#[Fillable([
    'project_id', 'order_number', 'order_date', 'contract', 'battery_model_id', 'mounting', 'capacity_ah', 'technology',
    'battery_count', 'cells_per_battery', 'volts_per_cell', 'cell_price', 'lines', 'delivery_date', 'delivery_address',
    'contact_name', 'contact_phone', 'contact_fax', 'contact_mobile', 'contact_email', 'billing_block', 'remarks',
    'exported_at', 'created_by',
])]
class BaeOrder extends Model
{
    /** @use HasFactory<BaeOrderFactory> */
    use HasFactory;

    /**
     * The order lines between the batteries and the rack (Excel Order rows 23–32): description on the
     * order, the rack field it comes from (null: fixed) and the default code and quantity per battery.
     *
     * @var array<string, array{name: string, from: ?string, code: ?string, per_battery: int}>
     */
    public const LINES = [
        'discharge_test' => ['name' => 'C5 discharge test', 'from' => null, 'code' => 'test report', 'per_battery' => 1],
        'measuring_pole' => ['name' => 'Pole for measuring point', 'from' => null, 'code' => null, 'per_battery' => 138],
        'intercell' => ['name' => 'Intercell connector', 'from' => 'intercell', 'code' => null, 'per_battery' => 0],
        'step' => ['name' => 'Step connector 50mm²', 'from' => 'step', 'code' => null, 'per_battery' => 0],
        'tier70' => ['name' => 'Tier connector 70mm²', 'from' => 'tier70', 'code' => null, 'per_battery' => 0],
        'tier50' => ['name' => 'Tier connector 50mm²', 'from' => 'tier50', 'code' => null, 'per_battery' => 0],
        'row' => ['name' => 'Row connector', 'from' => 'row', 'code' => null, 'per_battery' => 0],
        'end_terminal' => ['name' => 'End terminal set', 'from' => null, 'code' => '2993618', 'per_battery' => 0],
        'middlepoint' => ['name' => 'Middlepoint connector', 'from' => null, 'code' => '2994910', 'per_battery' => 1],
        'transport' => ['name' => 'Transport', 'from' => null, 'code' => 'Pallet Niel', 'per_battery' => 4],
    ];

    /**
     * Most batteries (strings) on one order.
     */
    public const MAX_BATTERIES = 12;

    protected static function booted(): void
    {
        // Without a battery dossier, the new batteries of the order are in the project's Outlook events.
        static::saved(function (self $order): void {
            if ($order->wasChanged(['battery_model_id', 'battery_count', 'capacity_ah', 'technology'])) {
                app(OutlookCalendar::class)->syncProject($order->project_id);
            }
        });
    }

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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<BaeOrder>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('project', fn (Builder $query) => $query->visibleTo($user));
    }

    /**
     * The order lines with their defaults from the rack of the model and mounting (Excel VLOOKUP on
     * "Batterijen"): [key => ['code' => …, 'per_battery' => …]], plus "rack" => ['code' => …, 'price' => …].
     *
     * @return array<string, array<string, mixed>>
     */
    public static function linesFor(?BatteryRack $rack): array
    {
        $lines = [];

        foreach (self::LINES as $key => $line) {
            $lines[$key] = $line['from'] === null
                ? ['code' => $line['code'], 'per_battery' => $line['per_battery']]
                : ['code' => $line['from'] === 'row' ? null : $rack?->getAttribute($line['from'].'_code'), 'per_battery' => (int) $rack?->getAttribute($line['from'].'_count')];
        }

        $lines['rack'] = ['code' => $rack?->code, 'price' => $rack?->bae_price !== null ? (float) $rack->bae_price : null];

        return $lines;
    }

    /**
     * @return array{code: ?string, per_battery: int}
     */
    public function line(string $key): array
    {
        $line = ((array) $this->lines)[$key] ?? self::linesFor(null)[$key];

        return ['code' => filled($line['code'] ?? null) ? (string) $line['code'] : null, 'per_battery' => (int) ($line['per_battery'] ?? 0)];
    }

    /**
     * Units in total of a line: batteries × units per battery.
     */
    public function lineTotal(string $key): int
    {
        return $this->batteryCount() * $this->line($key)['per_battery'];
    }

    public function batteryCount(): int
    {
        return (int) $this->battery_count;
    }

    public function totalCells(): int
    {
        return $this->batteryCount() * (int) $this->cells_per_battery;
    }

    public function batteryPrice(): float
    {
        return round((int) $this->cells_per_battery * (float) $this->cell_price, 2);
    }

    public function batteriesTotal(): float
    {
        return round($this->batteryCount() * $this->batteryPrice(), 2);
    }

    public function rackCode(): ?string
    {
        $code = ((array) $this->lines)['rack']['code'] ?? null;

        return filled($code) ? (string) $code : null;
    }

    public function rackPrice(): float
    {
        return (float) (((array) $this->lines)['rack']['price'] ?? 0);
    }

    public function rackTotal(): float
    {
        return round($this->batteryCount() * $this->rackPrice(), 2);
    }

    public function total(): float
    {
        return round($this->batteriesTotal() + $this->rackTotal(), 2);
    }

    public function mountingEnum(): ?BatteryMounting
    {
        return $this->mounting;
    }

    /**
     * "48V": volts per cell × cells per battery.
     */
    public function voltage(): string
    {
        return rtrim(rtrim(number_format((float) $this->volts_per_cell * (int) $this->cells_per_battery, 2, '.', ''), '0'), '.').'V';
    }

    /**
     * " 48V / 7 OPzV 490" (Excel Order B22).
     */
    public function batteryTitle(): string
    {
        return trim($this->voltage().' / '.$this->batteryModel?->name, ' /');
    }

    /**
     * "500Ah OpzV" (Excel Order D22).
     */
    public function batteryDescription(): string
    {
        return trim(($this->capacity_ah ? $this->capacity_ah.'Ah ' : '').$this->technology);
    }

    /**
     * "U-Vorm: PGL2-23H" (Excel Order D33).
     */
    public function rackDescription(): string
    {
        return trim(implode(': ', array_filter([$this->mountingEnum()?->getLabel(), $this->rackCode()])));
    }

    /**
     * "Batterie: 2 x 7 OPzV 490" (Excel Order C13).
     */
    public function subject(): string
    {
        return 'Batterie: '.$this->batteryCount().' x '.($this->batteryModel?->name ?? '—');
    }

    /**
     * "604/923": the project category and its number (Excel Input B11).
     */
    public function dossierNumber(): string
    {
        $project = $this->project;

        return trim(($project?->category?->code ?? '').'/'.($project?->category ? $project->category->numbering->formatNumber((int) $project->number) : $project?->number), '/');
    }

    public function cowCode(): ?string
    {
        return $this->project?->workSite?->cow_code ?? $this->project?->cow_code;
    }

    /**
     * "83HVR : Route de Barvaux, 180 - 5590 HAVERSIN" (Excel Order C14), or only the COW code.
     */
    public function cowLine(): string
    {
        $site = $this->project?->workSite;
        $street = trim(implode(' ', array_filter([$site?->street, $site?->house_number, $site?->addition])));
        $place = trim(implode(' ', array_filter([$site?->postal_code, $site?->city])));
        $address = implode(' - ', array_filter([$street, $place]));

        return implode(' : ', array_filter([$this->cowCode(), $address])) ?: '—';
    }

    /**
     * "Prox Batt BAE 604/923 - 83HVR" (Excel Order C15).
     */
    public function ourReference(): string
    {
        return trim('Prox Batt BAE '.$this->dossierNumber().' - '.$this->cowCode(), ' -');
    }

    /**
     * "604/923/0726/JL": the dossier number, the month of ordering and the initials of who orders.
     */
    public static function defaultOrderNumber(self $order, ?User $by): string
    {
        $initials = collect(preg_split('/\s+/', trim((string) $by?->name)) ?: [])->filter()->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');

        return implode('/', array_filter([$order->dossierNumber(), ($order->order_date ?? now())->format('my'), $initials]));
    }

    public function isExported(): bool
    {
        return $this->exported_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'mounting' => BatteryMounting::class,
            'battery_count' => 'integer',
            'cells_per_battery' => 'integer',
            'capacity_ah' => 'integer',
            'volts_per_cell' => 'decimal:2',
            'cell_price' => 'decimal:2',
            'lines' => 'array',
            'exported_at' => 'datetime',
        ];
    }
}
