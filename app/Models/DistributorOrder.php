<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order placed at a distributor (see the migration and DistributorOrders): its lines, the delivery
 * status, and the link to our own order reference when that reference is in the order.
 */
#[Fillable([
    'distributor_id', 'external_number', 'customer_reference', 'order_reference_id', 'expense_id', 'ordered_at', 'status',
    'expected_delivery', 'delivered_at', 'total', 'source', 'notes', 'raw', 'synced_at', 'created_by',
])]
class DistributorOrder extends Model
{
    public const OPEN = 'open';

    public const CONFIRMED = 'confirmed';

    public const PARTIAL = 'partial';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::OPEN, self::CONFIRMED, self::PARTIAL, self::DELIVERED, self::CANCELLED];

    public const SOURCE_API = 'api';

    public const SOURCE_MANUAL = 'manual';

    /** Built from the distributor's Peppol invoice (RecordDistributorDeliveries). */
    public const SOURCE_INVOICE = 'invoice';

    /** Imported from the webshop basket (ImportDistributorBasket). */
    public const SOURCE_BASKET = 'basket';

    /** Built from a Cebeo delivery note (RecordDistributorDeliveries). */
    public const SOURCE_DELIVERY_NOTE = 'delivery_note';

    public const SOURCES = [self::SOURCE_MANUAL, self::SOURCE_BASKET, self::SOURCE_INVOICE, self::SOURCE_DELIVERY_NOTE, self::SOURCE_API];

    /**
     * Our order references ("466/1026/JL", also written with spaces or small letters).
     */
    public const REFERENCE_PATTERN = '/(?<![\d\/])(\d{1,6})\s*\/\s*(\d{4})\s*\/\s*([A-Za-z]{1,8})(?![A-Za-z])/';

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<OrderReference, $this>
     */
    public function orderReference(): BelongsTo
    {
        return $this->belongsTo(OrderReference::class);
    }

    /**
     * The last Peppol invoice that delivered (part of) this order.
     *
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return HasMany<DistributorOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DistributorOrderLine::class)->orderBy('line_number')->orderBy('id');
    }

    public function isFromApi(): bool
    {
        return $this->source === self::SOURCE_API;
    }

    /**
     * Our references that appear in a text, normalised like OrderReference::format() ("466/1026/JL").
     *
     * @return list<string>
     */
    public static function referencesIn(?string $text): array
    {
        preg_match_all(self::REFERENCE_PATTERN, (string) $text, $matches, PREG_SET_ORDER);

        return array_values(array_unique(array_map(fn (array $match): string => sprintf('%03d/%s/%s', (int) $match[1], $match[2], strtoupper($match[3])), $matches)));
    }

    /**
     * Links the order to our order reference, only when such a reference is in what the distributor
     * received (the customer reference, else the notes); never replaces a link made by hand.
     */
    public function linkReference(): bool
    {
        if ($this->order_reference_id !== null) {
            return false;
        }

        foreach ([$this->customer_reference, $this->notes] as $text) {
            foreach (self::referencesIn($text) as $reference) {
                if ($id = OrderReference::query()->where('reference', $reference)->value('id')) {
                    $this->order_reference_id = $id;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * "3/5 delivered": lines (fully) delivered of all lines.
     */
    public function deliveryProgress(): string
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();
        $done = $lines->filter(fn (DistributorOrderLine $line): bool => $line->isDelivered())->count();

        return __('erp.distributor_orders.progress', ['done' => $done, 'total' => $lines->count()]);
    }

    protected static function booted(): void
    {
        // Never return false here: that would cancel the save.
        static::saving(function (self $order): void {
            $order->linkReference();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'expected_delivery' => 'date',
            'delivered_at' => 'date',
            'total' => 'decimal:2',
            'raw' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
