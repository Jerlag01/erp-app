<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Marks an employee as no longer employed: the login is switched off (and open sessions end on
 * their next request), but every detail stays in the register. Reversible with rehire().
 */
class EndEmployment
{
    public function handle(Employee $employee, string $leftOn, ?string $reason = null): void
    {
        DB::transaction(function () use ($employee, $leftOn, $reason): void {
            $employee->update(['left_on' => $leftOn, 'leaving_reason' => $reason]);

            if ($user = $employee->user) {
                $user->forceFill(['is_active' => false, 'remember_token' => null])->save();

                // With database sessions, sign the person out everywhere right away.
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            }
        });
    }

    public function rehire(Employee $employee): void
    {
        DB::transaction(function () use ($employee): void {
            $employee->update(['left_on' => null, 'leaving_reason' => null]);
            $employee->user?->forceFill(['is_active' => true])->save();
        });
    }
}
