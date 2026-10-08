<?php

namespace App\Support;

use App\Models\WasteCategory;
use App\Models\WasteEntry;
use Illuminate\Support\Carbon;

/**
 * Total weight per waste category and subcategory (with waste code) over a period.
 */
class WasteTotals
{
    /**
     * @return array{groups: list<array{category: WasteCategory, rows: list<array{category: WasteCategory, kg: float, count: int}>, kg: float, count: int}>, kg: float, count: int}
     */
    public static function between(?string $from, ?string $until): array
    {
        $sums = WasteEntry::query()
            ->when($from, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($until, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->selectRaw('waste_category_id, SUM(weight_kg) as kg, COUNT(*) as entries')
            ->groupBy('waste_category_id')
            ->get()
            ->keyBy('waste_category_id');

        $groups = [];

        foreach (WasteCategory::query()->whereNull('parent_id')->ordered()->with('children')->get() as $category) {
            // A category without subcategories is its own row.
            $members = $category->children->isEmpty() ? collect([$category]) : $category->children->concat([$category]);
            $rows = [];

            foreach ($members as $member) {
                $sum = $sums->get($member->id);

                // Inactive categories without entries in the period are left out.
                if ($sum === null && ! $member->is_active) {
                    continue;
                }

                // Entries on a main category that also has subcategories only show when there are any.
                if ($member->is($category) && $category->children->isNotEmpty() && $sum === null) {
                    continue;
                }

                $rows[] = ['category' => $member, 'kg' => (float) ($sum->kg ?? 0), 'count' => (int) ($sum->entries ?? 0)];
            }

            if ($rows !== []) {
                $groups[] = [
                    'category' => $category,
                    'rows' => $rows,
                    'kg' => array_sum(array_column($rows, 'kg')),
                    'count' => array_sum(array_column($rows, 'count')),
                ];
            }
        }

        return [
            'groups' => $groups,
            'kg' => array_sum(array_column($groups, 'kg')),
            'count' => array_sum(array_column($groups, 'count')),
        ];
    }

    public static function periodLabel(?string $from, ?string $until): string
    {
        $format = fn (?string $date): string => $date ? Carbon::parse($date)->format('d/m/Y') : '…';

        return ! $from && ! $until ? __('erp.waste.all_time') : $format($from).' – '.$format($until);
    }
}
