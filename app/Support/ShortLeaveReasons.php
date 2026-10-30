<?php

namespace App\Support;

/**
 * The reasons for klein verlet (KV) after the royal decree of 28 August 1963 (werk.belgie.be, "Lijst
 * klein verlet"), with the most days each allows. The text per reason is in erp.short_leave.reasons.
 */
class ShortLeaveReasons
{
    /** Reason => maximum days. */
    public const DAYS = [
        'own_marriage' => 2,
        'marriage_relative' => 1,
        'ordination_relative' => 1,
        'death_partner_child' => 10,
        'death_parent' => 3,
        'death_relative_living_in' => 2,
        'death_relative' => 1,
        'death_foster_child' => 1,
        'communion' => 1,
        'secular_youth' => 1,
        'military_selection' => 3,
        'conscientious_objector' => 3,
        'jury_witness' => 5,
        'polling_station' => 1,
        'polling_station_europe' => 5,
        'counting_station' => 5,
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::DAYS)->mapWithKeys(fn (int $days, string $reason): array => [
            $reason => __("erp.short_leave.reasons.{$reason}").' ('.trans_choice('erp.short_leave.days', $days, ['count' => $days]).')',
        ])->all();
    }

    public static function maxDays(?string $reason): ?int
    {
        return self::DAYS[$reason] ?? null;
    }
}
