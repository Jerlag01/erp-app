<?php

namespace App\Support;

use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;

/**
 * The progress bar of the 610–650 projects (Proximus charging stations): fourteen steps, ticked by hand
 * (with today's date) or automatically:
 * - a PO number on the project completes "PO ontvangen";
 * - an offer in the offers completes "Aanvraag Proximus", "Werfbezoek" and "Offerte verstuurd";
 * - "invoiced" completes every step (and the project is closed).
 */
class ProjectProgress
{
    public const CATEGORIES = ['610', '620', '630', '640', '650'];

    public const STEPS = [
        1 => 'request', 2 => 'site_visit', 3 => 'offer_sent', 4 => 'po_received', 5 => 'site_preparation',
        6 => 'station_delivery', 7 => 'levelling', 8 => 'acx_earthing', 9 => 'commissioning', 10 => 'outgoing_connected',
        11 => 'as_built', 12 => 'inspection_normec', 13 => 'elastoflex', 14 => 'handed_over',
    ];

    public const PO_STEP = 4;

    /**
     * Steps an offer completes.
     */
    public const OFFER_STEPS = [1, 2, 3];

    public static function appliesTo(?Project $project): bool
    {
        return $project !== null && in_array((string) $project->category?->code, self::CATEGORIES, true);
    }

    /**
     * Every step with its state, for the progress bar.
     *
     * @return list<array{number: int, key: string, label: string, done: bool, date: ?string, automatic: bool}>
     */
    public static function steps(Project $project): array
    {
        $done = $project->steps()->get()->keyBy('step');

        return array_map(fn (int $number, string $key): array => [
            'number' => $number,
            'key' => $key,
            'label' => __('erp.project_progress.steps.'.$key),
            'done' => $done->has($number),
            'date' => $done->get($number)?->completed_on?->format('d/m/Y'),
            'automatic' => (bool) $done->get($number)?->automatic,
        ], array_keys(self::STEPS), self::STEPS);
    }

    public static function percentage(Project $project): int
    {
        return (int) round($project->steps()->count() / count(self::STEPS) * 100);
    }

    /**
     * Ticks or unticks a step by hand: ticked today.
     */
    public static function toggle(Project $project, int $step, User $by): void
    {
        abort_unless(array_key_exists($step, self::STEPS), 404);

        $existing = $project->steps()->where('step', $step)->first();

        if ($existing) {
            $existing->delete();

            return;
        }

        $project->steps()->create(['step' => $step, 'completed_on' => today(), 'completed_by' => $by->id, 'automatic' => false]);
    }

    /**
     * Completes a step automatically, unless it is done already.
     */
    public static function complete(Project $project, int $step, mixed $date = null): void
    {
        ProjectStep::query()->firstOrCreate(
            ['project_id' => $project->id, 'step' => $step],
            ['completed_on' => $date, 'automatic' => true],
        );
    }

    /**
     * The automatic steps, from what the project holds now.
     */
    public static function sync(Project $project): void
    {
        if (! self::appliesTo($project)) {
            return;
        }

        if ($project->poNumbers()->exists()) {
            self::complete($project, self::PO_STEP, $project->poNumbers()->oldest()->value('created_at'));
        }

        $offer = $project->filesIn(ProjectFileSection::Offers)->oldest()->first();

        if ($offer) {
            self::complete($project, 3, $offer->created_at);
            self::complete($project, 1);
            self::complete($project, 2);
        }

        if (in_array($project->status, [ProjectStatus::Invoiced, ProjectStatus::Closed], true)) {
            foreach (array_keys(self::STEPS) as $step) {
                self::complete($project, $step);
            }
        }
    }
}
