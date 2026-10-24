<?php

namespace App\Filament\Resources\WasteEntryLines\Pages;

use App\Enums\WasteRegion;
use App\Filament\Resources\WasteEntries\WasteEntryResource;
use App\Filament\Resources\WasteEntryLines\WasteEntryLineResource;
use App\Models\WasteEntryLine;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListWasteEntryLines extends ListRecords
{
    protected static string $resource = WasteEntryLineResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->activeTab ??= WasteRegion::Flanders->value;
    }

    /**
     * A tab per region.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = WasteEntryLine::query()->selectRaw('region, count(*) as total')->groupBy('region')->pluck('total', 'region');

        return collect(WasteRegion::cases())->mapWithKeys(fn (WasteRegion $region): array => [$region->value => Tab::make($region->getLabel())
            ->badge((int) ($counts[$region->value] ?? 0) ?: null)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('waste_entry_lines.region', $region->value))])->all();
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return WasteRegion::Flanders->value;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Per year: the list (landscape) followed by the PDF of each entry (portrait).
            Action::make('export')
                ->label(__('erp.waste.export_regional'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'waste'))
                ->modalHeading(fn (): string => __('erp.waste.export_regional_heading', ['region' => WasteRegion::tryFrom((string) $this->activeTab)?->getLabel()]))
                ->modalDescription(__('erp.waste.export_regional_help'))
                ->modalSubmitActionLabel(__('erp.waste.export'))
                ->fillForm(fn (): array => ['year' => $this->tableFilters['year']['value'] ?? now()->year])
                ->schema([
                    Select::make('year')->label(__('erp.waste.year'))->options(fn (): array => WasteEntryResource::yearOptions())->required(),
                ])
                ->action(fn (array $data) => $this->js('window.open('.json_encode(route('waste.regional-pdf', [
                    'region' => WasteRegion::tryFrom((string) $this->activeTab)?->value ?? WasteRegion::Flanders->value,
                    'year' => (int) $data['year'],
                ])).', "_blank")')),
        ];
    }
}
