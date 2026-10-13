<?php

namespace App\Support;

use App\Models\TimesheetDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The prestation overview of everyone per month, like the Excel "prestatiefiche power": per employee
 * a row with the hours worked per day (or the absence code), a row with the kilometres and a row with
 * the imputation (project category); per month the kilometres and the mobility allowance; on the left
 * the vacation allowance and the absence days of the year so far.
 */
class TimesheetReport
{
    /**
     * Absence code per type of day, as in the Excel legend.
     */
    public const CODES = ['vacation' => 'V', 'medical' => 'Z', 'overtime' => 'IR', 'recuperation' => 'IR', 'family' => 'FV', 'training' => 'OP', 'holiday' => 'F'];

    /**
     * Codes counted in the left columns (year so far).
     */
    public const COUNTED = ['V', 'IR', 'Z', 'OP'];

    public function __construct(protected Timesheets $timesheets) {}

    public function mobilityRate(): float
    {
        return (float) settings()->get('timesheets.mobility_rate', 0.1929);
    }

    /**
     * The months of a report: one month, two months, or the calendar year of the month.
     *
     * @return list<CarbonImmutable>
     */
    public static function months(CarbonImmutable $month, string $span): array
    {
        $first = $span === 'year' ? $month->startOfYear() : $month->startOfMonth();
        $count = match ($span) {
            'year' => 12,
            '2' => 2,
            default => 1,
        };

        return array_map(fn (int $offset): CarbonImmutable => $first->addMonthsNoOverflow($offset), range(0, $count - 1));
    }

    /**
     * Everyone with a prestation sheet in the period: active employees of the employee register (or,
     * without that module, all active accounts with a role), plus anyone who filled in a day.
     *
     * @return Collection<int, User>
     */
    public function employees(CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        $filled = TimesheetDay::query()->whereBetween('date', [$from->toDateString(), $until->toDateString()])->distinct()->pluck('user_id');

        return User::query()
            ->where(fn (Builder $query) => $query
                ->whereKey($filled)
                ->orWhere(fn (Builder $query) => $query->active()->when(
                    modules()->employees(),
                    fn (Builder $query) => $query->whereHas('employee', fn (Builder $query) => $query->whereNull('left_on')->orWhereDate('left_on', '>=', $from->toDateString())),
                    fn (Builder $query) => $query->whereNotNull('role_id'),
                )))
            ->orderBy('name')
            ->get();
    }

    /**
     * One month of one employee.
     *
     * @return array{days: array<int, array{weekend: bool, value: string, hours: float, km: int, imputation: string, code: ?string, note: ?string}>, hours: float, total: float, km: int, mobility: float, codes: array<string, float>}
     */
    public function month(User $user, CarbonImmutable $month): array
    {
        $first = $month->startOfMonth();
        $last = $month->endOfMonth()->startOfDay();
        $working = $this->timesheets->days($user, $first, $last);
        $days = [];
        $codes = array_fill_keys(array_values(self::CODES), 0.0);

        foreach (CarbonPeriod::create($first, $last) as $date) {
            $key = $date->toDateString();
            $day = $working[$key] ?? null;
            $code = $day && $day['status'] ? (self::CODES[$day['status']['type']] ?? null) : null;
            $worked = (float) ($day['worked'] ?? 0);
            $half = $day && $day['status'] && $day['status']['credit'] > 0 && $day['status']['credit'] < $this->timesheets->dayHours();

            if ($code) {
                $codes[$code] += $half ? 0.5 : 1;
            }

            $categories = $day ? collect($day['rows'])->pluck('imputation')->filter()->map(fn (string $imputation): string => explode('-', $imputation)[0])->unique()->implode('/') : '';

            $days[$date->day] = [
                'weekend' => $date->isWeekend(),
                // A full absence shows its code; a worked day (also half a day off) its hours.
                'value' => $worked > 0 ? self::number($worked) : ($code ?? ''),
                'hours' => $worked,
                'km' => (int) ($day['km'] ?? 0),
                'imputation' => $categories,
                'code' => $code,
                'note' => $day['status']['note'] ?? null,
            ];
        }

        $km = (int) array_sum(array_column($days, 'km'));

        return [
            'days' => $days,
            'hours' => round(array_sum(array_column($days, 'hours')), 2),
            'total' => round(array_sum(array_column($working, 'total')), 2),
            'km' => $km,
            'mobility' => round($km * $this->mobilityRate(), 2),
            'codes' => $codes,
        ];
    }

    /**
     * Absence days of the year up to the end of the month (the left columns of the Excel).
     *
     * @return array<string, float>
     */
    public function yearToDate(User $user, CarbonImmutable $month): array
    {
        $statuses = $this->timesheets->statuses($user, $month->startOfYear(), $month->endOfMonth()->startOfDay());
        $counts = array_fill_keys(self::COUNTED, 0.0);

        foreach ($statuses as $date => $status) {
            $code = self::CODES[$status['type']] ?? null;

            if ($code && isset($counts[$code]) && ! CarbonImmutable::parse($date)->isWeekend()) {
                $counts[$code] += $status['credit'] > 0 && $status['credit'] < $this->timesheets->dayHours() ? 0.5 : 1;
            }
        }

        return $counts;
    }

    /**
     * Everything the page and the PDF show for a set of months.
     *
     * @param  list<CarbonImmutable>  $months
     * @return array{months: list<array{month: CarbonImmutable, employees: list<array<string, mixed>>, km: int, mobility: float, hours: float}>, summary: list<array<string, mixed>>}
     */
    public function build(array $months): array
    {
        $from = $months[0]->startOfMonth();
        $until = end($months)->endOfMonth()->startOfDay();
        $employees = $this->employees($from, $until);
        $result = [];
        $summary = [];

        foreach ($months as $month) {
            $rows = [];

            foreach ($employees as $user) {
                $data = $this->month($user, $month);
                $rows[] = ['user' => $user, 'allowance' => (float) $user->vacation_days, 'year' => $this->yearToDate($user, $month), ...$data];

                $summary[$user->id] ??= ['user' => $user, 'hours' => 0.0, 'total' => 0.0, 'km' => 0, 'mobility' => 0.0, 'codes' => array_fill_keys(array_values(self::CODES), 0.0)];
                $summary[$user->id]['hours'] += $data['hours'];
                $summary[$user->id]['total'] += $data['total'];
                $summary[$user->id]['km'] += $data['km'];
                $summary[$user->id]['mobility'] += $data['mobility'];

                foreach ($data['codes'] as $code => $count) {
                    $summary[$user->id]['codes'][$code] += $count;
                }
            }

            $result[] = [
                'month' => $month,
                'employees' => $rows,
                'hours' => round(array_sum(array_column($rows, 'hours')), 2),
                'km' => (int) array_sum(array_column($rows, 'km')),
                'mobility' => round(array_sum(array_column($rows, 'mobility')), 2),
            ];
        }

        return ['months' => $result, 'summary' => array_values($summary)];
    }

    /**
     * 8 → "8", 7.75 → "7,75".
     */
    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }
}
