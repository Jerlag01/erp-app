<?php

namespace App\Console\Commands;

use App\Models\PlanningItem;
use App\Support\OutlookCalendar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('planning:sync-outlook {--from= : First day (Y-m-d), default today}')]
#[Description('Put the planning items from today (or --from) in the employees\' Outlook calendars, e.g. right after switching on the Outlook link in Settings > Planning')]
class SyncPlanningToOutlook extends Command
{
    public function handle(OutlookCalendar $outlook): int
    {
        if (! $outlook->enabled()) {
            $this->components->error('The Outlook calendar link is switched off or incomplete (Settings > Planning).');

            return self::FAILURE;
        }

        $from = $this->option('from') ?: today()->toDateString();
        $query = PlanningItem::query()->whereDate('date', '>=', $from)->with(['user', 'project.workSite'])->orderBy('date');
        $this->components->info("Planning items from {$from}: {$query->count()}");

        $query->chunkById(100, function ($items) use ($outlook): void {
            foreach ($items as $item) {
                $outlook->sync($item);
            }
        });

        $this->components->info('Done. Items that could not be sent are in the log.');

        return self::SUCCESS;
    }
}
