<?php

namespace App\Filament\Pages;

use App\Actions\Timesheets\SendWeeklyTimesheets;
use App\Enums\NavigationGroup;
use App\Models\TimesheetWeek;
use App\Support\Timesheets;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * All prestation sheets of a week: hours, overtime, kilometres and whether the week was sent,
 * for administrators and "timesheets.see_all".
 */
class TimesheetOverview extends Page
{
    protected string $view = 'filament.pages.timesheet-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'timesheet-overview';

    #[Url]
    public ?string $week = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return modules()->timesheets() && $user && ($user->isAdmin() || $user->hasPermission('timesheets.see_all'));
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.timesheets.overview');
    }

    public function getTitle(): string
    {
        return __('erp.timesheets.overview');
    }

    public function mount(): void
    {
        $this->week = Timesheets::monday(rescue(fn (): CarbonImmutable => CarbonImmutable::parse((string) $this->week), null, false) ?? today())->toDateString();
    }

    public function monday(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->week);
    }

    public function previousWeek(): void
    {
        $this->week = $this->monday()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = $this->monday()->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->week = Timesheets::monday(today())->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $timesheets = app(Timesheets::class);
        $monday = $this->monday();
        $sent = TimesheetWeek::query()->whereDate('week_start', $monday->toDateString())->whereNotNull('sent_at')->pluck('sent_at', 'user_id');

        return [
            'monday' => $monday,
            'rows' => app(SendWeeklyTimesheets::class)->employees($monday)->map(fn ($user): array => [
                'user' => $user,
                'week' => $timesheets->week($user, $monday),
                'sent_at' => $sent[$user->id] ?? null,
            ])->all(),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label(__('erp.timesheets.send_all'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => auth()->user()->isAdmin() || auth()->user()->hasPermission('timesheets.send'))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('erp.timesheets.send_week_help', ['recipients' => implode(', ', app(Timesheets::class)->recipients()) ?: '—']))
                ->action(function (): void {
                    $result = app(SendWeeklyTimesheets::class)->handle($this->monday());

                    $result['sent']
                        ? Notification::make()->title(__('erp.timesheets.sent_count', ['count' => $result['count']]))->success()->send()
                        : Notification::make()->title(__('erp.timesheets.not_sent'))->body($result['error'])->danger()->send();
                }),
        ];
    }
}
