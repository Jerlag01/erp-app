<?php

namespace App\Support;

use App\Actions\Projects\CreateFollowUpProject;
use App\Enums\ProjectFileSection;
use App\Models\BatteryDossier;
use App\Models\ClientUser;
use App\Models\MoveDossier;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The documents of a finished battery replacement (604) or battery move (605 with a move dossier) in the
 * client portal: once the project is "to be invoiced" (or later), the client sees per project the COW-code
 * and description, the photos of the dossier, its measurement report, delivery note, visual inspection
 * (and for a 604 the invoice request; PDF, made from the dossier as it is), and the files uploaded on the
 * project afterwards: the waste report (604 and every 605) and the measurement report of BAE (604).
 */
class BatteryReports
{
    public const CATEGORY = '604';

    /** Enersys and battery moves; only the moves are shown to the client. */
    public const MOVE_CATEGORY = '605';

    /** The projects with a waste report to upload. */
    public const WASTE_REPORT_CATEGORIES = [self::CATEGORY, self::MOVE_CATEGORY];

    /** The dossier's documents the client gets, in this order. */
    public const DOCUMENTS = ['measurement', 'delivery', 'inspection', 'invoice_request'];

    /** Those of a battery move (it has no invoice request). */
    public const MOVE_DOCUMENTS = ['measurement', 'delivery', 'inspection'];

    /**
     * The 604 projects of the client user whose works are done ("to be invoiced", invoiced or closed).
     *
     * @return Builder<Project>
     */
    public static function query(?ClientUser $user): Builder
    {
        $query = Project::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('category', fn (Builder $query) => $query->where('code', self::CATEGORY))
                ->orWhere(fn (Builder $query) => $query
                    ->whereHas('category', fn (Builder $query) => $query->where('code', self::MOVE_CATEGORY))
                    ->whereIn('projects.id', MoveDossier::query()->select('project_id'))))
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

    /**
     * The battery dossier of a 604, the move dossier of a 605.
     */
    public static function dossier(Project $project): ?BatteryDossier
    {
        $model = (string) $project->category?->code === self::CATEGORY ? BatteryDossier::class : MoveDossier::class;

        return $model::query()->where('project_id', $project->id)->latest('id')->first();
    }

    /**
     * The documents of the dossier the client gets.
     *
     * @return list<string>
     */
    public static function documents(?BatteryDossier $dossier): array
    {
        return match (true) {
            $dossier === null => [],
            $dossier instanceof MoveDossier => self::MOVE_DOCUMENTS,
            default => self::DOCUMENTS,
        };
    }

    /**
     * 604 and 605 projects, and interventions of the subcategory COLIBRI in phase 2.
     */
    public static function hasWasteReport(Project $project): bool
    {
        if (in_array((string) $project->category?->code, self::WASTE_REPORT_CATEGORIES, true)) {
            return true;
        }

        return $project->isIntervention()
            && mb_strtoupper(trim((string) $project->interventionSubcategory?->name)) === 'COLIBRI'
            && preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $project->intervention_phase))) === 'PHASE 2';
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
