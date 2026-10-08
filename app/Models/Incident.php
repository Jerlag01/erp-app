<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Concerns\HasPrivatePhotos;
use App\Support\PrivatePhotos;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An incident report: accident, near miss, dangerous situation or dangerous action,
 * at a storage location or a work site. Photos are kept on the private disk and only
 * served to people allowed to see the report.
 */
#[Fillable([
    'user_id', 'reporter_name', 'reported_at', 'type', 'place_type', 'place_id', 'place_label', 'location_details',
    'other_victims', 'other_victims_details', 'material_damage', 'material_damage_details', 'description', 'photos',
    'status', 'follow_up', 'handled_by', 'handled_at',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory, HasPrivatePhotos;

    public const DISK = PrivatePhotos::DISK;

    /**
     * The person who reported it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Where it happened: a storage Location or a WorkSite.
     *
     * @return MorphTo<Model, $this>
     */
    public function place(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * "Depot Luik" or "02GAM – Rue de la Gare 5, 4000 Liège", kept as text so the report
     * still says where it happened if the place is renamed or removed later.
     */
    public static function labelFor(Model $place): string
    {
        return match (true) {
            $place instanceof WorkSite => trim($place->cow_code.' – '.$place->addressLine(), ' –'),
            $place instanceof Location => $place->name,
            default => '',
        };
    }

    public function placeName(): ?string
    {
        return $this->place ? static::labelFor($this->place) : $this->place_label;
    }

    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            $incident->reported_at ??= now();
            $incident->status ??= IncidentStatus::New;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'reported_at' => 'datetime',
            'handled_at' => 'datetime',
            'other_victims' => 'boolean',
            'material_damage' => 'boolean',
            'photos' => 'array',
        ];
    }
}
