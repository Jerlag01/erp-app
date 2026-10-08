<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ProjectCategory;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * One tab per category, like folders, with the number of visible projects in it.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = ProjectResource::getEloquentQuery()
            ->toBase()
            ->selectRaw('project_category_id, count(*) as aggregate')
            ->groupBy('project_category_id')
            ->pluck('aggregate', 'project_category_id');

        $tabs = ['all' => Tab::make(__('erp.projects.all'))->badge($counts->sum() ?: null)];

        $categories = ProjectCategory::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $counts->keys()))
            ->orderBy('code')
            ->get();

        foreach ($categories as $category) {
            $tabs[$category->code] = Tab::make($category->code)
                ->badge($counts[$category->id] ?? null)
                ->extraAttributes(['title' => $category->name])
                ->modifyQueryUsing(fn (Builder $query) => $query->where('projects.project_category_id', $category->id));
        }

        return $tabs;
    }
}
