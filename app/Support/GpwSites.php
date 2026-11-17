<?php

namespace App\Support;

use App\Models\GpwRequest;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\WorkSite;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * GPW requests for a project with several COW codes (an intervention on several COW codes, a battery move
 * from one site to another): one request, mailed once and approved once, covers all the COW codes chosen,
 * either in one period or each COW code in its own period.
 */
class GpwSites
{
    /**
     * The COW codes a GPW can be asked for at once, by work site id with a label; empty when the project has
     * only one.
     *
     * @return array<int, string>
     */
    public static function options(Project $project): array
    {
        $options = PlanningItem::siteOptions($project->id);

        return count($options) > 1 ? $options : [];
    }

    /**
     * The work sites of those COW codes (for the contact suggestions); just the project's own otherwise.
     *
     * @return Collection<int, WorkSite>
     */
    public static function workSites(Project $project): Collection
    {
        $ids = array_keys(self::options($project));

        if ($ids === []) {
            return new Collection(array_filter([$project->workSite]));
        }

        return WorkSite::query()->whereKey($ids)->get()->sortBy(fn (WorkSite $site): int => array_search($site->id, $ids, true))->values();
    }

    /**
     * The fields of the request windows: one period (the default), or for a project with several COW codes
     * the COW codes to ask for and, if wanted, a period per COW code.
     *
     * @return list<mixed>
     */
    public static function schema(Project $project, bool $future = true): array
    {
        $options = self::options($project);
        $single = fn (Get $get): bool => $options === [] || ! $get('split');
        $date = fn (string $name, string $label): DatePicker => DatePicker::make($name)->label(__($label))->native(false)->displayFormat('d/m/Y')->minDate($future ? today() : null);

        $period = Grid::make(2)->visible($single)->schema([
            $date('starts_on', 'erp.gpw.starts_on')->required()->live(),
            $date('ends_on', 'erp.gpw.ends_on')->afterOrEqual('starts_on')->required(),
        ]);

        if ($options === []) {
            return [$period];
        }

        return [
            CheckboxList::make('work_site_ids')
                ->label(__('erp.gpw.sites.cow_codes'))
                ->helperText(__('erp.gpw.sites.cow_codes_help'))
                ->options($options)
                ->default(array_keys($options))
                ->visible($single)
                ->required()
                ->bulkToggleable(),
            Toggle::make('split')
                ->label(__('erp.gpw.sites.split'))
                ->helperText(__('erp.gpw.sites.split_help'))
                ->live(),
            $period,
            Repeater::make('sites')
                ->label(__('erp.gpw.sites.per_cow_code'))
                ->visible(fn (Get $get): bool => (bool) $get('split'))
                ->schema([
                    Select::make('work_site_id')->label(__('erp.client_portal.cow_code'))->options($options)->required(),
                    $date('starts_on', 'erp.gpw.starts_on')->required()->live(),
                    $date('ends_on', 'erp.gpw.ends_on')->afterOrEqual('starts_on')->required(),
                ])
                ->columns(3)
                ->default(array_map(fn (int $id): array => ['work_site_id' => $id, 'starts_on' => null, 'ends_on' => null], array_keys($options)))
                ->minItems(1)
                ->addActionLabel(__('erp.gpw.sites.add'))
                ->reorderable(false),
        ];
    }

    /**
     * Checks the period(s) of a request and returns the COW codes with their periods (none for a project with
     * one COW code), and the span over all of them.
     *
     * @param  array<string, mixed>  $data  starts_on/ends_on, and for several COW codes either work_site_ids (one period) or split + sites
     * @return array{starts_on: CarbonImmutable, ends_on: CarbonImmutable, sites: list<array{work_site_id: int, cow_code: ?string, starts_on: string, ends_on: string}>}
     */
    public static function resolve(Project $project, array $data, bool $future = true): array
    {
        $options = self::options($project);
        $rows = [];

        if ($options !== [] && ! empty($data['split']) && is_array($data['sites'] ?? null)) {
            foreach (array_values($data['sites']) as $index => $row) {
                $rows[] = [(int) ($row['work_site_id'] ?? 0), $row['starts_on'] ?? null, $row['ends_on'] ?? null, "sites.{$index}."];
            }
        } elseif ($options !== [] && array_key_exists('work_site_ids', $data)) {
            foreach ((array) $data['work_site_ids'] as $id) {
                $rows[] = [(int) $id, $data['starts_on'] ?? null, $data['ends_on'] ?? null, ''];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['work_site_ids' => __('erp.gpw.sites.none')]);
            }
        } else {
            [$from, $until] = self::period($data['starts_on'] ?? null, $data['ends_on'] ?? null, $future, '');

            return ['starts_on' => $from, 'ends_on' => $until, 'sites' => []];
        }

        $codes = WorkSite::query()->whereKey(array_keys($options))->pluck('cow_code', 'id');
        $sites = [];

        foreach ($rows as [$id, $starts, $ends, $prefix]) {
            if (! array_key_exists($id, $options)) {
                throw ValidationException::withMessages([$prefix.'work_site_id' => __('erp.gpw.sites.unknown')]);
            }

            [$from, $until] = self::period($starts, $ends, $future, $prefix);
            $sites[] = ['work_site_id' => $id, 'cow_code' => $codes[$id] ?? null, 'starts_on' => $from->toDateString(), 'ends_on' => $until->toDateString()];
        }

        return [
            'starts_on' => CarbonImmutable::parse(min(array_column($sites, 'starts_on'))),
            'ends_on' => CarbonImmutable::parse(max(array_column($sites, 'ends_on'))),
            'sites' => $sites,
        ];
    }

    /**
     * @param  list<array{work_site_id: int, cow_code: ?string, starts_on: string, ends_on: string}>  $sites
     */
    public static function store(GpwRequest $request, array $sites): void
    {
        foreach ($sites as $site) {
            $request->sites()->create($site);
        }

        $request->unsetRelation('sites');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected static function period(mixed $starts, mixed $ends, bool $future, string $prefix): array
    {
        if (blank($starts) || blank($ends)) {
            throw ValidationException::withMessages([$prefix.(blank($starts) ? 'starts_on' : 'ends_on') => __('validation.required', ['attribute' => __(blank($starts) ? 'erp.gpw.starts_on' : 'erp.gpw.ends_on')])]);
        }

        $from = CarbonImmutable::parse((string) $starts)->startOfDay();
        $until = CarbonImmutable::parse((string) $ends)->startOfDay();

        if ($future && $from->isBefore(today())) {
            throw ValidationException::withMessages([$prefix.'starts_on' => __('erp.gpw.errors.past')]);
        }

        if ($until->isBefore($from)) {
            throw ValidationException::withMessages([$prefix.'ends_on' => __('validation.after_or_equal', ['attribute' => __('erp.gpw.ends_on'), 'date' => $from->format('d/m/Y')])]);
        }

        return [$from, $until];
    }
}
