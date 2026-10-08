<?php

namespace App\Actions\It;

use App\Enums\ItLogAction;
use App\Models\ItAsset;
use App\Models\ItLog;

/**
 * Confirms an asset was physically seen today and plans the next audit.
 */
class AuditAsset
{
    public function handle(ItAsset $asset, ?string $nextAuditDate = null, ?int $locationId = null, ?string $note = null): void
    {
        $asset->update(array_filter([
            'last_audit_date' => today(),
            'next_audit_date' => $nextAuditDate ?? today()->addMonthsNoOverflow(settings()->itAuditMonths()),
            'location_id' => $locationId,
        ], fn (mixed $value): bool => $value !== null));

        ItLog::record($asset, ItLogAction::Audit, $asset->location, $note);
    }
}
