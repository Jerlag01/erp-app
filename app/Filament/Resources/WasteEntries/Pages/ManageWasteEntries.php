<?php

namespace App\Filament\Resources\WasteEntries\Pages;

use App\Enums\WasteRegion;
use App\Filament\Resources\WasteEntries\WasteEntryResource;
use App\Filament\Resources\WasteEntryLines\WasteEntryLineResource;
use App\Http\Controllers\WastePdfController;
use App\Models\WasteEntry;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
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
            // The current list, with its filters and sorting, as a PDF; or every entry of one year.
            Action::make('exportPdf')
                ->label(__('erp.waste.export_pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'waste'))
                ->authorize(fn (): bool => auth()->user()->can('viewAny', WasteEntry::class) && PdfAccess::canGenerate(auth()->user(), 'waste'))
                ->modalHeading(__('erp.waste.export_pdf'))
                ->modalSubmitActionLabel(__('erp.waste.export'))
                ->fillForm(fn (): array => ['year' => $this->tableFilters['year']['value'] ?? null])
                ->schema([
                    Select::make('year')
                        ->label(__('erp.waste.year'))
                        ->placeholder(__('erp.waste.export_current_list'))
                        ->helperText(__('erp.waste.export_year_help'))
                        ->options(fn (): array => WasteEntryResource::yearOptions()),
                ])
                ->action(fn (array $data) => $this->js('window.open('.json_encode($this->exportUrl(filled($data['year'] ?? null) ? (int) $data['year'] : null)).', "_blank")')),
            Action::make('regionalLists')
                ->label(__('erp.waste.regional_lists'))
                ->icon(Heroicon::OutlinedBattery50)
                ->color('gray')
                ->url(WasteEntryLineResource::getUrl()),
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
        ];
    }

    /**
     * The PDF of the list as shown, or of one whole year (in the current tab, with the other filters).
     */
    protected function exportUrl(?int $year): string
    {
        if ($year === null) {
            return WastePdfController::urlFor((string) $this->activeTab, $this->getFilteredSortedTableQuery()->pluck('waste_entries.id')->all(), $this->tableFilters['period'] ?? []);
        }

        // A whole year: the year and period filters make way for it; the tab and the other filters stay.
        $filters = $this->tableFilters;
        $this->tableFilters = [...$filters, 'year' => ['value' => null], 'period' => ['from' => null, 'until' => null]];

        try {
            $ids = $this->getFilteredSortedTableQuery()->whereYear('waste_entries.date', $year)->pluck('waste_entries.id')->all();
        } finally {
            $this->tableFilters = $filters;
        }

        return WastePdfController::urlFor((string) $this->activeTab, $ids, ['from' => "{$year}-01-01", 'until' => "{$year}-12-31"]);
    }

    /**
     * Battery waste with lines in one region.
     *
     * @param  Builder<WasteEntry>  $query
     * @return Builder<WasteEntry>
     */
    public static function regionQuery(Builder $query, WasteRegion $region): Builder
    {
        return $query->whereHas('lines', fn (Builder $query) => $query->where('region', $region->value))->whereHas('category', fn (Builder $query) => $query->where('is_batteries', true));
    }
}
