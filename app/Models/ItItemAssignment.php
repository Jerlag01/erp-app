<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An accessory lent to a user, a component built into an asset, or consumables handed out.
 * Open (not returned) assignments of accessories and components keep pieces in use.
 */
#[Fillable(['it_item_id', 'user_id', 'it_asset_id', 'quantity', 'assigned_at', 'returned_at', 'note', 'created_by'])]
class ItItemAssignment extends Model
{
    /**
     * @return BelongsTo<ItItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItItem::class, 'it_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ItAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'assigned_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
