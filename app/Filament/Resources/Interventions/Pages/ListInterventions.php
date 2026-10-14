<?php

namespace App\Filament\Resources\Interventions\Pages;

use App\Enums\InterventionReportStatus;
use App\Enums\ProjectNumbering;
use App\Filament\Resources\Interventions\InterventionResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\ProjectCategory;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListInterventions extends ListRecords
{
    protected static string $resource = InterventionResource::class;

    protected function getHeaderActions(): array
    {
        // 600 (interventions) and 608.1 (interventions on charging stations) follow the same workflow.
        $categories = ProjectCategory::query()->where('numbering', ProjectNumbering::Intervention->value)->where('is_active', true)->orderBy('code')->get();

        if ($categories->count() <= 1) {
            $category = $categories->first();

            return [
                Action::make('create')
                    ->label(__('erp.interventions.new'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->url($category ? ProjectResource::getUrl('create', ['category' => $category->id]) : null)
                    ->visible(fn (): bool => $category !== null && InterventionResource::canCreate()),
            ];
        }

        return [
            ActionGroup::make($categories->map(fn (ProjectCategory $category): Action => Action::make('create_'.$category->id)
                ->label($category->label())
                ->url(ProjectResource::getUrl('create', ['category' => $category->id])))->all())
                ->label(__('erp.interventions.new'))
                ->icon(Heroicon::OutlinedPlus)
                ->button()
                ->visible(fn (): bool => InterventionResource::canCreate()),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make(__('erp.interventions.tabs.open'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->doesntHave('interventionReport')
                    ->orWhereHas('interventionReport', fn (Builder $query) => $query->where('status', InterventionReportStatus::Rejected->value)))),
            'pending' => Tab::make(__('erp.interventions.tabs.pending'))
                ->badge(fn (): ?int => InterventionResource::getEloquentQuery()->whereHas('interventionReport', fn (Builder $query) => $query->where('status', InterventionReportStatus::Pending->value))->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('interventionReport', fn (Builder $query) => $query->where('status', InterventionReportStatus::Pending->value))),
            'approved' => Tab::make(__('erp.interventions.tabs.approved'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('interventionReport', fn (Builder $query) => $query->where('status', InterventionReportStatus::Approved->value))),
            'all' => Tab::make(__('erp.projects.all')),
        ];
    }
}
