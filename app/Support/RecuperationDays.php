<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\InterimRecuperationDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The IR days (inhaalrust) and public holidays of each person: the company's, except those taken away
 * for an interim (they are not entitled to them and work that day), plus the IR days of their own an
 * interim got (Personnel → employee → IR days and holidays).
 */
class RecuperationDays
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, array<string, array{name: string, label: string}>> per user id, per date
     */
    public static function forUsers(array $userIds, CarbonInterface|string $from, CarbonInterface|string $until): array
    {
        $from = Carbon::parse($from)->toDateString();
        $until = Carbon::parse($until)->toDateString();

        $company = Holiday::query()->recuperationDays()->whereBetween('date', [$from, $until])->get()
            ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => ['name' => (string) $holiday->name, 'label' => $holiday->label()]])
            ->all();
        $adjusted = $userIds === [] ? collect() : InterimRecuperationDay::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from, $until])
            ->get()
            ->groupBy('user_id');

        $days = [];

        foreach ($userIds as $userId) {
            $own = $company;

            foreach ($adjusted->get($userId, collect()) as $day) {
                $date = $day->date->toDateString();

                if ($day->is_day_off) {
                    $own[$date] = ['name' => $day->name ?: (string) __('erp.recuperation_days.default_name'), 'label' => $day->label()];
                } else {
                    unset($own[$date]);
                }
            }

            ksort($own);
            $days[$userId] = $own;
        }

        return $days;
    }

    /**
     * @return array<string, array{name: string, label: string}> per date
     */
    public static function forUser(int $userId, CarbonInterface|string $from, CarbonInterface|string $until): array
    {
        return self::forUsers([$userId], $from, $until)[$userId];
    }

    /**
     * The public holidays that apply to a person in the period, per date.
     *
     * @return array<string, Holiday>
     */
    public static function publicHolidaysFor(int $userId, CarbonInterface|string $from, CarbonInterface|string $until): array
    {
        $from = Carbon::parse($from)->toDateString();
        $until = Carbon::parse($until)->toDateString();
        $takenAway = InterimRecuperationDay::query()->where('user_id', $userId)->where('is_day_off', false)->whereBetween('date', [$from, $until])
            ->pluck('date')->map(fn (CarbonInterface $date): string => $date->toDateString())->all();

        return Holiday::query()->publicHolidays()->whereBetween('date', [$from, $until])->orderBy('date')->get()->toBase()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString())
            ->except($takenAway)
            ->all();
    }

    /**
     * The days off of a person in the period, public holidays included, as "Y-m-d" dates.
     *
     * @return list<string>
     */
    public static function daysOff(?int $userId, CarbonInterface|string $from, CarbonInterface|string $until): array
    {
        $from = Carbon::parse($from)->toDateString();
        $until = Carbon::parse($until)->toDateString();

        if ($userId === null) {
            return Holiday::query()->whereBetween('date', [$from, $until])->pluck('date')
                ->map(fn (CarbonInterface $date): string => $date->toDateString())->all();
        }

        $public = array_keys(self::publicHolidaysFor($userId, $from, $until));

        return array_values(array_unique([...$public, ...array_keys(self::forUser($userId, $from, $until))]));
    }
}
