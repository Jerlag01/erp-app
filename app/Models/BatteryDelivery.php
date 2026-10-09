<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\BatteryDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One line of the "Overzicht Levering Batterijen": a battery order for a 604 project. The location
 * and order number come from the project. Ticking "delivered" stamps the system date and time and
 * moves the project to the status set in Settings > Batteries (to be executed, i.e. ready to plan).
 */
#[Fillable(['project_id', 'order_confirmation', 'battery_model_id', 'pieces', 'planned_delivery_date', 'executed', 'total_cost', 'notes'])]
class BatteryDelivery extends Model
{
    /** @use HasFactory<BatteryDeliveryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<BatteryModel, $this>
     */
    public function batteryModel(): BelongsTo
    {
        return $this->belongsTo(BatteryModel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * @param  Builder<BatteryDelivery>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('project', fn (Builder $query) => $query->visibleTo($user));
    }

    /**
     * "55MUN - Munkzwalm": the COW code of the project's work site and its city.
     */
    public function location(): string
    {
        $site = $this->project?->workSite;
        $cow = $site?->cow_code ?? $this->project?->cow_code;

        return trim(implode(' - ', array_filter([$cow, $site?->city])));
    }

    /**
     * The order number: the project number within its category, e.g. "764" for 604-764-55MUN.
     */
    public function orderNumber(): string
    {
        $project = $this->project;

        return $project?->category ? $project->category->numbering->formatNumber((int) $project->number) : (string) $project?->number;
    }

    public function markDelivered(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->forceFill(['delivered' => true, 'delivered_at' => now(), 'delivered_by' => $user->id])->save();

            if ($status = self::deliveredProjectStatus()) {
                $this->project->update(['status' => $status]);
            }
        });
    }

    /**
     * Unticking clears the delivery date; the project keeps its status.
     */
    public function unmarkDelivered(): void
    {
        $this->forceFill(['delivered' => false, 'delivered_at' => null, 'delivered_by' => null])->save();
    }

    public static function deliveredProjectStatus(): ?ProjectStatus
    {
        return ProjectStatus::tryFrom((string) settings()->get('batteries.delivered_project_status'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pieces' => 'integer',
            'planned_delivery_date' => 'date',
            'delivered' => 'boolean',
            'delivered_at' => 'datetime',
            'executed' => 'boolean',
            'total_cost' => 'decimal:2',
        ];
    }
}
