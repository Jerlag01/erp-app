<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a project's part list: a stock item (or a free description) with a quantity and unit,
 * e.g. 150 m of cable. It does not take anything out of stock.
 */
#[Fillable(['project_id', 'stock_item_id', 'description', 'quantity', 'unit_id', 'notes'])]
class ProjectPart extends Model
{
    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function quantityLabel(): string
    {
        return $this->unit ? $this->unit->format($this->quantity) : rtrim(rtrim((string) $this->quantity, '0'), '.');
    }

    protected static function booted(): void
    {
        static::saving(function (self $part): void {
            if (blank($part->description) && $part->stockItem) {
                $part->description = $part->stockItem->name;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }
}
