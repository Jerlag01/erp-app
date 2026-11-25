<?php

namespace App\Console\Commands;

use App\Actions\Projects\SyncPlanningStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('projects:sync-planning-statuses')]
#[Description('Moves 604 projects and interventions on with their planning as the days go by (in progress, executed)')]
class SyncPlanningStatuses extends Command
{
    public function handle(SyncPlanningStatus $sync): int
    {
        $this->info('Projects updated: '.$sync->all());

        return self::SUCCESS;
    }
}
