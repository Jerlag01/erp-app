<?php

namespace App\Models;

use App\Enums\VatRate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'description', 'quantity', 'unit', 'unit_price', 'vat_rate', 'line_total', 'is_standard', 'sort_order'])]
class InvoiceLine extends Model
{
    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function totalFor(mixed $quantity, mixed $unitPrice): float
    {
        return round((float) $quantity * (float) $unitPrice, 2);
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            $line->line_total = self::totalFor($line->quantity, $line->unit_price);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'vat_rate' => VatRate::class,
            'is_standard' => 'boolean',
        ];
    }
}
