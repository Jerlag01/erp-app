<?php

namespace App\Filament\Resources\Asbuilts\Pages;

use App\Enums\AsbuiltStatus;
use App\Filament\Resources\Asbuilts\AsbuiltResource;
use App\Models\Asbuilt;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAsbuilts extends ListRecords
{
    protected static string $resource = AsbuiltResource::class;

    /**
     * Per status, with the open ones first.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Asbuilt::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tabs = ['all' => Tab::make(__('erp.asbuilt.all'))];

        foreach (AsbuiltStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->badge((int) ($counts[$status->value] ?? 0) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return $tabs;
    }
}
