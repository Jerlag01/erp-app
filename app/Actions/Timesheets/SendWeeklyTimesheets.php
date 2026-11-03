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
 * all sheets and a summary per employee. The contacts of an interim office get one e-mail with the sheets
 * of that office's interims. The weeks are then marked as sent, so employees can no longer change them.
 */
class SendWeeklyTimesheets
{
    public function __construct(protected Timesheets $timesheets, protected TimesheetPdf $pdf) {}

    /**
     * @param  iterable<User>|null  $users  everyone who fills in prestation sheets when null
     * @return array{sent: bool, count: int, personal: int, interim: int, error: ?string}
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
            $summary[$user->id] = '- **'.MailTemplates::escape($user->name).'**: '.MailTemplates::escape(sprintf(
                '%s u · meeruren %s (saldo %s) · %s km',
                number_format($week['hours'], 2, ',', ''),
                ($week['overtime_now'] >= 0 ? '+' : '').number_format($week['overtime_now'], 2, ',', ''),
                number_format($week['overtime_new'], 2, ',', ''),
                number_format($week['km'], 0, ',', '.'),
            ));

            TimesheetWeek::query()->updateOrCreate(
                ['user_id' => $user->id, 'week_start' => $monday->toDateString()],
                ['plate_number' => $week['plate'], 'pdf_path' => $path, 'sent_at' => now()],
            );

            $personal[] = ['user' => $user, 'week' => $week, 'file' => $files[$user->id]];
        }

        $sentToEmployees = $toEmployees ? $this->sendToEmployees($monday, $personal) : 0;
        $sentToOffices = $this->sendToInterimOffices($monday, $offices, $files, $summary);

        if ($recipients === []) {
            return ['sent' => true, 'count' => $users->count(), 'personal' => $sentToEmployees, 'interim' => $sentToOffices, 'error' => null];
        }

        try {
            Mail::to($recipients)->send($this->weekMail($monday, array_values($summary), array_values($files)));
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
    protected function weekMail(CarbonImmutable $monday, array $summary, array $files): SystemMail
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
     * Each interim office's contacts get one e-mail with the sheets of that office's interims (and only
     * those). A failed e-mail is logged and does not stop the others. Returns how many offices got it.
     *
     * @param  list<array{office: InterimOffice, users: list<int>}>  $offices
     * @param  array<int, array{data: string, name: string, mime: string}>  $files
     * @param  array<int, string>  $summary
     */
    protected function sendToInterimOffices(CarbonImmutable $monday, array $offices, array $files, array $summary): int
    {
        $sent = 0;

        foreach ($offices as ['office' => $office, 'users' => $userIds]) {
            $theirFiles = array_values(array_intersect_key($files, array_flip($userIds)));

            if ($theirFiles === []) {
                continue;
            }

            try {
                Mail::to(collect($office->contactAddresses())->map(fn (string $name, string $email): array => ['email' => $email, 'name' => $name])->values()->all())
                    ->send($this->weekMail($monday, array_values(array_intersect_key($summary, array_flip($userIds))), $theirFiles));
                $sent++;
            } catch (Throwable $exception) {
                Log::warning('Weekly timesheets could not be sent to the interim office', ['office' => $office->id, 'week' => $monday->toDateString(), 'error' => $exception->getMessage()]);
            }
        }

        return $sent;
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
