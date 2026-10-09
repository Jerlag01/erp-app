<?php

namespace App\Actions\Interventions;

use App\Enums\InterventionCategoryKind;
use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\InterventionReports\InterventionReportResource;
use App\Models\InterventionCategory;
use App\Models\InterventionReport;
use App\Models\InterventionReportPosition;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\Interventions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The employee closes an intervention with all details: categories, COW code, room, material
 * used/freed, breaker/fuse positions, modified boards, time worked, co-workers and remarks.
 * The execution date is set here. The intervention then waits for review by an administrator
 * (project status "executed"), the PDF is made and the administrators are notified.
 */
class CloseIntervention
{
    public function __construct(protected Interventions $interventions, protected InterventionPdf $pdf) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Project $project, array $data, User $user): InterventionReport
    {
        if (! $this->interventions->canClose($user, $project)) {
            abort(403);
        }

        $categories = InterventionCategory::query()->ofKind(InterventionCategoryKind::Category)->whereKey((array) ($data['categories'] ?? []))->get();

        if ($categories->isEmpty()) {
            throw ValidationException::withMessages(['categories' => __('erp.interventions.errors.categories')]);
        }

        $directions = $this->interventions->directions($categories);
        $workSite = WorkSite::query()->find($data['work_site_id'] ?? $project->work_site_id);
        $materials = $this->materials((array) ($data['materials'] ?? []), $directions);
        $positions = $this->positions((array) ($data['positions'] ?? []));
        $boards = self::boardNames((array) ($data['modified_boards'] ?? []));
        $coWorkers = User::query()->active()->whereKey((array) ($data['co_workers'] ?? []))->whereKeyNot($user->id)->pluck('id')->all();
        $hours = round((float) ($data['hours_worked'] ?? 0), 2);

        if ($hours <= 0) {
            throw ValidationException::withMessages(['hours_worked' => __('erp.interventions.errors.hours')]);
        }

        $report = DB::transaction(function () use ($project, $data, $user, $categories, $workSite, $materials, $positions, $boards, $coWorkers, $hours): InterventionReport {
            $project->interventionCategories()->sync($categories->modelKeys());
            $project->update(['status' => ProjectStatus::Executed]);

            $report = InterventionReport::query()->updateOrCreate(['project_id' => $project->id], [
                'status' => InterventionReportStatus::Pending,
                'work_site_id' => $workSite?->id,
                'cow_code' => $workSite?->cow_code ?? $project->cow_code,
                'room' => $data['room'] ?? null,
                'hours_worked' => $hours,
                'remarks' => $data['remarks'] ?? null,
                'categories' => $categories->pluck('name')->all(),
                'modified_boards' => $boards,
                'executed_at' => now(),
                'closed_by' => $user->id,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);

            $report->materials()->delete();
            $report->materials()->createMany($materials);
            $report->positions()->delete();
            $report->positions()->createMany($positions);
            $report->coWorkers()->sync($coWorkers);

            return $report;
        });

        $this->pdf->store($report->fresh());
        $this->notifyAdministrators($report, $user);

        return $report;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  list<MaterialDirection>  $directions
     * @return list<array<string, mixed>>
     */
    protected function materials(array $rows, array $directions): array
    {
        $materials = [];

        foreach (array_values($rows) as $index => $row) {
            $item = filled($row['stock_item_id'] ?? null) ? StockItem::query()->find($row['stock_item_id']) : null;
            $description = trim((string) ($row['description'] ?? '')) ?: $item?->name;

            if (blank($description)) {
                continue;
            }

            $direction = MaterialDirection::tryFrom((string) ($row['direction'] ?? '')) ?? $directions[0];

            if (! in_array($direction, $directions, true)) {
                throw ValidationException::withMessages(["materials.{$index}.direction" => __('erp.interventions.errors.direction')]);
            }

            $materials[] = [
                'stock_item_id' => $item?->id,
                'description' => $description,
                'quantity' => max(0.001, (float) ($row['quantity'] ?? 1)),
                'unit_id' => $row['unit_id'] ?? $item?->unit_id,
                'direction' => $direction,
            ];
        }

        return $materials;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    protected function positions(array $rows): array
    {
        $positions = [];

        foreach (array_values($rows) as $index => $row) {
            $board = self::boardNames([$row['board_name'] ?? ''])[0] ?? null;
            $number = strtoupper(trim((string) ($row['number'] ?? '')));

            if (! $board || $number === ''
                || ! in_array($row['current'] ?? null, InterventionReportPosition::CURRENTS, true)
                || ! in_array($row['device'] ?? null, InterventionReportPosition::DEVICES, true)
                || ! in_array($row['supply'] ?? null, InterventionReportPosition::SUPPLIES, true)) {
                throw ValidationException::withMessages(["positions.{$index}.number" => __('erp.interventions.errors.position')]);
            }

            $positions[] = [
                'board_name' => $board,
                'current' => $row['current'],
                'device' => $row['device'],
                // "Q12" typed as number is stored as "12".
                'number' => ltrim($number, $row['device']) ?: $number,
                'supply' => $row['supply'],
            ];
        }

        return $positions;
    }

    /**
     * Board names as typed, trimmed, in capitals and without doubles: "td-01 " → "TD-01".
     *
     * @param  array<int, mixed>  $names
     * @return list<string>
     */
    public static function boardNames(array $names): array
    {
        return array_values(array_unique(array_filter(array_map(fn (mixed $name): string => mb_substr(mb_strtoupper(trim((string) $name)), 0, 100), $names), 'filled')));
    }

    protected function notifyAdministrators(InterventionReport $report, User $closer): void
    {
        $project = $report->project;

        User::query()->active()->with('role')->get()->filter(fn (User $user): bool => $user->isAdmin() && $user->id !== $closer->id)
            ->each(fn (User $admin) => Notification::make()
                ->title(__('erp.interventions.notifications.closed_title', ['reference' => $project->reference], $admin->preferredLocale()))
                ->body(__('erp.interventions.notifications.closed_body', ['name' => $closer->name, 'wo' => $project->work_order_number ?? '—'], $admin->preferredLocale()))
                ->icon('heroicon-o-clipboard-document-check')
                ->warning()
                ->actions([
                    Action::make('review')
                        ->label(__('erp.interventions.review', locale: $admin->preferredLocale()))
                        ->url(InterventionReportResource::getUrl('view', ['record' => $report], panel: 'admin')),
                ])
                ->sendToDatabase($admin));
    }
}
