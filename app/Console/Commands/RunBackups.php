<?php

namespace App\Console\Commands;

use App\Models\BackupTarget;
use App\Support\Backups\BackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backups:run {--target= : Only this target (id or name)} {--now : Also when it is not due}')]
#[Description('Makes the backups that are due (or the chosen target now) and sends them to the NAS targets')]
class RunBackups extends Command
{
    public function handle(BackupManager $backups): int
    {
        $targets = BackupTarget::query()
            ->when($this->option('target'), fn ($query, $target) => $query->where(fn ($query) => $query->whereKey($target)->orWhere('name', $target)))
            ->get()
            ->filter(fn (BackupTarget $target): bool => $this->option('now') || $target->isDue());

        foreach ($targets as $target) {
            $this->line("Backup to {$target->name} …");
            $backup = $backups->run($target);
            $backup->status === 'done'
                ? $this->info("  {$backup->file_name} (".number_format((int) $backup->size / 1048576, 1).' MB)')
                : $this->error("  Failed: {$backup->message}");
        }

        return self::SUCCESS;
    }
}
