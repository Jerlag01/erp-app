<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\WasteEntry;
use App\Support\WasteTotals;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The total weight per waste category and subcategory, with the waste code, over a period
 * (this year by default), also as PDF. Shared by the employee and admin panels.
 */
class WasteTotalsOverview extends Page
{
    protected static ?string $slug = 'waste-totals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Waste;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.waste-totals';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    public static function canAccess(): bool
    {
        return auth()->check() && Gate::allows('viewAny', WasteEntry::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.waste.totals');
    }

    public function getTitle(): string
    {
        return __('erp.waste.totals');
    }

    public function mount(): void
    {
        if ($this->from === null && $this->until === null) {
            $this->setYear((int) now()->year);
        }
    }

    public function setYear(int $year): void
    {
        $this->from = "{$year}-01-01";
        $this->until = "{$year}-12-31";
    }

    public function clearPeriod(): void
    {
        $this->from = null;
        $this->until = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function totals(): array
    {
        return WasteTotals::between($this->validDate($this->from), $this->validDate($this->until));
    }

    protected function validDate(?string $date): ?string
    {
        return $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    /**
     * @return list<int>
     */
    public function years(): array
    {
        $first = (int) (WasteEntry::query()->min('date') ? substr((string) WasteEntry::query()->min('date'), 0, 4) : now()->year);

        return range((int) now()->year, min($first, (int) now()->year));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label(__('erp.waste.export_pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (): string => route('waste.totals-pdf', array_filter(['from' => $this->validDate($this->from), 'until' => $this->validDate($this->until)])), shouldOpenInNewTab: true),
        ];
    }
}
