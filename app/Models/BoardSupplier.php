<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier of electrical boards (ELP, P&V panels, CE+T, Automation LP …) with its own follow-up list.
 * The layout decides the list: "simple" (PO, delivery date, delivered) or "configured" (PO, expected
 * and actual delivery, configuration date, completed).
 */
#[Fillable(['name', 'layout', 'sort', 'is_active'])]
class BoardSupplier extends Model
{
    public const SIMPLE = 'simple';

    public const CONFIGURED = 'configured';

    public const LAYOUTS = [self::SIMPLE, self::CONFIGURED];

    /**
     * @return HasMany<DeliveryFollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(DeliveryFollowUp::class);
    }

    public function isConfigured(): bool
    {
        return $this->layout === self::CONFIGURED;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
