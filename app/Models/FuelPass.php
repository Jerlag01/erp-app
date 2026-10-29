<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FuelPassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fuel pass of a vehicle: the card number (19 digits) and its PIN (4 digits, encrypted). The PIN is
 * only shown to the members of the vehicle's team and to users with "fuel_passes.view_pin".
 */
#[Fillable(['vehicle_id', 'card_number', 'pin_code', 'expires_on', 'notes'])]
#[Hidden(['pin_code'])]
class FuelPass extends Model
{
    /** @use HasFactory<FuelPassFactory> */
    use HasFactory;

    public const CARD_PATTERN = '/^\d{19}$/';

    public const PIN_PATTERN = '/^\d{4}$/';

    /** The expiry as on the card: "08/2028". */
    public const EXPIRY_PATTERN = '/^(0[1-9]|1[0-2])\/\d{4}$/';

    /** Showing the PIN: also administrators need it ticked. */
    public const PIN_PERMISSION = 'fuel_passes.view_pin';

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * "7077 1234 5678 9012 345".
     */
    public function formattedCardNumber(): string
    {
        return trim(chunk_split((string) $this->card_number, 4, ' '));
    }

    /**
     * "08/2028" (month and year, as on the card).
     */
    public function expiryLabel(): ?string
    {
        return $this->expires_on?->format('m/Y');
    }

    /**
     * Expired once the month on the card is over.
     */
    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->endOfMonth()->isPast();
    }

    /**
     * "08/2028" to the last day of that month; null when empty or not a month and year.
     */
    public static function parseExpiry(?string $value): ?string
    {
        if (! is_string($value) || ! preg_match(self::EXPIRY_PATTERN, trim($value))) {
            return null;
        }

        [$month, $year] = explode('/', trim($value));

        return CarbonImmutable::create((int) $year, (int) $month, 1)->endOfMonth()->toDateString();
    }

    /**
     * The members of the vehicle's team, and those with the permission to see PINs.
     */
    public function pinVisibleTo(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $teamId = $this->vehicle?->team_id;

        return $user->hasPermission(self::PIN_PERMISSION) || ($teamId !== null && in_array((int) $teamId, $user->teamIds(), true));
    }

    /**
     * What the user may see: everything (within their teams, as vehicles) with "fuel_passes.view",
     * otherwise the passes of their own teams' vehicles.
     *
     * @param  Builder<FuelPass>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasPermission('fuel_passes.view')) {
            $query->whereHas('vehicle', fn (Builder $query) => $query->visibleTo($user));

            return;
        }

        $query->whereHas('vehicle', fn (Builder $query) => $query->whereIn('vehicles.team_id', $user->teamIds() ?: [0]));
    }

    protected static function booted(): void
    {
        static::saving(function (self $pass): void {
            $pass->card_number = preg_replace('/\D/', '', (string) $pass->card_number);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pin_code' => 'encrypted',
            'expires_on' => 'date',
        ];
    }
}
