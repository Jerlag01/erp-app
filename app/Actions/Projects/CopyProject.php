<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectPart;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A new project from an existing (or archived) one: the same details, teams, leaders and part list,
 * optionally the files. Number, PO numbers, status, progress and intervention report start fresh.
 */
class CopyProject
{
    /**
     * @param  array{project_category_id?: int, work_site_id?: ?int, short_description?: ?string, with_files?: bool}  $options
     */
    public function handle(Project $source, User $by, array $options = []): Project
    {
        return DB::transaction(function () use ($source, $by, $options): Project {
            $copy = Project::query()->create([
                ...$source->only([
                    'project_category_id', 'work_site_id', 'short_description',
                    'intervention_subcategory_id', 'intervention_phase', 'intervention_tags',
                    'client_name', 'client_company', 'client_email', 'client_phone',
                    'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id', 'notes',
                ]),
                ...array_intersect_key($options, array_flip(['project_category_id', 'work_site_id', 'short_description'])),
                'status' => ProjectStatus::Offer,
                'created_by' => $by->id,
                'source_project_id' => $source->id,
            ]);

            $copy->teams()->sync($source->teams()->pluck('teams.id'));
            $copy->leaders()->sync($source->leaders()->pluck('users.id'));
            $copy->interventionCategories()->sync($source->interventionCategories()->pluck('intervention_categories.id'));

            $source->parts()->get()->each(fn (ProjectPart $part) => $copy->parts()->create($part->only(['stock_item_id', 'description', 'quantity', 'unit_id', 'notes'])));

            if ($options['with_files'] ?? false) {
                $source->files()->with('latestVersion')->get()->each(function (ProjectFile $file) use ($copy, $by): void {
                    $version = $file->latestVersion;

                    $disk = Storage::disk(ProjectFile::DISK);

                    if ($version && $disk->exists($version->path)) {
                        $path = ProjectFile::directoryFor($copy, $file->sectionEnum()).'/'.Str::random(40).'.'.pathinfo($version->path, PATHINFO_EXTENSION);
                        $disk->copy($version->path, $path);
                        ProjectFile::store($copy, $file->sectionEnum(), $path, $version->original_name, $by);
                    }
                });
            }

            return $copy;
        });
    }
}
