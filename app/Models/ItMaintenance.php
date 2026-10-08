<?php

namespace App\Models;

use App\Enums\ItMaintenanceType;
use Database\Factories\ItMaintenanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['it_asset_id', 'type', 'title', 'it_supplier_id', 'start_date', 'completion_date', 'is_warranty', 'cost', 'notes', 'user_id'])]
class ItMaintenance extends Model
{
    /** @use HasFactory<ItMaintenanceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ItAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    /**
     * @return BelongsTo<ItSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ItSupplier::class, 'it_supplier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function durationDays(): ?int
    {
        return $this->completion_date ? (int) $this->start_date->diffInDays($this->completion_date) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ItMaintenanceType::class,
            'start_date' => 'date',
            'completion_date' => 'date',
            'is_warranty' => 'boolean',
            'cost' => 'decimal:2',
        ];
    }
}
