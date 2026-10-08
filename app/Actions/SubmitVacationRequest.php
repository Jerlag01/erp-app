<?php

namespace App\Actions;

use App\Enums\VacationStatus;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Files a vacation request for a user after checking the period and the remaining balance,
 * then lets the people who may approve it know.
 */
class SubmitVacationRequest
{
    /**
     * @param  array{start_date: string, end_date: string, half_day?: bool|null, reason?: string|null}  $data
     */
    public function handle(User $user, array $data): VacationRequest
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $halfDay = (bool) ($data['half_day'] ?? false) && $start->isSameDay($end);

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

        $request = DB::transaction(function () use ($user, $start, $end, $halfDay, $days, $data): VacationRequest {
            // Lock the user's row so two requests submitted at once cannot both pass the balance check.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($user->vacationRequests()->overlapping($start->toDateString(), $end->toDateString())->exists()) {
                throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.overlap')]);
            }

            $remaining = $user->vacationBalance($start->year)['remaining'];

            if ($days > $remaining) {
                throw ValidationException::withMessages(['end_date' => __('erp.vacations.errors.not_enough', [
                    'days' => rtrim(rtrim(number_format($days, 1, ',', ''), '0'), ','),
                    'remaining' => rtrim(rtrim(number_format(max(0, $remaining), 1, ',', ''), '0'), ','),
                ])]);
            }

            return $user->vacationRequests()->create([
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
}
