<?php

namespace App\Actions\It;

use App\Enums\ItLogAction;
use App\Models\ItAsset;
use App\Models\ItLog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hands an asset to a user, a location or another asset. Only deployable assets that are
 * not checked out yet can go out.
 */
class CheckoutAsset
{
    public function handle(ItAsset $asset, User|Location|ItAsset $target, ?string $expectedCheckin = null, ?string $note = null): void
    {
        DB::transaction(function () use ($asset, $target, $expectedCheckin, $note): void {
            $locked = ItAsset::query()->whereKey($asset->id)->lockForUpdate()->with('status')->firstOrFail();

            if ($locked->isCheckedOut()) {
                throw ValidationException::withMessages(['target' => __('erp.it.errors.already_out', ['name' => $locked->assignedName()])]);
            }

            if (! $locked->isDeployable()) {
                throw ValidationException::withMessages(['target' => __('erp.it.errors.not_deployable', ['status' => $locked->status?->name])]);
            }

            if ($target instanceof ItAsset && ($target->is($locked) || $target->assigned_type === ItAsset::class && $target->assigned_id === $locked->id)) {
                throw ValidationException::withMessages(['target' => __('erp.it.errors.self')]);
            }

            $locked->update([
                'assigned_type' => $target->getMorphClass(),
                'assigned_id' => $target->getKey(),
                'assigned_at' => now(),
                'expected_checkin' => $expectedCheckin,
                // Like Snipe-IT: an asset handed to a location is kept there from then on.
                'location_id' => $target instanceof Location ? $target->id : $locked->location_id,
            ]);

            ItLog::record($locked, ItLogAction::Checkout, $target, $note);
            $asset->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * @param  array{target_type: string, user_id?: int|null, location_id?: int|null, asset_id?: int|null}  $data
     */
    public static function resolveTarget(array $data): Model
    {
        return match ($data['target_type']) {
            'user' => User::findOrFail($data['user_id'] ?? 0),
            'location' => Location::findOrFail($data['location_id'] ?? 0),
            'asset' => ItAsset::findOrFail($data['asset_id'] ?? 0),
        };
    }
}
