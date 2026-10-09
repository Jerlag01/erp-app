<?php

namespace App\Models;

use App\Enums\BatteryMounting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The rack of a battery model for one mounting, e.g. PGL1-44H at € 16,70 per cell.
 */
#[Fillable(['battery_model_id', 'mounting', 'code', 'price'])]
class BatteryRack extends Model
{
    /**
     * @return BelongsTo<BatteryModel, $this>
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(BatteryModel::class, 'battery_model_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mounting' => BatteryMounting::class,
            'price' => 'decimal:2',
        ];
    }
}
