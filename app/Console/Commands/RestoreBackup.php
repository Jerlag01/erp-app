<?php

namespace App\Console\Commands;

use App\Models\BackupTarget;
use App\Support\Backups\BackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * For when the platform itself is broken: lists the backups on a target and puts the chosen parts back.
 * With --file a backup zip on this server can be used (e.g. copied by hand from the NAS).
 */
#[Signature('backups:restore {target? : Target id or name} {backup? : Backup file name on the target} {--file= : A backup zip on this server instead} {--parts=database,files : database, files and/or code} {--with-env : Also put back the .env} {--force : Do not ask for confirmation}')]
#[Description('Restores the database, server files and/or code from a backup')]
class RestoreBackup extends Command
{
    public function handle(BackupManager $backups): int
    {
        $parts = array_values(array_intersect(BackupTarget::PARTS, array_map('trim', explode(',', (string) $this->option('parts')))));

        if ($parts === [] && ! $this->option('with-env')) {
            $this->error('Choose the parts to restore: --parts=database,files,code');

            return self::FAILURE;
        }

        if ($file = $this->option('file')) {
            if (! $this->option('force') && ! $this->confirm('Restore '.implode(', ', $parts).' from '.$file.'? The current data is replaced.')) {
                return self::FAILURE;
            }

            $backups->restoreFile($file, $parts, (bool) $this->option('with-env'));
            $this->info('Restored.');

            return self::SUCCESS;
        }

        $target = BackupTarget::query()->whereKey($this->argument('target'))->orWhere('name', $this->argument('target'))->first();

        if ($target === null) {
            $this->table(['Id', 'Name', 'Type', 'Host'], BackupTarget::query()->get(['id', 'name', 'type', 'host'])->toArray());
            $this->error('Name a target: backups:restore <target> <backup>');

            return self::FAILURE;
        }

        if (! $this->argument('backup')) {
            $this->table(['Backup', 'Size (MB)', 'Date'], collect($target->transport()->list())->map(fn (array $file): array => [
                $file['name'], number_format((int) $file['size'] / 1048576, 1), $file['modified'] ? date('d/m/Y H:i', $file['modified']) : '',
            ])->all());
            $this->line('Restore with: php artisan backups:restore '.$target->id.' <backup> --parts=database,files');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Restore '.implode(', ', $parts).' from '.$this->argument('backup').'? The current data is replaced.')) {
            return self::FAILURE;
        }

        $result = $backups->restore($target, (string) $this->argument('backup'), $parts, null, (bool) $this->option('with-env'));
        $result->status === 'done' ? $this->info('Restored.') : $this->error('Failed: '.$result->message);

        return $result->status === 'done' ? self::SUCCESS : self::FAILURE;
    }
}
