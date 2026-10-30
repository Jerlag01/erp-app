<?php

namespace App\Actions;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockCount;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Raises or lowers a stock level and logs the movement. The row is locked while
 * changing, so two people scanning the same item at once cannot lose an update.
 * During the yearly count only counting is allowed (setTo). When a row drops to or below its
 * minimum, administrators and those who manage stock items get a notification in the platform.
 */
class AdjustStock
{
    public function __construct(protected StockCount $count) {}

    public function handle(StockLevel $level, float $change, User $user, ?string $note = null): StockMovement
    {
        if ($this->count->active()) {
            throw ValidationException::withMessages(['quantity' => __('erp.stock.yearly_count.blocked')]);
        }

        return $this->change($level, $change, $user, $note);
    }

    /**
     * Sets the counted quantity (stocktake) and logs the difference. During the yearly count the row is
     * marked as counted, also when nothing changed.
     */
    public function setTo(StockLevel $level, float $quantity, User $user, ?string $note = null): ?StockMovement
    {
        if ($quantity < 0) {
            throw ValidationException::withMessages(['quantity' => __('erp.stock.errors.negative', ['available' => $level->item->unit->format(0)])]);
        }

        $difference = round($quantity - (float) $level->quantity, 3);
        $movement = $difference == 0.0 ? null : $this->change($level, $difference, $user, $note);

        if ($this->count->active()) {
            $level->update(['counted_at' => now()]);
        }

        return $movement;
    }

    protected function change(StockLevel $level, float $change, User $user, ?string $note): StockMovement
    {
        $unit = $level->item->unit;

        if ($change == 0.0) {
            throw ValidationException::withMessages(['quantity' => __('erp.stock.errors.zero')]);
        }

        if (! $unit->allows_decimals && floor($change) != $change) {
            throw ValidationException::withMessages(['quantity' => __('erp.stock.errors.whole', ['unit' => $unit->name])]);
        }

        $wasLow = $level->isLow();

        $movement = DB::transaction(function () use ($level, $change, $user, $note): StockMovement {
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

        if (! $wasLow && $level->isLow()) {
            $this->notifyLow($level);
        }

        return $movement;
    }

    /**
     * Only in the platform (the bell), never by e-mail.
     */
    protected function notifyLow(StockLevel $level): void
    {
        $level->loadMissing(['item.unit', 'location']);

        User::query()->active()->get()
            ->filter(fn (User $user): bool => $user->isAdmin() || $user->hasPermission('stock_items.update'))
            ->each(function (User $user) use ($level): void {
                $locale = $user->preferredLocale();

                Notification::make()
                    ->title(__('erp.stock.low_notification.title', ['item' => $level->item->localizedName($locale), 'location' => $level->location->name], $locale))
                    ->body(__('erp.stock.low_notification.body', [
                        'quantity' => $level->item->unit->format($level->quantity),
                        'minimum' => $level->item->unit->format($level->threshold()),
                    ], $locale))
                    ->icon('heroicon-o-bell-alert')
                    ->danger()
                    ->actions([Action::make('open')->label(__('erp.stock.low_notification.open', [], $locale))->url(StockItemResource::getUrl('view', ['record' => $level->item], panel: 'app'))])
                    ->sendToDatabase($user);
            });
    }
}
