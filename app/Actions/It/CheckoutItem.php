<?php

namespace App\Actions\It;

use App\Enums\ItItemKind;
use App\Enums\ItLogAction;
use App\Models\ItAsset;
use App\Models\ItItem;
use App\Models\ItItemAssignment;
use App\Models\ItLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hands out accessories (to a user), consumables (to a user, used up) and components
 * (built into an asset), and takes accessories and components back.
 */
class CheckoutItem
{
    public function handle(ItItem $item, User|ItAsset $target, int $quantity = 1, ?string $note = null): ItItemAssignment
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => __('erp.it.errors.quantity')]);
        }

        if (($item->kind === ItItemKind::Component) !== ($target instanceof ItAsset)) {
            throw ValidationException::withMessages(['target' => __('erp.it.errors.wrong_target')]);
        }

        return DB::transaction(function () use ($item, $target, $quantity, $note): ItItemAssignment {
            $locked = ItItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($quantity > $locked->available()) {
                throw ValidationException::withMessages(['quantity' => __('erp.it.errors.not_enough', ['available' => $locked->available()])]);
            }

            $consumable = $locked->kind === ItItemKind::Consumable;

            if ($consumable) {
                $locked->decrement('quantity', $quantity);
            }

            $assignment = $locked->assignments()->create([
                'user_id' => $target instanceof User ? $target->id : null,
                'it_asset_id' => $target instanceof ItAsset ? $target->id : null,
                'quantity' => $quantity,
                'assigned_at' => now(),
                // Consumables are gone once handed out, so they are closed straight away.
                'returned_at' => $consumable ? now() : null,
                'note' => $note,
                'created_by' => auth()->id(),
            ]);

            ItLog::record($locked, match ($locked->kind) {
                ItItemKind::Consumable => ItLogAction::Consume,
                ItItemKind::Component => ItLogAction::Install,
                ItItemKind::Accessory => ItLogAction::Checkout,
            }, $target, $note, $quantity);

            $item->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $assignment;
        });
    }

    public function checkin(ItItemAssignment $assignment, ?string $note = null): void
    {
        if ($assignment->returned_at !== null) {
            throw ValidationException::withMessages(['assignment' => __('erp.it.errors.already_returned')]);
        }

        $assignment->update(['returned_at' => now()]);

        ItLog::record($assignment->item, $assignment->item->kind === ItItemKind::Component ? ItLogAction::Remove : ItLogAction::Checkin, $assignment->user ?? $assignment->asset, $note, $assignment->quantity);
    }
}
