<?php

namespace App\Console\Commands;

use App\Support\Nas\NasSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sends what waits for the NAS (and what failed, e.g. while the NAS was offline). With --all every file
 * of the platform is put in the outbox first: the first copy, or after the NAS was replaced.
 */
#[Signature('nas:sync {--all : Copy every file of the platform (again)} {--limit=500 : At most this many files per run}')]
#[Description('Copy the platform\'s files to the NAS (Synology)')]
class SyncToNas extends Command
{
    public function handle(NasSync $nas): int
    {
        if (! $nas->enabled()) {
            $this->components->warn('The NAS link is off (Settings > NAS).');

            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $this->components->info('Waiting for the NAS: '.$nas->queueAll().' file(s).');
        }

        $result = $nas->process((int) $this->option('limit'));
        $this->components->info("Copied to the NAS: {$result['sent']}, failed: {$result['failed']}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
