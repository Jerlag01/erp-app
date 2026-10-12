<?php

namespace App\Models;

use App\Enums\AssetCheckResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of equipment on a yearly control list.
 */
#[Fillable(['asset_check_id', 'asset_id', 'result', 'remark', 'model', 'serial_number', 'checked_at', 'checked_by'])]
class AssetCheckItem extends Model
{
    /**
     * @return BelongsTo<AssetCheck, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(AssetCheck::class, 'asset_check_id');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /**
     * Records the outcome. OK sets the control date of the equipment to today and the next control
     * a category interval (or a year) later.
     */
    public function record(?AssetCheckResult $result, User $user): void
    {
        $this->update([
            'result' => $result,
            'checked_at' => $result ? now() : null,
            'checked_by' => $result ? $user->id : null,
        ]);

        if ($result === AssetCheckResult::Ok && $this->asset) {
            $months = $this->asset->category?->inspection_interval_months ?: 12;

            $this->asset->update([
                'inspection_date' => today(),
                'next_inspection_date' => today()->addMonths($months),
            ]);
        }
    }

    /**
     * Fills in the model or serial number of equipment that had none (field: model|serial_number).
     * Kept on the item too, so the team can still correct what they typed.
     */
    public function complete(string $field, ?string $value): void
    {
        abort_unless(in_array($field, ['model', 'serial_number'], true), 422);

        $value = filled($value) ? mb_substr(trim($value), 0, 100) : null;
        $this->update([$field => $value]);
        $this->asset?->update([$field => $value]);
    }

    /**
     * The team may fill in the model / serial number when the equipment had none, or correct their own.
     */
    public function canComplete(string $field): bool
    {
        return blank($this->asset?->{$field}) || filled($this->{$field});
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => AssetCheckResult::class,
            'checked_at' => 'datetime',
        ];
    }
}
