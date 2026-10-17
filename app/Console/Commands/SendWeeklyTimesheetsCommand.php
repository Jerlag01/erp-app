<?php

namespace App\Console\Commands;

use App\Actions\Timesheets\SendWeeklyTimesheets;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Sends the prestation sheets of the week (scheduled at the end of every week, see routes/console.php).
 */
class SendWeeklyTimesheetsCommand extends Command
{
    protected $signature = 'timesheets:send-weekly {--week= : A date in the week to send (default: this week)} {--all : Also send employees whose week was already sent}';

    protected $description = 'Send the weekly prestation sheet PDFs to the addresses of Settings > Prestations';

    public function handle(SendWeeklyTimesheets $send): int
    {
        if (! modules()->timesheets()) {
            $this->info('The prestations module is off.');

            return self::SUCCESS;
        }

        if (! $this->option('week') && ! settings()->get('timesheets.auto_send', true)) {
            $this->info('Automatic sending is off in Settings > Prestations.');

            return self::SUCCESS;
        }

        $monday = Timesheets::monday($this->option('week') ?: CarbonImmutable::today());
        $result = $send->handle($monday, onlyUnsent: ! $this->option('all'));

        if (! $result['sent']) {
            $this->warn((string) $result['error']);

            return self::SUCCESS;
        }

        $this->info("Sent {$result['count']} prestation sheet(s) of week {$monday->isoWeek()} ({$result['personal']} to the employees themselves).");

        return self::SUCCESS;
    }
}
