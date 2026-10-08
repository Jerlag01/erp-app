<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a stock level: who, how much, and what was left afterwards.
 */
#[Fillable(['stock_level_id', 'user_id', 'change', 'quantity_after', 'note', 'created_at'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<StockLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class, 'stock_level_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change' => 'decimal:3',
            'quantity_after' => 'decimal:3',
        ];
    }
}
