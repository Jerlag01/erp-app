<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\DeliveryFollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A delivery followed up for a project: Enersys batteries of a 605 project (ordered by the client
 * itself; delivered moves the project to "to be planned") or an electrical board of a 610–650 project
 * from a board supplier (no status change).
 */
#[Fillable([
    'kind', 'board_supplier_id', 'project_id', 'po_number', 'delivery_date', 'delivered', 'delivered_on', 'delivered_by',
    'configured_on', 'completed', 'completed_at', 'notes', 'created_by',
])]
class DeliveryFollowUp extends Model
{
    /** @use HasFactory<DeliveryFollowUpFactory> */
    use HasFactory;

    public const ENERSYS = 'enersys';

    public const BOARD = 'board';

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<BoardSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(BoardSupplier::class, 'board_supplier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * @param  Builder<DeliveryFollowUp>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // Each kind follows its own area's "all_teams" (Enersys deliveries or electrical boards).
        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('delivery_follow_ups.kind', self::ENERSYS)->whereHas('project', fn (Builder $query) => $query->visibleTo($user, 'enersys')))
            ->orWhere(fn (Builder $query) => $query->where('delivery_follow_ups.kind', '!=', self::ENERSYS)->whereHas('project', fn (Builder $query) => $query->visibleTo($user, 'board_deliveries'))));
    }

    /**
     * @param  Builder<DeliveryFollowUp>  $query
     */
    public function scopeKind(Builder $query, string $kind): void
    {
        $query->where('delivery_follow_ups.kind', $kind);
    }

    public function isEnersys(): bool
    {
        return $this->kind === self::ENERSYS;
    }

    public function isConfigured(): bool
    {
        return ! $this->isEnersys() && (bool) $this->supplier?->isConfigured();
    }

    /**
     * "55MUN - Munkzwalm": COW code and city of the project's site.
     */
    public function location(): string
    {
        $site = $this->project?->workSite;

        return trim(implode(' - ', array_filter([$site?->cow_code ?? $this->project?->cow_code, $site?->city])));
    }

    /**
     * Ticked "delivered": today as actual delivery date. Enersys batteries move their project to "to be
     * planned", unless it is already further (planned, executed …).
     */
    public function markDelivered(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->forceFill(['delivered' => true, 'delivered_on' => $this->delivered_on ?? today(), 'delivered_by' => $user->id])->save();

            if ($this->isEnersys() && in_array($this->project?->status, self::beforePlanning(), true)) {
                $this->project->update(['status' => ProjectStatus::ToBePlanned]);
            }
        });
    }

    /**
     * Unticking clears the actual delivery date; the project keeps its status.
     */
    public function unmarkDelivered(): void
    {
        $this->forceFill(['delivered' => false, 'delivered_on' => null, 'delivered_by' => null])->save();
    }

    public function markCompleted(bool $completed): void
    {
        $this->forceFill(['completed' => $completed, 'completed_at' => $completed ? now() : null])->save();
    }

    /**
     * The statuses before "to be planned".
     *
     * @return list<ProjectStatus>
     */
    public static function beforePlanning(): array
    {
        return [ProjectStatus::Offer, ProjectStatus::WaitingForPo];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'delivered' => 'boolean',
            'delivered_on' => 'date',
            'configured_on' => 'date',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }
}
