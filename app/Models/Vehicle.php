<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Models\Concerns\DeletesStoredFiles;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'plate_number', 'brand', 'type', 'vin', 'year', 'fuel', 'mileage',
    'control_date', 'next_control_date', 'status', 'team_id', 'driver_id', 'location_id', 'photo', 'notes',
])]
class Vehicle extends Model
{
    use DeletesStoredFiles;

    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    public function storedFiles(): array
    {
        return ['photo'];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * "Brand Type (PLATE)", used in selects and titles.
     */
    public function label(): string
    {
        return "{$this->brand} {$this->type} ({$this->plate_number})";
    }

    /**
     * Vehicles whose next control date is overdue or falls within the warning period.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeControlDue(Builder $query): void
    {
        $query->whereNotNull('next_control_date')
            ->whereDate('next_control_date', '<=', today()->addDays(settings()->warningDays()))
            ->where('status', '!=', VehicleStatus::Sold);
    }

    /**
     * Vehicles the user may see: those of their teams and the ones they drive themselves.
     *
     * @param  Builder<Vehicle>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams()) {
            $query->where(fn (Builder $query) => $query
                ->whereIn('vehicles.team_id', $user->teamIds())
                ->orWhere('vehicles.driver_id', $user->id));
        }
    }

    /**
     * Uppercase plates and VINs without spaces, so lookups and uniqueness are reliable.
     */
    protected static function booted(): void
    {
        static::saving(function (self $vehicle): void {
            $vehicle->plate_number = strtoupper(trim((string) $vehicle->plate_number));
            $vehicle->vin = strtoupper(preg_replace('/\s+/', '', (string) $vehicle->vin));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'control_date' => 'date',
            'next_control_date' => 'date',
            'status' => VehicleStatus::class,
            'fuel' => FuelType::class,
            'year' => 'integer',
            'mileage' => 'integer',
        ];
    }
}
