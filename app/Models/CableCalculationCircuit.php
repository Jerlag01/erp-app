<?php

namespace App\Models;

use App\Support\CableCalculator;
use Database\Factories\CableCalculationCircuitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One circuit of a cable calculation, from a start point to an end point ("Krachtbord → Batterij 1"),
 * AC or DC with its own fields: for AC the net (3F+N, 3F, 2F, 1F+N), earthing system, frequency, voltage,
 * load (A, kW or kVA), cable (earth in the cable or apart, maximum section), how it is laid and the limits;
 * for DC the breaker or fuse, length, resistivity, allowed voltage drop in volt and the provided section.
 * The result of CableCalculator is recalculated whenever it is saved.
 */
#[Fillable([
    'cable_calculation_id', 'sort_order', 'from_point', 'to_point', 'system', 'voltage', 'load_type', 'load_current', 'load_power',
    'power_factor', 'length', 'material', 'insulation', 'installation_method', 'ambient_temperature', 'grouped_circuits',
    'parallel_conductors', 'max_voltage_drop', 'protective_device', 'short_circuit_ka', 'short_circuit_time',
    'protection_type', 'protection_size', 'resistivity', 'max_drop_volts', 'chosen_section',
    'earthing', 'frequency', 'load_kva', 'max_section', 'earth_in_cable',
])]
class CableCalculationCircuit extends Model
{
    /** @use HasFactory<CableCalculationCircuitFactory> */
    use HasFactory;

    public const INPUTS = [
        'system', 'voltage', 'load_type', 'load_current', 'load_power', 'power_factor', 'length', 'material', 'insulation',
        'installation_method', 'ambient_temperature', 'grouped_circuits', 'parallel_conductors', 'max_voltage_drop',
        'protective_device', 'short_circuit_ka', 'short_circuit_time',
        'protection_type', 'protection_size', 'resistivity', 'max_drop_volts', 'chosen_section',
        'earthing', 'frequency', 'load_kva', 'max_section', 'earth_in_cable',
    ];

    /**
     * @return BelongsTo<CableCalculation, $this>
     */
    public function calculation(): BelongsTo
    {
        return $this->belongsTo(CableCalculation::class, 'cable_calculation_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function inputs(): array
    {
        return array_map(fn (mixed $value): mixed => is_numeric($value) && ! is_bool($value) ? (float) $value : $value, $this->only(self::INPUTS));
    }

    public function isDc(): bool
    {
        return $this->system === 'dc';
    }

    /**
     * "Krachtbord → Batterij 1".
     */
    public function route(): string
    {
        return self::routeLabel($this->from_point, $this->to_point);
    }

    public static function routeLabel(?string $from, ?string $to): string
    {
        return implode(' → ', array_filter([trim((string) $from), trim((string) $to)], fn (string $point): bool => $point !== '')) ?: '—';
    }

    /**
     * The proposed cable, e.g. "3 × 6 mm² Cu XLPE" or "95 mm² per pool"; null when no section fits.
     */
    public function cableLabel(): ?string
    {
        return self::cableLabelFor((array) $this->result, $this->inputs());
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $input
     */
    public static function cableLabelFor(array $result, array $input): ?string
    {
        if (! isset($result['section'])) {
            return null;
        }

        return ($input['system'] ?? null) === 'dc'
            ? CableCalculator::dcLabel((float) $result['section'], (int) ($result['parallel'] ?? 1))
            : CableCalculator::acLabel($result, (string) ($input['material'] ?? 'cu'), (string) ($input['insulation'] ?? 'xlpe'));
    }

    protected static function booted(): void
    {
        static::saving(function (self $circuit): void {
            // A DC circuit has no AC fields (and the other way round): the defaults keep them filled.
            foreach (['material', 'insulation', 'installation_method', 'ambient_temperature', 'max_voltage_drop'] as $field) {
                $circuit->{$field} ??= CableCalculator::defaults()[$field];
            }

            $circuit->result = CableCalculator::calculate($circuit->inputs());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'earth_in_cable' => 'boolean',
        ];
    }
}
