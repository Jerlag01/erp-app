<?php

namespace App\Filament\Admin\Resources\InterventionReports\Pages;

use App\Enums\InterventionReportStatus;
use App\Filament\Admin\Resources\InterventionReports\InterventionReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInterventionReports extends ListRecords
{
    protected static string $resource = InterventionReportResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (InterventionReportStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status->value));
        }

        return [...$tabs, 'all' => Tab::make(__('erp.projects.all'))];
    }
}
