<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['it_license_id', 'user_id', 'it_asset_id', 'assigned_at', 'note'])]
class ItLicenseSeat extends Model
{
    /**
     * @return BelongsTo<ItLicense, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(ItLicense::class, 'it_license_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ItAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    public function isFree(): bool
    {
        return $this->user_id === null && $this->it_asset_id === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
