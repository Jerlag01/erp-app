<?php

namespace App\Models;

use App\Enums\BatteryMounting;
use App\Enums\BatteryTechnology;
use Database\Factories\BatteryModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A battery cell type such as "7 OPzV 490", with weight and dimensions and a rack per mounting.
 */
#[Fillable(['name', 'technology', 'weight_kg', 'length_mm', 'width_mm', 'height_mm', 'is_active'])]
class BatteryModel extends Model
{
    /** @use HasFactory<BatteryModelFactory> */
    use HasFactory;

    /**
     * @return HasMany<BatteryRack, $this>
     */
    public function racks(): HasMany
    {
        return $this->hasMany(BatteryRack::class);
    }

    public function rackFor(?BatteryMounting $mounting): ?BatteryRack
    {
        return $mounting ? $this->racks->firstWhere('mounting', $mounting) : null;
    }

    /**
     * "24 cellen 7 OPzV 490. Gewicht:42Kg. Afmetingen:l:168 b:208 h:535" (Excel "Batterijen", column F).
     */
    public function description(int $cells): string
    {
        $weight = rtrim(rtrim(number_format((float) $this->weight_kg, 2, ',', ''), '0'), ',');

        return __('battery_documents.lines.model', ['cells' => $cells, 'name' => $this->name, 'weight' => $weight, 'length' => $this->length_mm, 'width' => $this->width_mm, 'height' => $this->height_mm]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'technology' => BatteryTechnology::class,
            'weight_kg' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
