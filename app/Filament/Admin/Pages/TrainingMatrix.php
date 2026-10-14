<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NavigationGroup;
use App\Support\TrainingMatrix as Matrix;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * "Opleidingen & medisch onderzoek": the certificates and medical check-ups of all employees at a glance.
 */
class TrainingMatrix extends Page
{
    protected string $view = 'filament.admin.pages.training-matrix';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'training-overview';

    #[Url]
    public string $search = '';

    #[Url(as: 'problems')]
    public bool $onlyProblems = false;

    public static function canAccess(): bool
    {
        return modules()->employees() && (bool) auth()->user()?->isAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.training_matrix.title');
    }

    public function getTitle(): string
    {
        return __('erp.training_matrix.title');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['matrix' => app(Matrix::class)->build($this->search, $this->onlyProblems)];
    }
}
