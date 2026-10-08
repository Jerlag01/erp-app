<?php

namespace App\Models;

use App\Enums\ItItemKind;
use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\ItItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * IT stock counted in pieces, in three kinds (as in Snipe-IT):
 * accessories are lent to users and come back, consumables are used up,
 * components are built into an asset.
 */
#[Fillable([
    'kind', 'name', 'it_category_id', 'it_manufacturer_id', 'model_number', 'serial', 'quantity', 'min_quantity',
    'location_id', 'it_supplier_id', 'order_number', 'purchase_date', 'purchase_cost', 'image', 'notes',
])]
class ItItem extends Model
{
    use DeletesStoredFiles;

    /** @use HasFactory<ItItemFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['image'];
    }

    /**
     * @return BelongsTo<ItCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItCategory::class, 'it_category_id');
    }

    /**
     * @return BelongsTo<ItManufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(ItManufacturer::class, 'it_manufacturer_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<ItSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ItSupplier::class, 'it_supplier_id');
    }

    /**
     * @return HasMany<ItItemAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ItItemAssignment::class);
    }

    /**
     * @return MorphMany<ItLog, $this>
     */
    public function logs(): MorphMany
    {
        return $this->morphMany(ItLog::class, 'loggable')->latest('created_at')->latest('id');
    }

    /**
     * Pieces that can still be handed out. Consumables are taken off the quantity when used;
     * accessories and components stay counted until they come back.
     */
    public function available(): int
    {
        if ($this->kind === ItItemKind::Consumable) {
            return (int) $this->quantity;
        }

        return max(0, (int) $this->quantity - (int) $this->assignments()->whereNull('returned_at')->sum('quantity'));
    }

    public function isLow(): bool
    {
        return $this->min_quantity !== null && $this->available() <= $this->min_quantity;
    }

    /**
     * @param  Builder<ItItem>  $query
     */
    public function scopeKind(Builder $query, ItItemKind $kind): void
    {
        $query->where('it_items.kind', $kind);
    }

    /**
     * Items at the user's team locations or handed out to their team, or everything for administrators.
     *
     * @param  Builder<ItItem>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->seesOnlyOwnTeams()) {
            return;
        }

        $locations = modules()->locations() ? $user->locationIds() : [];

        $query->where(fn (Builder $query) => $query
            ->whereIn('it_items.location_id', $locations)
            ->orWhereHas('assignments', fn (Builder $query) => $query->whereNull('returned_at')->where(fn (Builder $query) => $query
                ->whereIn('user_id', $user->teamMemberIds())
                ->orWhereIn('it_asset_id', ItAsset::query()->visibleTo($user)->select('id')))));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ItItemKind::class,
            'quantity' => 'integer',
            'min_quantity' => 'integer',
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }
}
