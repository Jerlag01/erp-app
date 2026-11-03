<?php

namespace App\Actions\Timesheets;

use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\Employee;
use App\Models\InterimOffice;
use App\Models\TimesheetDay;
use App\Models\TimesheetWeek;
use App\Models\User;
use App\Support\MailTemplates;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Sends the prestation sheets of a week: one PDF per employee. Each employee gets their own sheet by
 * e-mail (in their language), and the general addresses of Settings > Prestations get one e-mail with
 * all sheets and a summary per employee. The sheets of interims are held for their interim office until
 * someone with "timesheets.verify_interim" verifies them (InterimTimesheets). The weeks are then marked as
 * sent, so employees can no longer change them.
 */
class SendWeeklyTimesheets
{
    public function __construct(protected Timesheets $timesheets, protected TimesheetPdf $pdf, protected InterimTimesheets $interims) {}

    /**
     * @param  iterable<User>|null  $users  everyone who fills in prestation sheets when null
     * @return array{sent: bool, count: int, personal: int, interim: int, error: ?string} "interim": sheets held for verification
     */
    public function handle(CarbonImmutable $monday, ?iterable $users = null, bool $onlyUnsent = false): array
    {
        $monday = Timesheets::monday($monday);
        $recipients = $this->timesheets->recipients();
        $toEmployees = $this->timesheets->sendsToEmployees();

        $users = collect($users ?? $this->employees($monday))
            ->filter(fn (User $user): bool => ! $onlyUnsent || ! TimesheetWeek::query()->where('user_id', $user->id)->whereDate('week_start', $monday->toDateString())->whereNotNull('sent_at')->exists())
            ->values();
        $offices = $this->interimOffices($users);

        if ($recipients === [] && ! $toEmployees && $offices === []) {
            return ['sent' => false, 'count' => 0, 'personal' => 0, 'interim' => 0, 'error' => __('erp.timesheets.no_recipients')];
        }

        if ($users->isEmpty()) {
            return ['sent' => false, 'count' => 0, 'personal' => 0, 'interim' => 0, 'error' => __('erp.timesheets.nobody_to_send')];
        }

        $files = [];
        $summary = [];
        $personal = [];

        foreach ($users as $user) {
            $week = $this->timesheets->week($user, $monday);
            // The administration's copy in the platform's language; the employee's own in theirs (below).
            $pdf = $this->pdf->render($user, $monday, config('app.locale'));
            $name = TimesheetPdf::filename($user, $monday);
            $path = "timesheets/{$user->id}/{$name}";
            Storage::disk(TimesheetWeek::DISK)->put($path, $pdf);

            $files[$user->id] = ['data' => $pdf, 'name' => $name, 'mime' => 'application/pdf'];
            $summary[$user->id] = self::summaryLine($user, $week);

            TimesheetWeek::query()->updateOrCreate(
                ['user_id' => $user->id, 'week_start' => $monday->toDateString()],
                ['plate_number' => $week['plate'], 'pdf_path' => $path, 'sent_at' => now()],
            );

            $personal[] = ['user' => $user, 'week' => $week, 'file' => $files[$user->id]];
        }

        $sentToEmployees = $toEmployees ? $this->sendToEmployees($monday, $personal) : 0;
        // Interims: held for their office until verified; those who verify are told.
        $sentToOffices = $this->interims->hold($monday, $offices);

        if ($recipients === []) {
            return ['sent' => true, 'count' => $users->count(), 'personal' => $sentToEmployees, 'interim' => $sentToOffices, 'error' => null];
        }

        try {
            Mail::to($recipients)->send(self::weekMail($monday, array_values($summary), array_values($files)));
        } catch (Throwable $exception) {
            Log::error('Weekly timesheets could not be sent', ['week' => $monday->toDateString(), 'error' => $exception->getMessage()]);

            return ['sent' => false, 'count' => 0, 'personal' => $sentToEmployees, 'interim' => $sentToOffices, 'error' => $exception->getMessage()];
        }

        return ['sent' => true, 'count' => $users->count(), 'personal' => $sentToEmployees, 'interim' => $sentToOffices, 'error' => null];
    }

    /**
     * The week e-mail (template "prestations of the week") with the given sheets and summary lines.
     *
     * @param  list<string>  $summary
     * @param  list<array{data: string, name: string, mime: string}>  $files
     */
    public static function weekMail(CarbonImmutable $monday, array $summary, array $files): SystemMail
    {
        return new SystemMail(MailTemplateKey::TimesheetWeek, config('app.locale'), [
            'week' => (string) $monday->isoWeek(),
            'from' => $monday->format('d/m/Y'),
            'until' => $monday->addDays(4)->format('d/m/Y'),
            'count' => (string) count($files),
            'summary' => new HtmlString(implode("\n", $summary)),
        ], $files);
    }

    /**
     * "- **Anna**: 38,00 u · meeruren +0,00 (saldo 2,50) · 120 km", for the week e-mails.
     *
     * @param  array<string, mixed>  $week
     */
    public static function summaryLine(User $user, array $week): string
    {
        return '- **'.MailTemplates::escape($user->name).'**: '.MailTemplates::escape(sprintf(
            '%s u · meeruren %s (saldo %s) · %s km',
            number_format($week['hours'], 2, ',', ''),
            ($week['overtime_now'] >= 0 ? '+' : '').number_format($week['overtime_now'], 2, ',', ''),
            number_format($week['overtime_new'], 2, ',', ''),
            number_format($week['km'], 0, ',', '.'),
        ));
    }

    /**
     * The interim offices (with contacts) of the interim employees among the users, with their user ids.
     *
     * @param  Collection<int, User>  $users
     * @return list<array{office: InterimOffice, users: list<int>}>
     */
    protected function interimOffices(Collection $users): array
    {
        if (! modules()->employees() || $users->isEmpty()) {
            return [];
        }

        return Employee::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('is_interim', true)
            ->whereNotNull('interim_office_id')
            ->whereHas('interimOffice', fn (Builder $query) => $query->whereHas('contacts'))
            ->with('interimOffice.contacts')
            ->get()
            ->groupBy('interim_office_id')
            ->map(fn (Collection $employees): array => ['office' => $employees->first()->interimOffice, 'users' => $employees->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all()])
            ->filter(fn (array $group): bool => $group['office']->contactAddresses() !== [])
            ->values()
            ->all();
    }

    /**
     * Each employee gets their own sheet, in their language. A failed e-mail is logged and does not
     * stop the others or the general e-mail. Returns how many were sent.
     *
     * @param  list<array{user: User, week: array<string, mixed>, file: array{data: string, name: string, mime: string}}>  $personal
     */
    protected function sendToEmployees(CarbonImmutable $monday, array $personal): int
    {
        $sent = 0;
        $number = fn (float $value): string => number_format($value, 2, ',', '');

        foreach ($personal as ['user' => $user, 'week' => $week, 'file' => $file]) {
            if (blank($user->email) || filter_var($user->email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $locale = $user->preferredLocale() ?? config('app.locale');

            if ($locale !== config('app.locale')) {
                $file = [...$file, 'data' => $this->pdf->render($user, $monday, $locale)];
            }

            try {
                Mail::to($user->email, $user->name)->send(new SystemMail(MailTemplateKey::TimesheetEmployee, $locale, [
                    'name' => $user->name,
                    'week' => (string) $monday->isoWeek(),
                    'from' => $monday->format('d/m/Y'),
                    'until' => $monday->addDays(4)->format('d/m/Y'),
                    'hours' => $number($week['hours']),
                    'overtime' => ($week['overtime_now'] >= 0 ? '+' : '').$number($week['overtime_now']),
                    'balance' => $number($week['overtime_new']),
                    'km' => number_format($week['km'], 0, ',', '.'),
                ], [$file]));
                $sent++;
            } catch (Throwable $exception) {
                Log::warning('Weekly timesheet could not be sent to the employee', ['user' => $user->id, 'week' => $monday->toDateString(), 'error' => $exception->getMessage()]);
            }
        }

        return $sent;
    }

    /**
     * Who fills in a prestation sheet: active employees of the employee register (or, without that
     * module, all active accounts with a role), plus anyone who filled in a day that week.
     *
     * @return Collection<int, User>
     */
    public function employees(CarbonImmutable $monday): Collection
    {
        $filled = TimesheetDay::query()->whereBetween('date', [$monday->toDateString(), $monday->addDays(4)->toDateString()])->distinct()->pluck('user_id');

        return User::query()
            ->active()
            ->withTimesheet()
            ->where(fn (Builder $query) => $query
                ->whereKey($filled)
                ->orWhere(fn (Builder $query) => modules()->employees()
                    ? $query->whereHas('employee', fn (Builder $query) => $query->whereNull('left_on'))
                    : $query->whereNotNull('role_id')))
            ->orderBy('name')
            ->get();
    }
}
