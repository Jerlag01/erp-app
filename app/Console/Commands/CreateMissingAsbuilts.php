<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\Asbuilts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Makes the as-builts that are missing now that they start at "to be executed": every project that is
 * to be executed, in progress or executed (not archived, not of an excluded category) and has none yet
 * gets one, with the standard files of its COW-code, exactly as when its status changes. Safe to run
 * again: projects that have an as-built are left alone.
 */
#[Signature('asbuilts:create-missing {--dry-run : Only list the projects that would get an as-built}')]
#[Description('Create the missing as-builts of projects that are to be executed, in progress or executed')]
class CreateMissingAsbuilts extends Command
{
    public function handle(Asbuilts $asbuilts): int
    {
        if (! $asbuilts->enabled()) {
            $this->components->warn('The as-built module is off: nothing to do.');

            return self::SUCCESS;
        }

        $projects = Project::query()
            ->with('category')
            ->whereIn('status', array_values(array_filter(ProjectStatus::cases(), fn (ProjectStatus $status): bool => Asbuilts::startsAt($status))))
            ->whereNull('archived_at')
            ->whereDoesntHave('asbuilt')
            ->orderBy('id')
            ->get()
            ->filter(fn (Project $project): bool => $asbuilts->appliesTo($project))
            ->values();

        if ($projects->isEmpty()) {
            $this->components->info('Every project that needs an as-built has one.');

            return self::SUCCESS;
        }

        $this->table(['Project', 'Status'], $projects->map(fn (Project $project): array => [$project->reference, $project->status->getLabel()])->all());

        if ($this->option('dry-run')) {
            $this->components->info("{$projects->count()} project(s) would get an as-built (dry run, nothing made).");

            return self::SUCCESS;
        }

        $made = $projects->filter(fn (Project $project): bool => $asbuilts->createFor($project) !== null)->count();
        $this->components->info("As-builts made: {$made}.");

        return self::SUCCESS;
    }
}
