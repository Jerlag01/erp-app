<?php

namespace App\Actions\It;

use App\Enums\ItLogAction;
use App\Models\ItAsset;
use App\Models\ItLicense;
use App\Models\ItLicenseSeat;
use App\Models\ItLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gives a free seat of a license to a user or an asset, and takes seats back.
 */
class CheckoutLicenseSeat
{
    public function handle(ItLicense $license, User|ItAsset $target, ?string $note = null): ItLicenseSeat
    {
        return DB::transaction(function () use ($license, $target, $note): ItLicenseSeat {
            $seat = $license->licenseSeats()->whereNull('user_id')->whereNull('it_asset_id')->orderBy('id')->lockForUpdate()->first();

            if ($seat === null) {
                throw ValidationException::withMessages(['target' => __('erp.it.errors.no_seats')]);
            }

            $seat->update([
                'user_id' => $target instanceof User ? $target->id : null,
                'it_asset_id' => $target instanceof ItAsset ? $target->id : null,
                'assigned_at' => now(),
                'note' => $note,
            ]);

            ItLog::record($license, ItLogAction::Checkout, $target, $note);

            return $seat;
        });
    }

    public function checkin(ItLicenseSeat $seat, ?string $note = null): void
    {
        if (! $seat->license->reassignable) {
            throw ValidationException::withMessages(['seat' => __('erp.it.errors.not_reassignable')]);
        }

        $previous = $seat->user ?? $seat->asset;
        $seat->update(['user_id' => null, 'it_asset_id' => null, 'assigned_at' => null, 'note' => null]);

        ItLog::record($seat->license, ItLogAction::Checkin, $previous, $note);
    }
}
