<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One COW code of a GPW request that covers several, with the period asked for it. The COW code is kept
 * as it was when requested.
 */
#[Fillable(['gpw_request_id', 'work_site_id', 'cow_code', 'starts_on', 'ends_on'])]
class GpwRequestSite extends Model
{
    /**
     * @return BelongsTo<GpwRequest, $this>
     */
    public function gpwRequest(): BelongsTo
    {
        return $this->belongsTo(GpwRequest::class);
    }

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * "12/11/2026 – 14/11/2026", or one date for a single day.
     */
    public function period(): string
    {
        return GpwRequest::formatPeriod($this->starts_on, $this->ends_on);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
