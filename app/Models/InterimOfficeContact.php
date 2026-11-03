<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contact of an interim office: name and e-mail address.
 */
#[Fillable(['interim_office_id', 'name', 'email', 'sort'])]
class InterimOfficeContact extends Model
{
    /**
     * @return BelongsTo<InterimOffice, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(InterimOffice::class, 'interim_office_id');
    }
}
