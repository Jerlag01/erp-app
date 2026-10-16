<?php

namespace App\Actions\Timesheets;

use App\Models\Project;
use App\Models\TimesheetDay;
use App\Models\TimesheetWeek;
use App\Models\User;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;

/**
 * Saves one day of a prestation sheet, from the Timesheet page and from the mobile app alike: the rows
 * (start, site, extra sites, end), the lunch break and the remarks. Imputation and description follow
 * the chosen project; the lunch break is always on for vacation, medical leave, training and holidays.
 */
class SaveTimesheetDay
{
    public function __construct(protected Timesheets $timesheets) {}

    /**
     * @param  array{rows?: array<int|string, mixed>, lunch_break?: mixed, remarks?: ?string}  $data
     */
    public function handle(User $employee, CarbonImmutable $day, array $data, User $by): TimesheetDay
    {
        $rows = Timesheets::normaliseRows((array) ($data['rows'] ?? []));

        foreach ($rows as $index => $row) {
            if ($row['project_id'] && ($project = Project::query()->with(['category', 'workSite'])->find($row['project_id']))) {
                $rows[$index]['imputation'] = Timesheets::imputation($project);
                $rows[$index]['description'] ??= $project->workSite?->cow_code ?? $project->cow_code;
            } else {
                $rows[$index]['project_id'] = null;
            }
        }

        $status = $this->timesheets->statuses($employee, $day, $day)[$day->toDateString()] ?? null;

        $record = TimesheetDay::query()->updateOrCreate(['user_id' => $employee->id, 'date' => $day->toDateString()], [
            'rows' => $rows,
            'lunch_break' => ($status['lunch_locked'] ?? false) || (bool) ($data['lunch_break'] ?? true),
            'remarks' => filled($data['remarks'] ?? null) ? mb_substr(trim((string) $data['remarks']), 0, 1000) : null,
            'updated_by' => $by->id,
        ]);

        TimesheetWeek::query()->firstOrCreate(
            ['user_id' => $employee->id, 'week_start' => Timesheets::monday($day)->toDateString()],
            ['plate_number' => $this->timesheets->plateFor($employee)],
        );

        return $record;
    }
}
