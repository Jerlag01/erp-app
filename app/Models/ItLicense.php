<?php

namespace App\Models;

use Database\Factories\ItLicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A software license with a number of seats that are handed out to users or assets.
 */
#[Fillable([
    'name', 'it_category_id', 'it_manufacturer_id', 'product_key', 'seats', 'licensed_to_name', 'licensed_to_email',
    'it_supplier_id', 'order_number', 'purchase_date', 'purchase_cost', 'expiration_date', 'reassignable', 'notes',
])]
#[Hidden(['product_key'])]
class ItLicense extends Model
{
    /** @use HasFactory<ItLicenseFactory> */
    use HasFactory;

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
     * @return BelongsTo<ItSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ItSupplier::class, 'it_supplier_id');
    }

    /**
     * @return HasMany<ItLicenseSeat, $this>
     */
    public function licenseSeats(): HasMany
    {
        return $this->hasMany(ItLicenseSeat::class);
    }

    /**
     * @return MorphMany<ItLog, $this>
     */
    public function logs(): MorphMany
    {
        return $this->morphMany(ItLog::class, 'loggable')->latest('created_at')->latest('id');
    }

    public function freeSeats(): int
    {
        return $this->licenseSeats()->whereNull('user_id')->whereNull('it_asset_id')->count();
    }

    /**
     * Keeps one seat row per seat: adds missing ones, removes free ones when the number goes down.
     * Seats in use are never removed, so the number cannot drop below what is handed out.
     */
    public function syncSeats(): void
    {
        $current = $this->licenseSeats()->count();

        if ($current < $this->seats) {
            $this->licenseSeats()->createMany(array_fill(0, $this->seats - $current, []));
        } elseif ($current > $this->seats) {
            $this->licenseSeats()->whereNull('user_id')->whereNull('it_asset_id')->latest('id')->limit($current - $this->seats)->get()->each->delete();
        }
    }

    public function isExpired(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->isPast();
    }

    /**
     * Licenses with a seat for the user, a team member or an asset the user may see.
     *
     * @param  Builder<ItLicense>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams()) {
            $query->whereHas('licenseSeats', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereIn('user_id', $user->teamMemberIds())
                ->orWhereIn('it_asset_id', ItAsset::query()->visibleTo($user)->select('id'))));
        }
    }

    /**
     * @param  Builder<ItLicense>  $query
     */
    public function scopeExpiring(Builder $query): void
    {
        $query->whereNotNull('expiration_date')->whereDate('expiration_date', '<=', today()->addDays(settings()->warningDays()));
    }

    protected static function booted(): void
    {
        static::saved(fn (self $license) => $license->syncSeats());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_key' => 'encrypted',
            'seats' => 'integer',
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'expiration_date' => 'date',
            'reassignable' => 'boolean',
        ];
    }
}
