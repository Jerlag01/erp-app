<?php

namespace App\Actions;

use App\Enums\VacationStatus;
use App\Enums\VacationType;
use App\Models\Absence;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\LeaveEntitlements;
use App\Support\Timesheets;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Files a leave request for a user after checking the period and the remaining balance, then lets the
 * people who may approve it know. Vacation comes out of the yearly allowance; overtime taken as paid
 * leave out of the overtime balance of the prestation sheets (hours of a working day per day); mobility
 * and seniority leave out of what is earned that year; sollicitatieverlof one day a week in the notice period.
 */
class SubmitVacationRequest
{
    /**
     * @param  array{type?: string|VacationType|null, start_date: string, end_date: string, half_day?: bool|null, reason?: string|null}  $data
     */
    public function handle(User $user, array $data): VacationRequest
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $halfDay = (bool) ($data['half_day'] ?? false) && $start->isSameDay($end);
        $type = $data['type'] ?? null;
        $type = $type instanceof VacationType ? $type : (VacationType::tryFrom((string) $type) ?? VacationType::Vacation);

        if ($type === VacationType::Overtime && ! app(Timesheets::class)->enabled()) {
            throw ValidationException::withMessages(['type' => __('erp.vacations.errors.overtime_unavailable')]);
        }

        // Mobility, seniority and sollicitatieverlof only for those who have them.
        if (! in_array($type, app(LeaveEntitlements::class)->requestable($user, app(Timesheets::class)->enabled()), true)) {
            throw ValidationException::withMessages(['type' => __('erp.vacations.errors.type_unavailable')]);
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => __('erp.vacations.errors.end_before_start')]);
        }

        if ($start->year !== $end->year) {
            throw ValidationException::withMessages(['end_date' => __('erp.vacations.errors.across_years')]);
        }

        $days = WorkingDays::count($start, $end, $halfDay);

        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.no_working_days')]);
        }

        $request = DB::transaction(function () use ($user, $start, $end, $halfDay, $days, $data, $type): VacationRequest {
            // Lock the user's row so two requests submitted at once cannot both pass the balance check.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($user->vacationRequests()->overlapping($start->toDateString(), $end->toDateString())->exists()) {
                throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.overlap')]);
            }

            if ($type !== VacationType::Vacation && Absence::query()->where('user_id', $user->id)->overlapping($start->toDateString(), $end->toDateString())->exists()) {
                throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.overlap')]);
            }

            if ($type === VacationType::Overtime) {
                $this->checkOvertime($user, $start, $end, $days);
            } elseif (in_array($type, [VacationType::Mobility, VacationType::Seniority, VacationType::JobSearch], true)) {
                $this->checkEarned($user, $type, $start, $end, $days, $halfDay);
            } elseif ($days > ($remaining = $user->vacationBalance($start->year)['remaining'])) {
                throw ValidationException::withMessages(['end_date' => __('erp.vacations.errors.not_enough', [
                    'days' => rtrim(rtrim(number_format($days, 1, ',', ''), '0'), ','),
                    'remaining' => rtrim(rtrim(number_format(max(0, $remaining), 1, ',', ''), '0'), ','),
                ])]);
            }

            return $user->vacationRequests()->create([
                'type' => $type,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'half_day' => $halfDay,
                'reason' => $data['reason'] ?? null,
                'status' => VacationStatus::Pending,
            ]);
        });

        app(NotifyVacationApprovers::class)->handle($request);

        return $request;
    }

    /**
     * Mobility and seniority leave: not more than what is earned for that year (seniority from the 25th
     * anniversary on). Sollicitatieverlof: one full day a week, within the notice period.
     */
    protected function checkEarned(User $user, VacationType $type, Carbon $start, Carbon $end, float $days, bool $halfDay): void
    {
        $entitlements = app(LeaveEntitlements::class);
        $format = fn (float $value): string => rtrim(rtrim(number_format(max(0, $value), 1, ',', ''), '0'), ',');

        if ($type === VacationType::JobSearch) {
            $notice = $entitlements->noticePeriod($user);

            if ($notice === null || $start->toDateString() < $notice['starts'] || $end->toDateString() > $notice['ends']) {
                throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.job_search_period', ['end' => $notice ? Carbon::parse($notice['ends'])->format('d/m/Y') : '—'])]);
            }

            if (! $start->isSameDay($end) || $halfDay) {
                throw ValidationException::withMessages(['end_date' => __('erp.vacations.errors.job_search_day')]);
            }

            if ($entitlements->jobSearchDayInWeek($user, $start)) {
                throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.job_search_week')]);
            }

            return;
        }

        $balance = $type === VacationType::Mobility ? $entitlements->mobility($user, $start->year) : $entitlements->seniority($user, $start->year);

        if ($type === VacationType::Seniority && ($balance['from'] === null || $start->toDateString() < $balance['from'])) {
            throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.seniority_from', ['date' => $balance['from'] ? Carbon::parse($balance['from'])->format('d/m/Y') : '—'])]);
        }

        if ($days > $balance['remaining']) {
            throw ValidationException::withMessages(['end_date' => __('erp.vacations.errors.not_enough', ['days' => $format($days), 'remaining' => $format($balance['remaining'])])]);
        }
    }

    /**
     * Overtime leave: not on days with another absence, and not more hours than the overtime balance.
     */
    protected function checkOvertime(User $user, Carbon $start, Carbon $end, float $days): void
    {
        $balance = app(Timesheets::class)->overtimeBalance($user);
        $hours = round($days * $balance['day_hours'], 2);

        if ($hours > $balance['available']) {
            $format = fn (float $value): string => rtrim(rtrim(number_format(max(0, $value), 2, ',', ''), '0'), ',');

            throw ValidationException::withMessages(['end_date' => __('erp.vacations.errors.not_enough_overtime', [
                'hours' => $format($hours),
                'available' => $format($balance['available']),
            ])]);
        }
    }
}
