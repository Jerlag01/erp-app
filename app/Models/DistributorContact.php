<?php

namespace App\Models;

use Database\Factories\DistributorContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['distributor_id', 'first_name', 'last_name', 'job_title', 'email', 'phone'])]
class DistributorContact extends Model
{
    /** @use HasFactory<DistributorContactFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
