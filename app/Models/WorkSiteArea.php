<?php

namespace App\Models;

use Database\Factories\WorkSiteAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A region the work sites are organised by, with its own contact persons (one or more).
 */
#[Fillable(['name', 'description'])]
class WorkSiteArea extends Model
{
    /** @use HasFactory<WorkSiteAreaFactory> */
    use HasFactory;

    /**
     * The area's contact persons, for its sites without a contact of their own.
     *
     * @return BelongsToMany<WorkSiteContact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(WorkSiteContact::class, 'work_site_area_contact')->withTimestamps()->orderBy('first_name')->orderBy('last_name');
    }

    /**
     * @return HasMany<WorkSite, $this>
     */
    public function workSites(): HasMany
    {
        return $this->hasMany(WorkSite::class);
    }
}
