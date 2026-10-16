<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Timesheets\SaveTimesheetDay;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TimesheetWeek;
use App\Models\User;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The employee's own prestation sheet ("prestatiefiche") in the mobile app: the week with its totals,
 * one day with its rows, saving a day and finding projects for the imputation. A day is saved exactly
 * like on the Timesheet page (SaveTimesheetDay), with the same rules: own sheet only, not after the
 * week has been sent (unless "timesheets.edit_all").
 */
class TimesheetController extends Controller
{
    public function __construct(protected Timesheets $timesheets) {}

    public function week(Request $request): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        $user = $request->user();
        $date = $this->date((string) $request->query('date', today()->toDateString())) ?? abort(422);
        $week = $this->timesheets->week($user, $date);

        return response()->json(['data' => [
            'monday' => $week['monday']->toDateString(),
            'friday' => $week['friday']->toDateString(),
            'number' => $week['number'],
            'name' => $user->name,
            'plate' => $week['plate'],
            'can_edit' => $this->timesheets->canEdit($user, $user, $week['record']),
            'sent' => (bool) $week['record']?->isSent(),
            'expected' => $week['expected'],
            'hours' => $week['hours'],
            'km' => $week['km'],
            'km_carried' => $week['km_carried'],
            'km_new' => $week['km_new'],
            'overtime_carried' => $week['overtime_carried'],
            'overtime_now' => $week['overtime_now'],
            'overtime_new' => $week['overtime_new'],
            'days' => array_map(fn (array $day): array => [
                'date' => $day['date']->toDateString(),
                'weekday' => $day['date']->locale(app()->getLocale())->translatedFormat('l'),
                'status' => $this->status($day['status']),
                'worked' => $day['worked'],
                'credit' => $day['credit'],
                'total' => $day['total'],
                'km' => $day['km'],
                'filled' => $day['record'] !== null,
            ], $week['days']),
        ]]);
    }

    public function show(Request $request, string $date): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        $day = $this->weekday($date);

        return response()->json(['data' => $this->day($request->user(), $day)]);
    }

    public function update(Request $request, string $date, SaveTimesheetDay $save): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        $user = $request->user();
        $day = $this->weekday($date);

        if (! $this->timesheets->canEdit($user, $user, $this->weekRecord($user, $day))) {
            return response()->json(['message' => __('erp.mobile.errors.week_locked'), 'code' => 'week_locked'], 403);
        }

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:3', 'max:'.(Timesheets::MAX_EXTRA + 3)],
            'rows.*.kind' => ['required', Rule::in(['start', 'site', 'extra', 'end'])],
            'rows.*.km_departure' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'rows.*.km_arrival' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'rows.*.arrival' => ['nullable', 'date_format:H:i'],
            'rows.*.departure' => ['nullable', 'date_format:H:i'],
            'rows.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'rows.*.description' => ['nullable', 'string', 'max:255'],
            'lunch_break' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        validator($data)->after(function (Validator $validator) use ($data, $user): void {
            foreach ($data['rows'] as $index => $row) {
                if (isset($row['km_departure'], $row['km_arrival']) && $row['km_arrival'] < $row['km_departure']) {
                    $validator->errors()->add("rows.{$index}.km_arrival", __('erp.mobile.errors.km_order'));
                }

                if (filled($row['arrival'] ?? null) && filled($row['departure'] ?? null) && $row['departure'] < $row['arrival']) {
                    $validator->errors()->add("rows.{$index}.departure", __('erp.mobile.errors.time_order'));
                }

                if (filled($row['project_id'] ?? null) && ! $this->timesheets->mayBookOn($user, Project::query()->findOrFail($row['project_id']))) {
                    $validator->errors()->add("rows.{$index}.project_id", __('erp.mobile.errors.project'));
                }
            }
        })->validate();

        $save->handle($user, $day, $data, $user);

        return response()->json(['message' => __('erp.timesheets.saved'), 'data' => $this->day($user, $day)]);
    }

    /**
     * Projects for the imputation: the ones of the day's planning, or found by a search term.
     */
    public function projects(Request $request): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $projects = $search !== ''
            ? $this->timesheets->searchProjects($user, $search, 30)
            : $this->timesheets->plannedProjects($user, $this->date((string) $request->query('date', today()->toDateString())) ?? today()->toImmutable());

        return response()->json(['data' => $projects->map(fn (Project $project): array => $this->project($project))->values()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function day(User $user, CarbonImmutable $day): array
    {
        $info = $this->timesheets->days($user, $day, $day)[$day->toDateString()];
        $rows = $info['record'] ? $info['rows'] : $this->timesheets->prefillRows($user, $day);
        $projects = Project::query()->with(['category', 'workSite'])->whereKey(array_filter(array_column($rows, 'project_id')))->get()->keyBy('id');

        return [
            'date' => $day->toDateString(),
            'weekday' => $day->locale(app()->getLocale())->translatedFormat('l j F'),
            'can_edit' => $this->timesheets->canEdit($user, $user, $this->weekRecord($user, $day)),
            'saved' => $info['record'] !== null,
            'status' => $this->status($info['status']),
            'rows' => array_map(fn (array $row): array => [
                ...$row,
                'project_label' => ($project = $projects->get($row['project_id'])) ? $this->project($project)['label'] : null,
                'hours' => Timesheets::hours($row['arrival'], $row['departure']),
                'km' => Timesheets::rowKm($row),
            ], $rows),
            'lunch_break' => $info['lunch'],
            'lunch_locked' => (bool) ($info['status']['lunch_locked'] ?? false),
            'lunch_hours' => $this->timesheets->lunchHours(),
            'remarks' => $info['remarks'],
            'worked' => $info['worked'],
            'credit' => $info['credit'],
            'total' => $info['total'],
            'km' => $info['km'],
            'max_extra' => Timesheets::MAX_EXTRA,
            'planned_projects' => $this->timesheets->plannedProjects($user, $day)->map(fn (Project $project): array => $this->project($project))->values(),
        ];
    }

    /**
     * @return array{id: int, imputation: string, description: ?string, label: string}
     */
    protected function project(Project $project): array
    {
        return [
            'id' => $project->id,
            'imputation' => Timesheets::imputation($project),
            'description' => $project->workSite?->cow_code ?? $project->cow_code,
            'label' => Timesheets::imputation($project).($project->subtitle() ? ' — '.$project->subtitle() : ''),
        ];
    }

    /**
     * @param  array{type: string, note: string, credit: float, lunch_locked: bool}|null  $status
     * @return array{type: string, note: string, credit: float}|null
     */
    protected function status(?array $status): ?array
    {
        return $status ? ['type' => $status['type'], 'note' => $status['note'], 'credit' => $status['credit']] : null;
    }

    protected function weekRecord(User $user, CarbonImmutable $day): ?TimesheetWeek
    {
        return TimesheetWeek::query()->where('user_id', $user->id)->whereDate('week_start', Timesheets::monday($day)->toDateString())->first();
    }

    protected function date(string $value): ?CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? rescue(fn (): CarbonImmutable => CarbonImmutable::parse($value)->startOfDay(), null, false) : null;
    }

    /**
     * A working day (Monday to Friday); weekends have no prestation sheet.
     */
    protected function weekday(string $value): CarbonImmutable
    {
        $day = $this->date($value);
        abort_if($day === null || $day->isWeekend(), 404);

        return $day;
    }

    protected function blocked(): ?JsonResponse
    {
        return $this->timesheets->enabled() ? null : response()->json(['message' => __('erp.mobile.errors.module_off'), 'code' => 'module_off'], 403);
    }
}
