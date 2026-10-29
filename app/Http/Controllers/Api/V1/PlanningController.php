<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\PlanningItem;
use App\Models\User;
use App\Support\Planning;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The planning in the mobile app, read-only: the employee's own planning per day, week or month, and
 * that of the colleagues whose planning they may see on the platform (team members, shared plannings,
 * everybody for planners). Changes are made on the platform.
 */
class PlanningController extends Controller
{
    /** Longest period asked at once: a month grid of six weeks. */
    public const MAX_DAYS = 42;

    public function __construct(protected Planning $planning) {}

    /**
     * The people whose planning the user may see: themselves first.
     */
    public function people(Request $request): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        /** @var User $user */
        $user = $request->user();
        $ids = $this->planning->visibleUserIds($user);
        $people = User::query()->active()->withTimesheet()
            ->when($ids !== null, fn ($query) => $query->whereKey($ids))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->sortBy(fn (User $person): int => $person->id === $user->id ? 0 : 1)
            ->values();

        return response()->json(['data' => $people->map(fn (User $person): array => [
            'id' => $person->id,
            'name' => $person->name,
            'me' => $person->id === $user->id,
        ])]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        /** @var User $viewer */
        $viewer = $request->user();
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $from = CarbonImmutable::parse($data['from'])->startOfDay();
        $until = CarbonImmutable::parse($data['until'])->startOfDay();
        abort_if($from->diffInDays($until) >= self::MAX_DAYS, 422, __('erp.mobile.errors.period'));

        $employee = isset($data['user_id']) ? User::query()->findOrFail($data['user_id']) : $viewer;
        abort_unless($this->planning->canSeePlanningOf($viewer, $employee), 403);

        $items = PlanningItem::query()
            ->with(['project.category', 'project.workSite', 'project.batteryDossier', 'project.baeOrder'])
            ->where('user_id', $employee->id)
            ->between($from, $until)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (PlanningItem $item): string => $item->date->toDateString());
        $blocked = $this->planning->blockedDays([$employee->id], $from, $until, $viewer)[$employee->id] ?? [];
        $holidays = Holiday::query()->whereBetween('date', [$from->toDateString(), $until->toDateString()])->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $days = [];

        foreach (CarbonPeriod::create($from, $until) as $date) {
            $key = $date->toDateString();
            $days[] = [
                'date' => $key,
                'weekday' => $date->locale(app()->getLocale())->translatedFormat('l'),
                'weekend' => $date->isWeekend(),
                'holiday' => $holidays->get($key)?->label(),
                'blocked' => $blocked[$key] ?? null,
                'items' => ($items->get($key) ?? collect())->map(fn (PlanningItem $item): array => $this->item($item))->values(),
            ];
        }

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
            'user' => ['id' => $employee->id, 'name' => $employee->name, 'me' => $employee->id === $viewer->id],
            'days' => $days,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(PlanningItem $item): array
    {
        $site = $item->project?->workSite;

        return [
            'id' => $item->id,
            'start' => $item->startsAt(),
            'end' => $item->endsAt(),
            'kind' => $item->kind->value,
            'kind_label' => $item->kind->getLabel(),
            'topic' => $item->topic(),
            'subtitle' => $item->subtitle(),
            'batteries' => $item->batteries(),
            'description' => $item->description,
            'notes' => $item->notes,
            'tasks' => $item->taskList(),
            'cow_code' => $site?->cow_code ?? $item->project?->cow_code,
            'address' => $site?->addressLine(),
            'maps_url' => $site?->googleMapsUrl(),
        ];
    }

    protected function blocked(): ?JsonResponse
    {
        return $this->planning->enabled() ? null : response()->json(['message' => __('erp.mobile.errors.module_off'), 'code' => 'module_off'], 403);
    }
}
