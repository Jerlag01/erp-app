<?php

namespace App\Console\Commands;

use App\Actions\Employees\EndEmployment;
use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('employees:end-notice')]
#[Description('Employees whose notice period has ended are no longer in service (login off), from the day after its end date')]
class EndNoticePeriods extends Command
{
    public function handle(EndEmployment $end): int
    {
        $ended = 0;

        Employee::query()->whereNull('left_on')->whereNotNull('notice_ends_on')->whereDate('notice_ends_on', '<', today())
            ->each(function (Employee $employee) use ($end, &$ended): void {
                $end->handle($employee, $employee->notice_ends_on->toDateString(), __('erp.employees.notice.reason', [], 'nl'));
                $ended++;
            });

        $this->components->info("Notice periods ended: {$ended}.");

        return self::SUCCESS;
    }
}
