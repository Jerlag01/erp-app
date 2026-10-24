<?php

namespace App\Filament\Resources\EnersysDeliveries;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * Projects to pick for a delivery follow-up: of the given categories and visible to the user.
 */
class FollowUpProjects
{
    /**
     * @param  list<string>  $categories
     * @return array<int, string>
     */
    public static function options(array $categories, ?int $include = null): array
    {
        return Project::query()
            ->visibleTo(auth()->user())
            ->where(fn (Builder $query) => $query
                ->whereHas('category', fn (Builder $query) => $query->whereIn('code', $categories))
                ->when($include, fn (Builder $query, int $id) => $query->orWhere('projects.id', $id)))
            ->with('category')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' '.($project->subtitle() ? '— '.$project->subtitle() : ''))])
            ->all();
    }

    /**
     * The first PO number of the project, to start with.
     */
    public static function poNumber(?int $projectId): ?string
    {
        return $projectId ? Project::query()->find($projectId)?->poNumbers()->value('number') : null;
    }
}
