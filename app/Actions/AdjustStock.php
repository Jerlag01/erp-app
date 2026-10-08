<?php

namespace App\Actions;

use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Raises or lowers a stock level and logs the movement. The row is locked while
 * changing, so two people scanning the same item at once cannot lose an update.
 */
class AdjustStock
{
    public function handle(StockLevel $level, float $change, User $user, ?string $note = null): StockMovement
    {
        $unit = $level->item->unit;

        if ($change == 0.0) {
            throw ValidationException::withMessages(['quantity' => __('erp.stock.errors.zero')]);
        }

        if (! $unit->allows_decimals && floor($change) != $change) {
            throw ValidationException::withMessages(['quantity' => __('erp.stock.errors.whole', ['unit' => $unit->name])]);
        }

        return DB::transaction(function () use ($level, $change, $user, $note): StockMovement {
            $locked = StockLevel::query()->whereKey($level->id)->lockForUpdate()->firstOrFail();
            $after = round((float) $locked->quantity + $change, 3);

            if ($after < 0) {
                throw ValidationException::withMessages(['quantity' => __('erp.stock.errors.negative', [
                    'available' => $level->item->unit->format($locked->quantity),
                ])]);
            }

            $locked->update(['quantity' => $after]);
            $level->setRawAttributes($locked->getAttributes(), true);

            return StockMovement::create([
                'stock_level_id' => $locked->id,
                'user_id' => $user->id,
                'change' => $change,
                'quantity_after' => $after,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Sets the counted quantity (stocktake) and logs the difference.
     */
    public function setTo(StockLevel $level, float $quantity, User $user, ?string $note = null): ?StockMovement
    {
        $difference = round($quantity - (float) $level->quantity, 3);

        return $difference == 0.0 ? null : $this->handle($level, $difference, $user, $note);
    }
}
