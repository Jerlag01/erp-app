<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ProjectCategory;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
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
            ->whereNull('projects.archived_at')
            ->toBase()
            ->selectRaw('project_category_id, count(*) as aggregate')
            ->groupBy('project_category_id')
            ->pluck('aggregate', 'project_category_id');

        $tabs = ['all' => Tab::make(__('erp.projects.all'))->badge($counts->sum() ?: null)->modifyQueryUsing(fn (Builder $query) => $query->whereNull('projects.archived_at'))];

        $categories = ProjectCategory::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $counts->keys()))
            ->orderBy('code')
            ->get();

        foreach ($categories as $category) {
            $tabs[$category->code] = Tab::make($category->code)
                ->badge($counts[$category->id] ?? null)
                ->extraAttributes(['title' => $category->name])
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('projects.archived_at')->where('projects.project_category_id', $category->id));
        }

        // Closed projects only show here.
        $tabs['archived'] = Tab::make(__('erp.projects.archive.tab'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->badge(ProjectResource::getEloquentQuery()->whereNotNull('projects.archived_at')->count() ?: null)
            ->badgeColor('gray')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('projects.archived_at'));

        return $tabs;
    }
}
