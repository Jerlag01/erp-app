<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One new battery (string) of a dossier: its ID number, the single-line diagram values and the
 * measured voltage (and, for OPzS, the acid density) per cell.
 */
#[Fillable([
    'battery_dossier_id', 'position', 'id_number', 'cable_type', 'cable_length_m', 'cables_positive', 'cables_negative',
    'cable_section', 'fuse_amperage', 'fuse_type', 'cell_voltages', 'cell_densities',
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
     * "Eindspanning": the sum of the measured cell voltages, or null when nothing was measured.
     */
    public function endVoltage(int $cells): ?float
    {
        $values = array_filter(array_map(fn (int $cell): ?float => $this->voltage($cell), range(1, max(1, $cells))), fn (?float $value): bool => $value !== null);

        return $values === [] ? null : round(array_sum($values), 3);
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
        ];
    }
}
