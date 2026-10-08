<?php

namespace App\Actions\It;

use App\Enums\ItLogAction;
use App\Models\ItAsset;
use App\Models\ItLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes an asset back, optionally with a new status (e.g. "Broken") and location.
 */
class CheckinAsset
{
    public function handle(ItAsset $asset, ?int $statusId = null, ?int $locationId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($asset, $statusId, $locationId, $note): void {
            $locked = ItAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isCheckedOut()) {
                throw ValidationException::withMessages(['status' => __('erp.it.errors.not_out')]);
            }

            $previous = $locked->assigned;

            $changes = ['assigned_type' => null, 'assigned_id' => null, 'assigned_at' => null, 'expected_checkin' => null];

            if ($statusId !== null) {
                $changes['it_status_label_id'] = $statusId;
            }

            if ($locationId !== null) {
                $changes['location_id'] = $locationId;
            }

            $locked->update($changes);

            ItLog::record($locked, ItLogAction::Checkin, $previous, $note);
            $asset->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
