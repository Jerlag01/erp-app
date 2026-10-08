<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How an item is counted: meters (decimals allowed) for cable, pieces for fuses and breakers.
 */
#[Fillable(['name', 'abbreviation', 'allows_decimals'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * @return HasMany<StockItem, $this>
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function format(float|string|null $quantity): string
    {
        $value = (float) $quantity;
        $formatted = $this->allows_decimals
            ? rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',')
            : number_format($value, 0, ',', '.');

        return "{$formatted} {$this->abbreviation}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allows_decimals' => 'boolean',
        ];
    }
}
