<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Interventions\CloseIntervention;
use App\Enums\InterventionCategoryKind;
use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use App\Filament\Resources\Projects\Actions\CloseInterventionAction;
use App\Filament\Resources\Projects\RelationManagers\PartsRelationManager;
use App\Http\Controllers\Controller;
use App\Models\InterventionCategory;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\Interventions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Interventions in the mobile app: the ones the employee sees (as under Interventions on the
 * platform) and the closing form, the same as "Close intervention" on the project page, without a
 * signature. The intervention then waits for review by an administrator.
 */
class InterventionController extends Controller
{
    public function __construct(protected Interventions $interventions) {}

    public function index(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $projects = $this->interventions->visibleTo($request->user())
            ->with(['category', 'workSite', 'interventionReport', 'interventionCategories'])
            // Still to close or sent back first, then the newest.
            ->orderByRaw('exists (select 1 from intervention_reports where intervention_reports.project_id = projects.id and intervention_reports.status <> ?) asc', [InterventionReportStatus::Rejected->value])
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $projects->map(fn (Project $project): array => $this->summary($project, $request->user()))->values()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project = $this->find($user, $id);
        $canClose = $this->interventions->canClose($user, $project);
        $report = $project->interventionReport;
        $current = $project->interventionCategories()->pluck('intervention_categories.id')->all();
        $defaults = $canClose ? CloseInterventionAction::defaults($project) : null;
        $workSite = WorkSite::query()->find($defaults['work_site_id'] ?? $project->work_site_id);

        return response()->json(['data' => [
            ...$this->summary($project, $user),
            'overview' => array_values(array_filter([
                ['label' => __('erp.interventions.wo_number'), 'value' => $project->work_order_number],
                ['label' => __('erp.interventions.cow_code'), 'value' => $project->workSite?->cow_code ?? $project->cow_code],
                ['label' => __('erp.batteries.overview.address'), 'value' => $project->addressLine() ?? $project->workSite?->addressLine()],
                ['label' => __('erp.interventions.subcategory'), 'value' => $project->interventionSubcategoryLabel()],
                ['label' => __('erp.interventions.categories'), 'value' => $project->interventionCategories->pluck('name')->implode(', ') ?: null],
                ['label' => __('erp.interventions.executed_by'), 'value' => $report ? implode(', ', $report->load(['closer', 'coWorkers'])->workerNames()) : null],
                ['label' => __('erp.interventions.executed_at'), 'value' => $report?->executed_at?->format('d/m/Y H:i')],
            ], fn (array $row): bool => filled($row['value']))),
            'maps_url' => $project->workSite?->googleMapsUrl(),
            'values' => $defaults === null ? null : [
                ...$defaults,
                'work_site_label' => $workSite ? $this->siteLabel($workSite) : null,
                'materials' => array_map(fn (array $material): array => [
                    ...$material,
                    'stock_item_label' => filled($material['stock_item_id'] ?? null) ? StockItem::query()->find($material['stock_item_id'])?->name : null,
                ], $defaults['materials']),
            ],
            'options' => $canClose ? [
                'categories' => InterventionCategory::query()
                    ->ofKind(InterventionCategoryKind::Category)
                    ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $current ?: [0]))
                    ->get()
                    ->map(fn (InterventionCategory $category): array => ['value' => $category->id, 'label' => $category->name, 'frees_material' => (bool) $category->frees_material])
                    ->values(),
                'units' => Unit::query()->orderBy('name')->get()->map(fn (Unit $unit): array => ['value' => $unit->id, 'label' => $unit->abbreviation ?: $unit->name])->values(),
                'co_workers' => User::query()->active()->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name'])->map(fn (User $colleague): array => ['value' => $colleague->id, 'label' => $colleague->name])->values(),
                'directions' => array_map(fn (MaterialDirection $direction): array => ['value' => $direction->value, 'label' => $direction->getLabel()], MaterialDirection::cases()),
                'stock' => modules()->stock(),
            ] : null,
            'labels' => $this->labels(),
        ]]);
    }

    /**
     * Closes the intervention with what the employee filled in (no signature); it goes to review.
     */
    public function close(Request $request, int $id, CloseIntervention $close): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project = $this->find($user, $id);
        abort_unless($this->interventions->canClose($user, $project), 403, __('erp.mobile.errors.intervention_closed'));

        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer'],
            'work_site_id' => ['required', 'integer', 'exists:work_sites,id'],
            'room' => ['required', 'string', 'max:100'],
            'hours_worked' => ['required', 'numeric', 'min:0.25', 'max:999'],
            'co_workers' => ['array'],
            'co_workers.*' => ['integer'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'modified_boards' => ['array', 'max:50'],
            'modified_boards.*' => ['string', 'max:100'],
            'materials' => ['array', 'max:100'],
            'materials.*.stock_item_id' => ['nullable', 'integer'],
            'materials.*.description' => ['required', 'string', 'max:255'],
            'materials.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'materials.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'materials.*.direction' => ['required', 'string'],
            'positions' => ['array', 'max:100'],
            'positions.*.board_name' => ['required', 'string', 'max:100'],
            'positions.*.current' => ['required', 'in:AC,DC'],
            'positions.*.device' => ['required', 'in:F,Q'],
            'positions.*.number' => ['required', 'string', 'max:20'],
            'positions.*.supply' => ['required', 'in:A,B'],
        ]);

        $close->handle($project, $data, $user);

        return response()->json(['message' => __('erp.interventions.closed').'. '.__('erp.interventions.closed_body'), 'data' => $this->summary($project->fresh(['category', 'workSite', 'interventionReport']), $user)]);
    }

    public function stockItems(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        abort_unless(modules()->stock(), 404);

        $items = PartsRelationManager::searchItems((string) $request->query('search', ''));
        $units = StockItem::query()->whereKey(array_keys($items))->pluck('unit_id', 'id');

        return response()->json(['data' => collect($items)->map(fn (string $label, int $id): array => ['id' => $id, 'label' => $label, 'unit_id' => $units[$id] ?? null])->values()]);
    }

    public function workSites(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $search = trim((string) $request->query('search', ''));
        $term = '%'.addcslashes($search, '%_\\').'%';

        $sites = WorkSite::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('cow_code', 'like', $term)
                ->orWhere('street', 'like', $term)
                ->orWhere('city', 'like', $term)))
            ->orderBy('cow_code')
            ->limit(30)
            ->get();

        return response()->json(['data' => $sites->map(fn (WorkSite $site): array => ['id' => $site->id, 'label' => $this->siteLabel($site)])->values()]);
    }

    protected function ensureEnabled(): void
    {
        abort_unless($this->interventions->enabled(), 403, __('erp.mobile.errors.module_off'));
    }

    protected function find(User $user, int $id): Project
    {
        $this->ensureEnabled();

        return $this->interventions->visibleTo($user)->with(['category', 'workSite', 'interventionReport', 'interventionCategories'])->findOrFail($id);
    }

    protected function siteLabel(WorkSite $site): string
    {
        return trim($site->cow_code.' · '.$site->city, ' ·');
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(Project $project, User $user): array
    {
        $report = $project->interventionReport;

        return [
            'id' => $project->id,
            'reference' => $project->reference,
            'subtitle' => $project->subtitle(),
            'cow_code' => $project->workSite?->cow_code ?? $project->cow_code,
            'city' => $project->workSite?->city,
            'status' => $project->status?->getLabel(),
            'report_status' => $report?->status->value,
            'report_status_label' => $report?->status->getLabel() ?? __('erp.interventions.not_closed'),
            'review_note' => $report?->status === InterventionReportStatus::Rejected ? $report->review_note : null,
            'can_close' => $this->interventions->canClose($user, $project),
        ];
    }

    /**
     * The form's texts, from the ERP so they match the platform.
     *
     * @return array<string, string>
     */
    protected function labels(): array
    {
        $keys = [
            'categories', 'cow_code', 'room', 'hours_worked', 'hours_help', 'hours_suffix', 'co_workers', 'co_workers_help', 'materials', 'add_material',
            'direction', 'positions', 'positions_help', 'add_position', 'board', 'device', 'position_number', 'supply', 'modified_boards',
            'modified_boards_help', 'remarks', 'remarks_help', 'executed_at_help', 'close', 'close_again', 'close_help', 'close_submit', 'review_note', 'review_status',
        ];

        return [
            ...collect($keys)->mapWithKeys(fn (string $key): array => [$key => __("erp.interventions.{$key}")])->all(),
            'what' => __('erp.interventions.sections.what'),
            'boards_remarks' => __('erp.interventions.sections.boards_remarks'),
            'device_F' => __('erp.interventions.devices.F'),
            'device_Q' => __('erp.interventions.devices.Q'),
            'materials_help_used' => __('erp.interventions.materials_help.used'),
            'materials_help_freed' => __('erp.interventions.materials_help.freed'),
            'materials_help_both' => __('erp.interventions.materials_help.both'),
            'item' => __('erp.projects.parts.item'),
            'description' => __('erp.fields.description'),
            'quantity' => __('erp.projects.parts.quantity'),
            'unit' => __('erp.projects.parts.unit'),
        ];
    }
}
