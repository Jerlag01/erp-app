<?php

namespace App\Actions\Projects;

use App\Actions\Batteries\BatteryDocument;
use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Models\BatteryDossier;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A 604 project (batteries) in Flanders or Brussels that is closed gets a 609 project (battery room) for
 * the same COW site, once, with the same project number (604-0012-02GAM → 609-0012-02GAM) unless the 609
 * already has that number. The PO number is not taken over: Proximus gives a new one later, which is
 * added on the project. The 609 waits for that PO.
 *
 * The 609s that follow the closed 604 (also one made by hand before) get the pictures of the 604 (its
 * picture zone and the photos of its battery dossier) and the single-line diagram of the battery dossier
 * in "Electrical schematics". The files are copies: the two projects keep their own.
 */
class CreateFollowUpProject
{
    public const SOURCE_CATEGORY = '604';

    public const FOLLOW_UP_CATEGORY = '609';

    /**
     * Areas (work site areas) that get a follow-up project.
     */
    public const AREAS = ['1-VLA', '2-VLA', '3-BRU'];

    public function applies(Project $project): bool
    {
        $area = strtoupper(str_replace(' ', '', (string) $project->workSite?->area?->name));

        return $project->category?->code === self::SOURCE_CATEGORY
            && $project->status === ProjectStatus::Closed
            && $project->work_site_id !== null
            && in_array($area, self::AREAS, true);
    }

    /**
     * Returns the 609 made now, if any.
     */
    public function handle(Project $project): ?Project
    {
        if ($project->category?->code !== self::SOURCE_CATEGORY || $project->status !== ProjectStatus::Closed) {
            return null;
        }

        $category = ProjectCategory::query()->where('code', self::FOLLOW_UP_CATEGORY)->first();

        if (! $category) {
            return null;
        }

        $followUps = Project::query()->where('source_project_id', $project->id)->where('project_category_id', $category->id)->get();
        $created = null;

        if ($followUps->isEmpty() && $this->applies($project)) {
            $created = Project::query()->create([
                'project_category_id' => $category->id,
                'number' => self::sameNumber($project, $category),
                'work_site_id' => $project->work_site_id,
                'status' => ProjectStatus::WaitingForPo,
                'notes' => __('battery_rooms.follow_up_note', ['reference' => $project->reference]),
                'created_by' => $project->created_by,
                'source_project_id' => $project->id,
            ]);

            $created->teams()->sync($project->teams()->pluck('teams.id'));
            $created->leaders()->sync($project->leaders()->pluck('users.id'));
            $followUps->push($created);
        }

        foreach ($followUps as $followUp) {
            $this->copyPictures($project, $followUp);
            $this->exportSingleLineDiagram($project, $followUp);
        }

        return $created;
    }

    /**
     * The number of the 604, when the 609 numbers the same way and does not use it yet; otherwise null,
     * so the 609 gets its next free number.
     */
    public static function sameNumber(?Project $source, ?ProjectCategory $category): ?int
    {
        if ($source === null || $category === null || $source->number === null || $source->category?->numbering !== $category->numbering) {
            return null;
        }

        return $category->projects()->where('number', $source->number)->exists() ? null : (int) $source->number;
    }

    /**
     * The pictures of the 604 (latest versions in its picture zone) and the photos of its battery dossier;
     * a picture the 609 already has by that name is not copied again (the 604 can be closed again).
     */
    protected function copyPictures(Project $project, Project $followUp): void
    {
        $disk = Storage::disk(ProjectFile::DISK);
        $directory = ProjectFile::directoryFor($followUp, ProjectFileSection::Images);
        $present = $followUp->filesIn(ProjectFileSection::Images)->pluck('name')->all();

        $project->filesIn(ProjectFileSection::Images)->with('latestVersion')->get()->each(function (ProjectFile $file) use ($disk, $directory, $followUp, $present): void {
            $version = $file->latestVersion;

            if ($version && ! in_array(basename($version->original_name), $present, true) && $disk->exists($version->path)) {
                $path = $directory.'/'.Str::random(8).'-'.basename($version->path);
                $disk->copy($version->path, $path);
                ProjectFile::store($followUp, ProjectFileSection::Images, $path, $version->original_name);
            }
        });

        $dossier = $this->dossier($project);
        $dossierDisk = Storage::disk(BatteryDossier::DISK);

        foreach (array_values((array) $dossier?->photos) as $index => $photo) {
            $name = Str::slug($project->reference).'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.'.strtolower(pathinfo((string) $photo, PATHINFO_EXTENSION) ?: 'jpg');

            if (! is_string($photo) || in_array($name, $present, true) || ! $dossierDisk->exists($photo)) {
                continue;
            }

            $path = $directory.'/'.Str::random(8).'-'.$name;
            $disk->put($path, (string) $dossierDisk->get($photo));
            ProjectFile::store($followUp, ProjectFileSection::Images, $path, $name);
        }
    }

    /**
     * The single-line diagram ("eendraadschema") of the 604's battery dossier as a PDF in the 609's
     * electrical schematics, once.
     */
    protected function exportSingleLineDiagram(Project $project, Project $followUp): void
    {
        $dossier = $this->dossier($project);

        if ($dossier === null) {
            return;
        }

        $name = BatteryDocument::filename($dossier, 'single_line');

        if ($followUp->filesIn(ProjectFileSection::Schematics)->where('name', $name)->exists()) {
            return;
        }

        try {
            $pdf = app(BatteryDocument::class)->render($dossier, 'single_line');
        } catch (Throwable $exception) {
            Log::warning('The single-line diagram of the 604 could not be put in the 609', ['project' => $project->id, 'follow_up' => $followUp->id, 'error' => $exception->getMessage()]);

            return;
        }

        $path = ProjectFile::directoryFor($followUp, ProjectFileSection::Schematics).'/'.Str::random(8).'-'.$name;
        Storage::disk(ProjectFile::DISK)->put($path, $pdf);
        ProjectFile::store($followUp, ProjectFileSection::Schematics, $path, $name);
    }

    protected function dossier(Project $project): ?BatteryDossier
    {
        return BatteryDossier::query()->where('project_id', $project->id)->latest('id')->first();
    }
}
