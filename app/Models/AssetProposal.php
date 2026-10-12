<?php

namespace App\Models;

use App\Enums\AssetCheckResult;
use App\Enums\AssetProposalStatus;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Equipment a team member has but that was not on the yearly control list. It only becomes equipment
 * (and part of the list) once an administrator approves it.
 */
#[Fillable([
    'asset_check_id', 'team_id', 'name', 'asset_category_id', 'brand', 'model', 'serial_number', 'result', 'remark',
    'proposed_by', 'status', 'handled_by', 'handled_at', 'rejection_reason', 'asset_id',
])]
class AssetProposal extends Model
{
    /**
     * @return BelongsTo<AssetCheck, $this>
     */
    public function check(): BelongsTo
    {
        return $this->belongsTo(AssetCheck::class, 'asset_check_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function isPending(): bool
    {
        return $this->status === AssetProposalStatus::Pending;
    }

    /**
     * Creates the equipment for the team and puts it on the control list with the team's result.
     *
     * @param  array<string, mixed>  $attributes  the equipment fields as checked by the administrator
     */
    public function approve(array $attributes, User $by): Asset
    {
        return DB::transaction(function () use ($attributes, $by): Asset {
            $asset = Asset::query()->create([
                'name' => $this->name,
                'asset_category_id' => $this->asset_category_id,
                'brand' => $this->brand,
                'model' => $this->model,
                'serial_number' => $this->serial_number,
                ...$attributes,
                'team_id' => $this->team_id,
                'status' => AssetStatus::InService,
            ]);

            $item = $this->check?->items()->firstOrCreate(['asset_id' => $asset->id], ['remark' => $this->remark]);

            if ($item && $this->result) {
                $item->record($this->result, $this->proposer ?? $by);
            }

            $this->update([
                'status' => AssetProposalStatus::Approved,
                'asset_id' => $asset->id,
                'handled_by' => $by->id,
                'handled_at' => now(),
            ]);

            return $asset;
        });
    }

    public function reject(?string $reason, User $by): void
    {
        $this->update([
            'status' => AssetProposalStatus::Rejected,
            'rejection_reason' => $reason,
            'handled_by' => $by->id,
            'handled_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssetProposalStatus::class,
            'result' => AssetCheckResult::class,
            'handled_at' => 'datetime',
        ];
    }
}
