<?php

namespace App\Filament\Resources\WasteEntries\Pages;

use App\Enums\WasteRegion;
use App\Filament\Resources\WasteEntries\WasteEntryResource;
use App\Http\Controllers\WastePdfController;
use App\Models\WasteEntry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageWasteEntries extends ManageRecords
{
    protected static string $resource = WasteEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The current list, with its filters and sorting, as a PDF.
            Action::make('exportPdf')
                ->label(__('erp.waste.export_pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()->can('viewAny', WasteEntry::class))
                ->action(fn () => $this->js('window.open('.json_encode(WastePdfController::urlFor(
                    (string) $this->activeTab,
                    $this->getFilteredSortedTableQuery()->pluck('waste_entries.id')->all(),
                    $this->tableFilters['period'] ?? [],
                )).', "_blank")')),
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
        ];
    }

    /**
     * The full registry and the battery list of each region.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('erp.waste.registry'))->icon(Heroicon::OutlinedListBullet),
        ];

        $counts = WasteEntry::query()->whereNotNull('region')->selectRaw('region, count(*) as total')->groupBy('region')->pluck('total', 'region');

        foreach (WasteRegion::cases() as $region) {
            $tabs[$region->value] = Tab::make($region->getLabel())
                ->icon(Heroicon::OutlinedBattery50)
                ->badge((int) ($counts[$region->value] ?? 0) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => self::regionQuery($query, $region));
        }

        return $tabs;
    }

    /**
     * Battery waste of one region.
     *
     * @param  Builder<WasteEntry>  $query
     * @return Builder<WasteEntry>
     */
    public static function regionQuery(Builder $query, WasteRegion $region): Builder
    {
        return $query->where('region', $region->value)->whereHas('category', fn (Builder $query) => $query->where('is_batteries', true));
    }
}
