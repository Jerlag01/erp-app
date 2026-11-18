<?php

namespace App\Console\Commands;

use App\Actions\Gpw\SyncGpwStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gpw:sync-statuses')]
#[Description('Brings approved GPW requests in step with their project (planned, in progress, executed) as the days go by')]
class SyncGpwStatuses extends Command
{
    public function handle(SyncGpwStatus $sync): int
    {
        $this->info(__('erp.gpw.sync_done', ['count' => $sync->all()]));

        return self::SUCCESS;
    }
}
