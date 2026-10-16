<?php

namespace App\Actions\Projects;

use App\Actions\Batteries\BatteryDocument;
use App\Enums\ProjectStatus;
use App\Models\BatteryDossier;
use App\Models\EnersysDossier;
use App\Models\InterventionReport;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\ProjectProgress;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Closing a project: every file of the project (latest versions, per section), the intervention report
 * and the battery documents go into one ZIP, the project gets the status "closed" and is archived.
 */
class CloseProject
{
    public const DISK = 'local';

    public function __construct(protected BatteryDocument $batteryDocuments) {}

    public function handle(Project $project): void
    {
        if ($project->archived_at !== null) {
            return;
        }

        ProjectProgress::sync($project->forceFill(['status' => ProjectStatus::Closed]));

        $path = $this->zip($project);

        $project->forceFill([
            'status' => ProjectStatus::Closed,
            'archived_at' => now(),
            'archive_path' => $path,
        ])->saveQuietly();
    }

    /**
     * Builds the ZIP on the private disk; returns its path.
     */
    public function zip(Project $project): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'project').'.zip';
        $zip = new ZipArchive;
        $zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $used = [];
        $add = function (string $name, string $contents) use ($zip, &$used): void {
            $name = $this->unique($name, $used);
            $used[] = $name;
            $zip->addFromString($name, $contents);
        };

        $project->files()->with('latestVersion')->get()->each(function (ProjectFile $file) use ($add): void {
            $version = $file->latestVersion;

            if ($version && Storage::disk(ProjectFile::DISK)->exists($version->path)) {
                $add(Str::slug($file->sectionEnum()->getLabel()).'/'.$version->original_name, (string) Storage::disk(ProjectFile::DISK)->get($version->path));
            }
        });

        $report = $project->interventionReport;

        if ($report instanceof InterventionReport && filled($report->pdf_path) && Storage::disk(InterventionReport::DISK)->exists($report->pdf_path)) {
            $add('interventie/'.basename($report->pdf_path), (string) Storage::disk(InterventionReport::DISK)->get($report->pdf_path));
        }

        collect([BatteryDossier::class, EnersysDossier::class])->flatMap(fn (string $model) => $model::query()->where('project_id', $project->id)->get())->each(function (BatteryDossier $dossier) use ($add): void {
            foreach (array_keys($dossier::documents()) as $document) {
                $add('batterijen/'.BatteryDocument::filename($dossier, $document), rescue(fn (): string => $this->batteryDocuments->render($dossier, $document), '', false));
            }
        });

        if ($zip->numFiles === 0) {
            $zip->addFromString('LEESMIJ.txt', __('erp.projects.archive.empty', ['reference' => $project->reference]));
        }

        $zip->close();

        $path = "projects/{$project->id}/archive/".Str::slug($project->reference).'.zip';
        Storage::disk(self::DISK)->put($path, (string) file_get_contents($temporary));
        @unlink($temporary);

        return $path;
    }

    /**
     * @param  list<string>  $used
     */
    protected function unique(string $name, array $used): string
    {
        $candidate = $name;
        $counter = 2;

        while (in_array($candidate, $used, true)) {
            $candidate = preg_replace('/(\.[^.\/]+)?$/', " ({$counter})$1", $name, 1);
            $counter++;
        }

        return $candidate;
    }

    /**
     * Opens an archived project again with the given status.
     */
    public function reopen(Project $project, ProjectStatus $status): void
    {
        $project->forceFill(['status' => $status, 'archived_at' => null])->saveQuietly();
    }
}
