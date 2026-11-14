<?php

namespace App\Models;

use App\Support\CableCalculator;
use Database\Factories\CableCalculationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cable section calculation, AC or DC (chosen first, each with its own fields): for AC the circuit
 * (system, voltage, load), the cable (metal, insulation), how it is laid and the limits; for DC the
 * breaker or fuse, length, resistivity, allowed voltage drop in volt and the provided section. The
 * result of CableCalculator is recalculated whenever it is saved.
 */
#[Fillable([
    'name', 'project_id', 'system', 'voltage', 'load_type', 'load_current', 'load_power', 'power_factor', 'length',
    'material', 'insulation', 'installation_method', 'ambient_temperature', 'grouped_circuits', 'parallel_conductors',
    'max_voltage_drop', 'protective_device', 'short_circuit_ka', 'short_circuit_time', 'notes', 'created_by',
    'protection_type', 'protection_size', 'resistivity', 'max_drop_volts', 'chosen_section',
])]
class CableCalculation extends Model
{
    /** @use HasFactory<CableCalculationFactory> */
    use HasFactory;

    public const INPUTS = [
        'system', 'voltage', 'load_type', 'load_current', 'load_power', 'power_factor', 'length', 'material', 'insulation',
        'installation_method', 'ambient_temperature', 'grouped_circuits', 'parallel_conductors', 'max_voltage_drop',
        'protective_device', 'short_circuit_ka', 'short_circuit_time',
        'protection_type', 'protection_size', 'resistivity', 'max_drop_volts', 'chosen_section',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function inputs(): array
    {
        return array_map(fn (mixed $value): mixed => is_numeric($value) ? (float) $value : $value, $this->only(self::INPUTS));
    }

    /**
     * The proposed cable, e.g. "3 × 6 mm² Cu XLPE"; null when no section fits.
     */
    public function cableLabel(): ?string
    {
        $result = (array) $this->result;

        if ($this->isDc()) {
            return isset($result['section']) ? CableCalculator::dcLabel((float) $result['section'], (int) ($result['parallel'] ?? 1)) : null;
        }

        return isset($result['section'])
            ? CableCalculator::label((float) $result['section'], $this->material, $this->insulation, (int) ($result['loaded'] ?? 3), (int) ($result['parallel'] ?? 1))
            : null;
    }

    public function isDc(): bool
    {
        return $this->system === 'dc';
    }

    protected static function booted(): void
    {
        static::saving(function (self $calculation): void {
            // A DC calculation has no AC fields: the defaults keep the required columns filled.
            foreach (['material', 'insulation', 'installation_method', 'ambient_temperature', 'max_voltage_drop'] as $field) {
                $calculation->{$field} ??= CableCalculator::defaults()[$field];
            }

            $calculation->result = CableCalculator::calculate($calculation->inputs());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
        ];
    }
}
