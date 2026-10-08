<?php

namespace App\Filament\Pages;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceType;
use App\Enums\NavigationGroup;
use App\Models\Expense;
use App\Models\Invoice;
use App\Support\FinanceFigures;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The default finance view: per month of a year, the invoices sent, credit notes and incoming
 * invoices with their amounts, and the documents of the chosen month.
 */
class FinanceOverview extends Page
{
    protected static ?string $slug = 'finance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 1;

    protected string $view = 'finance.overview';

    #[Url]
    public ?int $year = null;

    #[Url]
    public ?string $month = null;

    #[Url]
    public bool $vat = false;

    public static function canAccess(): bool
    {
        return modules()->finance() && (bool) auth()->user()?->hasPermission('finance.view');
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.finance.overview.title');
    }

    public function getTitle(): string
    {
        return __('erp.finance.overview.title');
    }

    public function mount(): void
    {
        $this->year = $this->year && $this->year > 2000 && $this->year < 2100 ? $this->year : (int) now()->year;

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $this->month)) {
            $this->month = $this->year === (int) now()->year ? now()->format('Y-m') : "{$this->year}-12";
        }
    }

    public function updatedYear(): void
    {
        $this->month = $this->year === (int) now()->year ? now()->format('Y-m') : "{$this->year}-12";
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function months(): array
    {
        return FinanceFigures::monthly("{$this->year}-01-01", "{$this->year}-12-31", $this->vat);
    }

    /**
     * @return list<int>
     */
    public function years(): array
    {
        $first = min((int) (Invoice::query()->min('issue_date') ? substr((string) Invoice::query()->min('issue_date'), 0, 4) : now()->year), (int) now()->year);

        return range((int) now()->year, $first);
    }

    protected function selectedMonth(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function documents(InvoiceType $type): Collection
    {
        $month = $this->selectedMonth();

        return Invoice::query()->booked()->where('type', $type)
            ->whereBetween('issue_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->with('client')->orderBy('issue_date')->orderBy('number')->get();
    }

    /**
     * @return Collection<int, Expense>
     */
    public function expenses(): Collection
    {
        $month = $this->selectedMonth();

        return Expense::query()->where('status', '!=', ExpenseStatus::Rejected)
            ->whereBetween('invoice_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->orderBy('invoice_date')->get();
    }
}
