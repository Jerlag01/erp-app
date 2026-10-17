<?php

namespace App\Actions;

use App\Enums\AbsenceType;
use App\Models\Absence;
use App\Models\User;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * An employee reports sickness: it is recorded straight away as a medical absence (no approval), with
 * the certificate when there is one, and the administrators are told. The certificate can still be
 * added later, as long as the administration has not uploaded one.
 */
class ReportSickness
{
    /**
     * @param  array{start_date: string, end_date: string, half_day?: bool|null, note?: string|null}  $data
     * @param  string|null  $documentPath  a file already stored on Absence::DISK
     */
    public function handle(User $user, array $data, ?string $documentPath = null, ?string $documentName = null): Absence
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $halfDay = (bool) ($data['half_day'] ?? false) && $start->isSameDay($end);

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => __('erp.vacations.errors.end_before_start')]);
        }

        if (WorkingDays::count($start, $end, $halfDay) <= 0) {
            throw ValidationException::withMessages(['start_date' => __('erp.vacations.errors.no_working_days')]);
        }

        if (Absence::query()->where('user_id', $user->id)->overlapping($start->toDateString(), $end->toDateString())->exists()) {
            throw ValidationException::withMessages(['start_date' => __('erp.absences.errors.overlap')]);
        }

        $absence = Absence::query()->create([
            'user_id' => $user->id,
            'type' => AbsenceType::Medical,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'half_day' => $halfDay,
            'note' => filled($data['note'] ?? null) ? mb_substr(trim((string) $data['note']), 0, 1000) : null,
            'created_by' => $user->id,
        ]);

        if (filled($documentPath)) {
            $absence->attachDocument($documentPath, $documentName, Absence::SOURCE_EMPLOYEE);
        }

        app(NotifyAbsenceAdministrators::class)->handle($absence->load('user'), NotifyAbsenceAdministrators::REPORTED);

        return $absence;
    }
}
