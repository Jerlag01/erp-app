<?php

namespace App\Models;

use App\Enums\VatRate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A line every new invoice starts with (e.g. file costs, BTV inspection). Empty VAT rate = the
 * client's default rate.
 */
#[Fillable(['description', 'quantity', 'unit', 'unit_price', 'vat_rate', 'is_active', 'sort_order'])]
class StandardInvoiceLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'vat_rate' => VatRate::class,
            'is_active' => 'boolean',
        ];
    }
}
