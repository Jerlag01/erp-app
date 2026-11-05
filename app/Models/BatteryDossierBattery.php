<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One new battery (string) of a dossier: its ID number, the single-line diagram values and the
 * measured voltage (and, for OPzS, the acid density) per cell. Enersys dossiers measure per block
 * disconnected (cell_voltages) and floating (floating_voltages), with the float voltage of the battery.
 */
#[Fillable([
    'battery_dossier_id', 'position', 'id_number', 'cable_type', 'cable_length_m', 'cables_positive', 'cables_negative',
    'cable_section', 'fuse_amperage', 'fuse_type', 'cell_voltages', 'cell_densities', 'float_voltage', 'floating_voltages', 'total_voltage', 'floating_total_voltage',
])]
class BatteryDossierBattery extends Model
{
    public const DEFAULT_FUSE_TYPE = 'gL-gL';

    /**
     * @return BelongsTo<BatteryDossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(BatteryDossier::class, 'battery_dossier_id');
    }

    public function label(): string
    {
        return 'Batt '.$this->position;
    }

    public function voltage(int $cell): ?float
    {
        $value = ($this->cell_voltages ?? [])[$cell - 1] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    public function density(int $cell): ?float
    {
        $value = ($this->cell_densities ?? [])[$cell - 1] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * "Eindspanning" (Enersys: the total disconnected): measured and filled in, not added up from the cells.
     */
    public function endVoltage(): ?float
    {
        return $this->total_voltage !== null ? (float) $this->total_voltage : null;
    }

    /**
     * Enersys: the total battery voltage while floating, measured and filled in.
     */
    public function floatingTotal(): ?float
    {
        return $this->floating_total_voltage !== null ? (float) $this->floating_total_voltage : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'cable_length_m' => 'decimal:2',
            'cables_positive' => 'integer',
            'cables_negative' => 'integer',
            'cell_voltages' => 'array',
            'cell_densities' => 'array',
            'float_voltage' => 'decimal:2',
            'floating_voltages' => 'array',
            'total_voltage' => 'decimal:2',
            'floating_total_voltage' => 'decimal:2',
        ];
    }
}
