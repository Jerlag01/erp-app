<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\User;
use App\Policies\ToolboxMeetingPolicy;
use App\Support\ToolboxMatrix;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Follow-up of the toolbox meetings: who signed which meeting of a year, like the overview of trainings
 * and medical check-ups. For "toolbox.view_signatures".
 */
class ToolboxOverview extends Page
{
    protected string $view = 'filament.pages.toolbox-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Safety;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'toolbox-overview';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $year = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ToolboxMeetingPolicy::seesSignatures($user);
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.toolbox.overview.title');
    }

    public function getTitle(): string
    {
        return __('erp.toolbox.overview.title');
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
        $this->year ??= (int) date('Y');

        return ['matrix' => app(ToolboxMatrix::class)->build($this->year, $this->search)];
    }
}
