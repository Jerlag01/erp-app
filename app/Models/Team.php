<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'color', 'description', 'leader_id', 'location_id', 'is_active'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * The name with the first names of its active members: "Ploeg Gent - Jan / Piet / An".
     */
    public function label(): string
    {
        $names = $this->loadMissing(['members' => fn ($query) => $query->where('is_active', true)->with('employee:id,user_id,first_name')->orderBy('name')])
            ->members
            ->map(fn (User $member): string => $member->firstName())
            ->filter()
            ->implode(' / ');

        return $names === '' ? $this->name : $this->name.' - '.$names;
    }

    /**
     * Teams as options for a choice, labelled with their members.
     *
     * @param  Builder<Team>|null  $query
     * @return array<int, string>
     */
    public static function options(?Builder $query = null): array
    {
        return ($query ?? static::query())
            ->with(['members' => fn ($query) => $query->where('is_active', true)->with('employee:id,user_id,first_name')->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Team $team): array => [$team->id => $team->label()])
            ->all();
    }

    /**
     * Teams the user may see: their own, or all of them for administrators.
     *
     * @param  Builder<Team>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams()) {
            $query->whereIn('teams.id', $user->teamIds());
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
