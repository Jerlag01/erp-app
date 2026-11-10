<?php

namespace App\Support;

use App\Actions\Projects\CreateFollowUpProject;
use App\Enums\ProjectFileSection;
use App\Models\BatteryDossier;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The documents of a finished battery replacement (604) in the client portal: once the project is "to
 * be invoiced" (or later), the client sees per project the COW-code and description, the photos of the
 * battery dossier, its measurement report, delivery note, visual inspection and invoice request (PDF,
 * made from the dossier as it is), and the measurement report of BAE and the waste report uploaded on
 * the project afterwards.
 */
class BatteryReports
{
    public const CATEGORY = '604';

    /** The dossier's documents the client gets, in this order. */
    public const DOCUMENTS = ['measurement', 'delivery', 'inspection', 'invoice_request'];

    /**
     * The 604 projects of the client user whose works are done ("to be invoiced", invoiced or closed).
     *
     * @return Builder<Project>
     */
    public static function query(?ClientUser $user): Builder
    {
        $query = Project::query()
            ->whereHas('category', fn (Builder $query) => $query->where('code', self::CATEGORY))
            ->whereIn('status', CreateFollowUpProject::DONE_STATUSES);

        return $user ? $user->restrictProjects($query) : $query->whereRaw('1 = 0');
    }

    public static function shows(?ClientUser $user, Project $project): bool
    {
        return $user instanceof ClientUser
            && $user->is_active
            && $user->may(ClientPortal::BATTERY_REPORTS)
            && modules()->projects()
            && self::query($user)->whereKey($project->id)->exists();
    }

    public static function dossier(Project $project): ?BatteryDossier
    {
        return BatteryDossier::query()->where('project_id', $project->id)->latest('id')->first();
    }

    /**
     * The photos of the battery dossier that are still on disk, by their index.
     *
     * @return array<int, string>
     */
    public static function photos(?BatteryDossier $dossier): array
    {
        $disk = Storage::disk(BatteryDossier::DISK);

        return array_filter((array) ($dossier?->photos ?? []), fn (mixed $path): bool => is_string($path) && $disk->exists($path));
    }

    /** The upload zones of the project the client sees, in this order. */
    public const UPLOADED = [ProjectFileSection::BaeMeasurementReports, ProjectFileSection::WasteReports];

    /**
     * The waste reports uploaded on the project.
     *
     * @return Collection<int, ProjectFile>
     */
    public static function wasteReports(Project $project): Collection
    {
        return self::uploaded($project, ProjectFileSection::WasteReports);
    }

    /**
     * The files uploaded in one of the UPLOADED zones (all of them without a zone).
     *
     * @return Collection<int, ProjectFile>
     */
    public static function uploaded(Project $project, ?ProjectFileSection $section = null): Collection
    {
        $sections = array_map(fn (ProjectFileSection $one): string => $one->value, $section ? [$section] : self::UPLOADED);

        return $project->files()->whereIn('section', $sections)->whereHas('latestVersion')->with('latestVersion')->orderBy('name')->get();
    }
}
