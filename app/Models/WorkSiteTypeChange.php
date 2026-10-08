<?php

namespace App\Models;

use App\Enums\BuildingType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the building type log of a work site.
 */
#[Fillable(['work_site_id', 'from_type', 'to_type', 'changed_on', 'reason', 'remarks', 'user_id'])]
class WorkSiteTypeChange extends Model
{
    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_type' => BuildingType::class,
            'to_type' => BuildingType::class,
            'changed_on' => 'date',
        ];
    }
}
