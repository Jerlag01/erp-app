<?php

namespace App\Actions\Gpw;

use App\Actions\Planning\SavePlanningItems;
use App\Enums\GpwStatus;
use App\Enums\PlanningKind;
use App\Enums\ProjectStatus;
use App\Models\GpwRequest;
use App\Models\PlanningItem;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Plans an approved GPW request: the members of a team are put in the planning for the project, on
 * days within the approved period only. The request and the project become "planned", which the
 * client also sees in the client portal.
 */
class PlanGpw
{
    public function __construct(protected SavePlanningItems $planning) {}

    /**
     * @param  array{team_id: int|string, date: string, until: string, start_time: string, end_time: string, include_weekends?: bool, notify_mail?: bool, notes?: string|null}  $data
     * @return array{created: list<PlanningItem>, skipped: list<string>}
     */
    public function handle(GpwRequest $request, User $actor, array $data): array
    {
        abort_unless($request->canBePlannedBy($actor), 403);

        $team = Team::query()->find($data['team_id'] ?? null) ?? throw ValidationException::withMessages(['team_id' => __('erp.gpw.errors.no_team')]);
        $memberIds = $team->members()->where('users.is_active', true)->pluck('users.id')->all();

        if ($memberIds === []) {
            throw ValidationException::withMessages(['team_id' => __('erp.gpw.errors.empty_team')]);
        }

        $from = CarbonImmutable::parse($data['date'])->startOfDay();
        $until = CarbonImmutable::parse($data['until'])->startOfDay();

        if ($from->isBefore($request->starts_on) || $until->isAfter($request->ends_on) || $until->isBefore($from)) {
            throw ValidationException::withMessages(['date' => __('erp.gpw.errors.outside_period', ['period' => $request->period()])]);
        }

        $result = $this->planning->create([
            ...$data,
            'kind' => PlanningKind::Project->value,
            'project_id' => $request->project_id,
            'user_ids' => $memberIds,
            'date' => $from->toDateString(),
            'until' => $until->toDateString(),
        ], $actor, $request);

        $request->update(['status' => GpwStatus::Planned, 'team_id' => $team->id, 'planned_at' => now(), 'planned_by' => $actor->id]);

        if (in_array($request->project->status, [ProjectStatus::ToBePlanned, ProjectStatus::ToBeExecuted], true)) {
            $request->project->update(['status' => ProjectStatus::Planned]);
        }

        return $result;
    }
}
