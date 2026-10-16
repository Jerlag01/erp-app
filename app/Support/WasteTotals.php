<?php

namespace App\Support;

use App\Models\WasteCategory;
use App\Models\WasteEntry;
use Illuminate\Support\Carbon;

/**
 * Total weight per waste category and subcategory (with waste code) over a period, grouped under the
 * chapters of the Eural. By default only categories with entries in the period; with $all every active
 * category (the complete list) is shown.
 */
class WasteTotals
{
    /**
     * @return array{groups: list<array{category: WasteCategory, chapter: ?string, rows: list<array{category: WasteCategory, kg: float, count: int}>, kg: float, count: int}>, chapters: array<string, array{title: string, kg: float, count: int}>, kg: float, count: int}
     */
    public static function between(?string $from, ?string $until, bool $all = false): array
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

                // Without entries in the period: only in the complete list, and only when active.
                if ($sum === null && (! $all || ! $member->is_active)) {
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
                    'chapter' => Eural::chapterOf($category->waste_code),
                    'rows' => $rows,
                    'kg' => array_sum(array_column($rows, 'kg')),
                    'count' => array_sum(array_column($rows, 'count')),
                ];
            }
        }

        // Per Eural chapter (in order); own categories without an Eural code come last.
        usort($groups, fn (array $a, array $b): int => [$a['chapter'] ?? '99', $a['category']->sort_order] <=> [$b['chapter'] ?? '99', $b['category']->sort_order]);
        $chapters = [];

        foreach ($groups as $group) {
            $key = $group['chapter'] ?? '';
            $chapters[$key] ??= ['title' => $key === '' ? __('erp.waste.other_categories') : Eural::chapters()[$key], 'kg' => 0.0, 'count' => 0];
            $chapters[$key]['kg'] += $group['kg'];
            $chapters[$key]['count'] += $group['count'];
        }

        return [
            'groups' => $groups,
            'chapters' => $chapters,
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
