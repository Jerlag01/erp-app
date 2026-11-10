<?php

namespace App\Console\Commands;

use App\Models\NasSyncItem;
use App\Models\NasTarget;
use App\Support\Nas\NasSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Reads every file the platform has now (project files, as-builts, employees, prestation sheets,
 * absences, vehicles, incidents, toolbox meetings) and copies all of it to the NAS systems in one go,
 * in batches, until nothing waits: for the first copy, or after adding or replacing a NAS. What fails is
 * left for "nas:sync" (every 10 minutes) and listed at the end.
 */
#[Signature('nas:export {--target= : Only this NAS (its id); default every active NAS} {--batch=200 : Files per batch} {--queue-only : Only put everything in the queue; "nas:sync" sends it}')]
#[Description('Copy every current file of the platform to the NAS systems')]
class ExportToNas extends Command
{
    public function handle(NasSync $nas): int
    {
        $only = filled($this->option('target')) ? NasTarget::query()->find((int) $this->option('target')) : null;

        if (filled($this->option('target')) && ($only === null || ! $only->is_active)) {
            $this->components->error('No active NAS with id '.$this->option('target').' (Admin > NAS systems).');

            return self::FAILURE;
        }

        if (! $nas->enabled()) {
            $this->components->warn('No active NAS (Admin > NAS systems).');

            return self::SUCCESS;
        }

        $targets = $only ? new EloquentCollection([$only]) : NasTarget::query()->active()->orderBy('id')->get();
        $this->components->info('Reading the platform for: '.$targets->pluck('name')->implode(', '));

        $waiting = $nas->queueAll($only);
        $this->components->info("Files to copy: {$waiting}");

        if ($this->option('queue-only') || $waiting === 0) {
            return self::SUCCESS;
        }

        // Every waiting file once, in batches (by id, so failures are not tried again in this run).
        $pending = fn () => NasSyncItem::query()
            ->where('status', NasSyncItem::PENDING)
            ->whereIn('nas_target_id', $targets->modelKeys());
        $bar = $this->output->createProgressBar($pending()->count());
        $bar->start();
        $lastId = 0;
        $batch = max(1, (int) $this->option('batch'));

        while (($items = $pending()->where('id', '>', $lastId)->orderBy('id')->limit($batch)->get())->isNotEmpty()) {
            $nas->send($items);
            $lastId = $items->max('id');
            $bar->advance($items->count());
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['NAS', 'On the NAS', 'Waiting', 'Failed'], $targets->map(fn (NasTarget $target): array => [
            $target->name,
            $target->items()->where('status', NasSyncItem::DONE)->count(),
            $target->items()->where('status', NasSyncItem::PENDING)->count(),
            $target->items()->where('status', NasSyncItem::FAILED)->count(),
        ])->all());

        $failed = NasSyncItem::query()->whereIn('nas_target_id', $targets->modelKeys())->where('status', NasSyncItem::FAILED);

        if ($failed->exists()) {
            $this->components->warn('Some files could not be copied; "nas:sync" tries them again every 10 minutes. First errors:');
            $failed->with('nasTarget')->limit(10)->get()->each(fn (NasSyncItem $item) => $this->line("  [{$item->nasTarget?->name}] {$item->target}: {$item->last_error}"));

            return self::FAILURE;
        }

        $this->components->info('Everything is on the NAS.');

        return self::SUCCESS;
    }
}
