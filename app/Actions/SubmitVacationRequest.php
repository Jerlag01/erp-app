<?php

namespace App\Actions;

use App\Enums\VacationStatus;
use App\Enums\VacationType;
use App\Models\Absence;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\Timesheets;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Files a leave request for a user after checking the period and the remaining balance, then lets the
 * people who may approve it know. Vacation comes out of the yearly allowance; overtime taken as paid
 * leave out of the overtime balance of the prestation sheets (hours of a working day per day).
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

            if ($type === VacationType::Overtime) {
                $this->checkOvertime($user, $start, $end, $days);
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
     * Overtime leave: not on days with another absence, and not more hours than the overtime balance.
     */
    protected function checkOvertime(User $user, Carbon $start, Carbon $end, float $days): void
    {
        if (Absence::query()->where('user_id', $user->id)->overlapping($start->toDateString(), $end->toDateString())->exists()) {
            throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.overlap')]);
        }

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
