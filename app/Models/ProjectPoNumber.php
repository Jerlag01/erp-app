<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A 10-digit purchase order number from the client. A project can have several.
 */
#[Fillable(['project_id', 'number'])]
class ProjectPoNumber extends Model
{
    public const PATTERN = '/^\d{10}$/';

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The invoice that billed this PO number (one at most).
     *
     * @return BelongsToMany<Invoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_po_numbers')->withTimestamps();
    }
}
