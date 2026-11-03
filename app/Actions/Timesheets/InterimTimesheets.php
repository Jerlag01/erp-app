<?php

namespace App\Actions\Timesheets;

use App\Enums\MailTemplateKey;
use App\Filament\Pages\InterimTimesheetVerification;
use App\Mail\SystemMail;
use App\Models\InterimOffice;
use App\Models\TimesheetWeek;
use App\Models\User;
use App\Support\MailTemplates;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * The weekly prestation sheets of interims go to their interim office only after they were verified by
 * someone with "timesheets.verify_interim" (administrators and the role Administratie):
 * - hold(): the sent week waits for its office; those who verify get a notification in the platform and
 *   an e-mail;
 * - send(): the verified sheets of one office and week go to the office's contacts in one e-mail, made
 *   again from the current data (corrections made while checking are in it).
 */
class InterimTimesheets
{
    public const PERMISSION = 'timesheets.verify_interim';

    public function __construct(protected Timesheets $timesheets, protected TimesheetPdf $pdf) {}

    /**
     * Holds the weeks of the interims for their office (unless already sent to it) and tells those who
     * verify. Returns how many sheets are waiting.
     *
     * @param  list<array{office: InterimOffice, users: list<int>}>  $offices
     */
    public function hold(CarbonImmutable $monday, array $offices): int
    {
        $held = 0;

        foreach ($offices as ['office' => $office, 'users' => $userIds]) {
            $held += TimesheetWeek::query()
                ->whereIn('user_id', $userIds)
                ->whereDate('week_start', $monday->toDateString())
                ->whereNull('interim_sent_at')
                ->update(['interim_office_id' => $office->id, 'interim_verified_at' => null, 'interim_verified_by' => null]);
        }

        if ($held > 0) {
            $this->notifyVerifiers($monday);
        }

        return $held;
    }

    /**
     * Active users who verify the interims' weeks.
     *
     * @return Collection<int, User>
     */
    public function verifiers(): Collection
    {
        return User::query()->active()->orderBy('name')->get()->filter(fn (User $user): bool => $user->hasPermission(self::PERMISSION))->values();
    }

    /**
     * A notification in the platform and an e-mail (in their language) to everyone who verifies.
     */
    public function notifyVerifiers(CarbonImmutable $monday): void
    {
        $weeks = TimesheetWeek::query()->awaitingInterimVerification()->whereDate('week_start', $monday->toDateString())->with(['user', 'interimOffice'])->get();

        if ($weeks->isEmpty()) {
            return;
        }

        $url = InterimTimesheetVerification::getUrl(['week' => $monday->toDateString()], panel: 'app');
        $summary = $weeks->groupBy('interim_office_id')
            ->map(fn (Collection $weeks): string => '- **'.MailTemplates::escape((string) $weeks->first()->interimOffice?->name).'**: '.MailTemplates::escape($weeks->map(fn (TimesheetWeek $week): string => (string) $week->user?->name)->sort()->implode(', ')))
            ->implode("\n");

        foreach ($this->verifiers() as $verifier) {
            $locale = $verifier->preferredLocale() ?? config('app.locale');

            Notification::make()
                ->title(__('erp.timesheets.interim.notification_title', ['week' => $monday->isoWeek()], $locale))
                ->body(trans_choice('erp.timesheets.interim.notification_body', $weeks->count(), ['count' => $weeks->count()], $locale))
                ->icon('heroicon-o-clipboard-document-check')
                ->warning()
                ->actions([Action::make('verify')->label(__('erp.timesheets.interim.verify', [], $locale))->url($url)->markAsRead()])
                ->sendToDatabase($verifier);

            if (blank($verifier->email) || filter_var($verifier->email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            try {
                Mail::to($verifier->email, $verifier->name)->send(new SystemMail(MailTemplateKey::TimesheetInterimVerify, $locale, [
                    'name' => $verifier->name,
                    'week' => (string) $monday->isoWeek(),
                    'from' => $monday->format('d/m/Y'),
                    'until' => $monday->addDays(4)->format('d/m/Y'),
                    'count' => (string) $weeks->count(),
                    'summary' => new HtmlString($summary),
                    'url' => $url,
                ]));
            } catch (Throwable $exception) {
                Log::warning('The interim verification e-mail could not be sent', ['user' => $verifier->id, 'week' => $monday->toDateString(), 'error' => $exception->getMessage()]);
            }
        }
    }

    /**
     * Verifies the waiting weeks of one office and week and mails them to the office's contacts.
     *
     * @param  list<int>|null  $weekIds  only these weeks (of that office), or all that are waiting
     * @return int how many sheets were sent
     */
    public function send(InterimOffice $office, CarbonImmutable $monday, User $verifier, ?array $weekIds = null): int
    {
        $weeks = TimesheetWeek::query()
            ->awaitingInterimVerification()
            ->where('interim_office_id', $office->id)
            ->whereDate('week_start', $monday->toDateString())
            ->when($weekIds !== null, fn ($query) => $query->whereKey($weekIds))
            ->with('user')
            ->get()
            ->filter(fn (TimesheetWeek $week): bool => $week->user !== null);

        $contacts = $office->load('contacts')->contactAddresses();

        if ($weeks->isEmpty()) {
            return 0;
        }

        if ($contacts === []) {
            throw new \DomainException(__('erp.timesheets.interim.no_contacts', ['office' => $office->name]));
        }

        $files = [];
        $summary = [];

        foreach ($weeks->sortBy(fn (TimesheetWeek $week): string => mb_strtolower($week->user->name)) as $week) {
            $files[] = ['data' => $this->pdf->render($week->user, $monday, config('app.locale')), 'name' => TimesheetPdf::filename($week->user, $monday), 'mime' => 'application/pdf'];
            $summary[] = SendWeeklyTimesheets::summaryLine($week->user, $this->timesheets->week($week->user, $monday));
        }

        Mail::to(collect($contacts)->map(fn (string $name, string $email): array => ['email' => $email, 'name' => $name])->values()->all())
            ->send(SendWeeklyTimesheets::weekMail($monday, $summary, $files));

        TimesheetWeek::query()->whereKey($weeks->modelKeys())->update([
            'interim_verified_at' => now(),
            'interim_verified_by' => $verifier->id,
            'interim_sent_at' => now(),
        ]);

        return $weeks->count();
    }
}
