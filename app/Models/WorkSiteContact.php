<?php

namespace App\Models;

use Database\Factories\WorkSiteContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A contact person for work sites, often from the building owner or a subcontractor.
 */
#[Fillable(['first_name', 'last_name', 'company', 'email', 'phone'])]
class WorkSiteContact extends Model
{
    /** @use HasFactory<WorkSiteContactFactory> */
    use HasFactory;

    /**
     * @return HasMany<WorkSiteArea, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(WorkSiteArea::class);
    }

    /**
     * @return HasMany<WorkSite, $this>
     */
    public function workSites(): HasMany
    {
        return $this->hasMany(WorkSite::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * "Jan Peeters (Proximus)", for selects and lists.
     */
    public function label(): string
    {
        return $this->fullName().(filled($this->company) ? " ({$this->company})" : '');
    }
}
