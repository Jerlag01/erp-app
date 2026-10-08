<?php

namespace App\Models;

use Database\Factories\WorkSiteAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A region the work sites are organised by, with its own contact person.
 */
#[Fillable(['name', 'description', 'work_site_contact_id'])]
class WorkSiteArea extends Model
{
    /** @use HasFactory<WorkSiteAreaFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WorkSiteContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WorkSiteContact::class, 'work_site_contact_id');
    }

    /**
     * @return HasMany<WorkSite, $this>
     */
    public function workSites(): HasMany
    {
        return $this->hasMany(WorkSite::class);
    }
}
