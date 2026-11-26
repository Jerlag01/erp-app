<?php

namespace App\Console\Commands;

use App\Actions\Projects\SendMonthlyProjectReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('projects:monthly-report')]
#[Description('Mails the monthly overview of open projects and overdue interventions to the administrators and the project leaders')]
class SendMonthlyProjectReports extends Command
{
    public function handle(SendMonthlyProjectReport $report): int
    {
        $this->info('Mails sent: '.$report->handle());

        return self::SUCCESS;
    }
}
