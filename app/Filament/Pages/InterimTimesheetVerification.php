<?php

namespace App\Filament\Pages;

use App\Actions\Timesheets\InterimTimesheets;
use App\Enums\NavigationGroup;
use App\Models\InterimOffice;
use App\Models\TimesheetWeek;
use App\Support\Timesheets;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * The weekly prestation sheets of interims waiting for verification, per week and interim office. After
 * checking (and correcting) them, "Verify and send" mails them to the office's contacts. For
 * administrators and "timesheets.verify_interim" (the role Administratie).
 */
class InterimTimesheetVerification extends Page
{
    protected string $view = 'filament.pages.interim-timesheet-verification';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'interim-timesheets';

    /** The Monday the notification pointed to, shown first. */
    #[Url]
    public ?string $week = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return modules()->timesheets() && modules()->employees() && $user !== null && $user->hasPermission(InterimTimesheets::PERMISSION);
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.timesheets.interim.title');
    }

    public function getTitle(): string
    {
        return __('erp.timesheets.interim.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TimesheetWeek::query()->awaitingInterimVerification()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /**
     * Waiting weeks per Monday and office, the newest week first.
     *
     * @return Collection<int, array{monday: CarbonImmutable, office: InterimOffice, rows: list<array<string, mixed>>}>
     */
    public function groups(): Collection
    {
        $timesheets = app(Timesheets::class);

        return TimesheetWeek::query()
            ->awaitingInterimVerification()
            ->with(['user', 'interimOffice.contacts'])
            ->orderByDesc('week_start')
            ->get()
            ->filter(fn (TimesheetWeek $week): bool => $week->user !== null && $week->interimOffice !== null)
            ->groupBy(fn (TimesheetWeek $week): string => $week->week_start->toDateString().'|'.$week->interim_office_id)
            ->map(function (Collection $weeks) use ($timesheets): array {
                $monday = CarbonImmutable::parse($weeks->first()->week_start);

                return [
                    'monday' => $monday,
                    'office' => $weeks->first()->interimOffice,
                    'rows' => $weeks->sortBy(fn (TimesheetWeek $week): string => mb_strtolower($week->user->name))
                        ->map(fn (TimesheetWeek $week): array => ['week' => $week, 'user' => $week->user, 'data' => $timesheets->week($week->user, $monday)])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $group): string => ($group['monday']->toDateString() === $this->week ? '1' : '0').$group['monday']->toDateString())
            ->values();
    }

    /**
     * The interim weeks sent to their office in the last 30 days.
     *
     * @return Collection<int, TimesheetWeek>
     */
    public function recentlySent(): Collection
    {
        return TimesheetWeek::query()
            ->whereNotNull('interim_sent_at')
            ->where('interim_sent_at', '>=', now()->subDays(30))
            ->with(['user', 'interimOffice', 'interimVerifier'])
            ->latest('interim_sent_at')
            ->limit(50)
            ->get();
    }

    /**
     * Verifies the waiting sheets of one office and week and mails them to the office's contacts.
     */
    public function verifyAction(): Action
    {
        return Action::make('verify')
            ->label(__('erp.timesheets.interim.verify_send'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('erp.timesheets.interim.verify_heading', ['office' => InterimOffice::query()->whereKey($arguments['office'] ?? 0)->value('name')]))
            ->modalDescription(fn (array $arguments): string => __('erp.timesheets.interim.verify_help', [
                'recipients' => implode(', ', array_keys(InterimOffice::query()->with('contacts')->find($arguments['office'] ?? 0)?->contactAddresses() ?? [])) ?: '—',
            ]))
            ->action(function (array $arguments, InterimTimesheets $interims): void {
                abort_unless(self::canAccess(), 403);
                $office = InterimOffice::query()->findOrFail($arguments['office'] ?? 0);

                try {
                    $count = $interims->send($office, CarbonImmutable::parse((string) ($arguments['week'] ?? '')), auth()->user());
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title(__('erp.timesheets.interim.send_failed'))->body($exception instanceof \DomainException ? $exception->getMessage() : null)->danger()->send();

                    return;
                }

                Notification::make()->title(trans_choice('erp.timesheets.interim.sent', $count, ['count' => $count, 'office' => $office->name]))->success()->send();
            });
    }
}
