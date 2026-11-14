<?php

namespace App\Support\Dashboard;

use App\Models\DashboardWidget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * What a self-built dashboard block shows:
 *
 *  - counter: the number of records (or the sum / average of a number field) matching the filters, as a
 *    number or as a percentage of the records matching the "percentage of" filters (all records when none);
 *  - list: the matching records, sorted, with the chosen columns, each linking to its record;
 *  - status: the matching records grouped by a field (status, category, person, month …), as numbers,
 *    percentages of the total, or both.
 */
class DashboardWidgetData
{
    public const AGGREGATES = ['count', 'sum', 'avg'];

    public const DISPLAYS = ['number', 'percentage', 'both'];

    /**
     * @return array<string, mixed>
     */
    public static function for(DashboardWidget $widget, ?User $user = null): array
    {
        $user ??= auth()->user();
        $source = DashboardSource::find($widget->panel, $widget->source);

        if ($source === null) {
            return ['error' => 'no_access'];
        }

        return rescue(fn (): array => match ($widget->type) {
            DashboardWidget::LIST => self::list($widget, $source, $user),
            DashboardWidget::STATUS => self::status($widget, $source, $user),
            default => self::counter($widget, $source, $user),
        }, ['error' => 'failed'], report: true);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function counter(DashboardWidget $widget, DashboardSource $source, ?User $user): array
    {
        $value = self::aggregate($source, (array) $widget->setting('filters', []), $widget, $user);
        $display = (string) $widget->setting('display', 'number');
        $base = in_array($display, ['percentage', 'both'], true) ? self::aggregate($source, (array) $widget->setting('base_filters', []), $widget, $user) : null;

        return [
            'value' => $value,
            'base' => $base,
            'percentage' => $base ? round(100 * $value / $base, 1) : ($base === null ? null : 0.0),
            'display' => $display,
            'aggregate' => (string) $widget->setting('aggregate', 'count'),
            'url' => $source->indexUrl(),
            'source' => $source->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function list(DashboardWidget $widget, DashboardSource $source, ?User $user): array
    {
        $fields = $source->fields($user);
        $columns = array_values(array_filter((array) $widget->setting('columns', []), fn (string $key): bool => isset($fields[$key])));
        $sort = $widget->setting('sort_field');
        $query = $source->applyFilters($source->query(), (array) $widget->setting('filters', []), $user);
        $relations = collect($columns)->map(fn (string $key): ?string => $fields[$key]['relation'] ?? null)->filter()->unique()->values()->all();
        $total = (clone $query)->toBase()->getCountForPagination();

        if (isset($fields[$sort])) {
            $query->reorder($query->getModel()->getTable().'.'.$fields[$sort]['column'], $widget->setting('sort_direction') === 'desc' ? 'desc' : 'asc');
        }

        $records = $query->with($relations)->limit(max(1, min(50, (int) $widget->setting('limit', 10))))->get();

        return [
            'columns' => collect($columns)->mapWithKeys(fn (string $key): array => [$key => $fields[$key]['label']])->all(),
            'rows' => $records->map(fn (Model $record): array => [
                'title' => $source->recordTitle($record),
                'url' => $source->recordUrl($record),
                'values' => collect($columns)->map(fn (string $key): string => $source->display($record, $key))->all(),
            ])->all(),
            'total' => $total,
            'url' => $source->indexUrl(),
            'source' => $source->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function status(DashboardWidget $widget, DashboardSource $source, ?User $user): array
    {
        $fields = $source->fields($user);
        $group = $widget->setting('group_by');

        if (! isset($fields[$group])) {
            return ['error' => 'no_group'];
        }

        $field = $fields[$group];
        $query = $source->applyFilters($source->query(), (array) $widget->setting('filters', []), $user);
        $table = $query->getModel()->getTable();
        $column = $table.'.'.$field['column'];
        $grammar = $query->getQuery()->getGrammar();
        $wrapped = $grammar->wrap($column);
        $groupExpression = $field['type'] === 'date' ? "DATE_FORMAT({$wrapped}, '%Y-%m')" : $wrapped;
        $aggregate = self::aggregateExpression($widget, $source, $user, $table);

        $rows = $query->reorder()->toBase()
            ->select(DB::raw("{$groupExpression} as group_value, {$aggregate} as aggregate_value"))
            ->groupByRaw($groupExpression)
            ->get();

        $total = (float) $rows->sum('aggregate_value');
        $display = (string) $widget->setting('display', 'number');
        $groups = $rows
            ->map(fn (object $row): array => [
                'label' => $field['type'] === 'date' && $row->group_value
                    ? rescue(fn (): string => ucfirst(CarbonImmutable::createFromFormat('Y-m', $row->group_value)->locale(app()->getLocale())->translatedFormat('F Y')), (string) $row->group_value, false)
                    : $source->groupLabel($group, $row->group_value),
                'sort' => $row->group_value,
                'value' => round((float) $row->aggregate_value, 2),
                'percentage' => $total > 0 ? round(100 * (float) $row->aggregate_value / $total, 1) : 0.0,
            ])
            ->sortBy($field['type'] === 'date' ? 'sort' : fn (array $group): float => -$group['value'])
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'total' => $total,
            'display' => $display,
            'aggregate' => (string) $widget->setting('aggregate', 'count'),
            'url' => $source->indexUrl(),
            'source' => $source->label(),
        ];
    }

    /**
     * The count, sum or average of the records matching the filters.
     *
     * @param  array<int|string, array<string, mixed>>  $filters
     */
    protected static function aggregate(DashboardSource $source, array $filters, DashboardWidget $widget, ?User $user): float
    {
        $query = $source->applyFilters($source->query(), $filters, $user);
        $table = $query->getModel()->getTable();
        $expression = self::aggregateExpression($widget, $source, $user, $table);

        return round((float) $query->reorder()->toBase()->select(DB::raw("{$expression} as aggregate_value"))->value('aggregate_value'), 2);
    }

    protected static function aggregateExpression(DashboardWidget $widget, DashboardSource $source, ?User $user, string $table): string
    {
        $fields = $source->fields($user);
        $aggregate = (string) $widget->setting('aggregate', 'count');
        $field = $fields[(string) $widget->setting('aggregate_field')] ?? null;

        if (! in_array($aggregate, ['sum', 'avg'], true) || ($field['type'] ?? null) !== 'number') {
            return 'COUNT(*)';
        }

        return strtoupper($aggregate).'('.$source->query()->getQuery()->getGrammar()->wrap($table.'.'.$field['column']).')';
    }

    public static function number(float|int|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
