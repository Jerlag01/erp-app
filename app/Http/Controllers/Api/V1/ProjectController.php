<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GpwStatus;
use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\RelationManagers\PartsRelationManager;
use App\Http\Controllers\Controller;
use App\Models\GpwRequest;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectPart;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSiteContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The 600 and 610–650 projects a team of the employee executes, in the mobile app: the overview (reference,
 * description, address, COW code, contact person, project leaders), where the GPW stands and for which
 * period, the files the team may see (documents, pictures, electrical schematics and extra files; never
 * offers, invoices or plans) and the part list, to which they add the items they used.
 */
class ProjectController extends Controller
{
    public const CATEGORIES = ['600', '610', '620', '630', '640', '650'];

    /**
     * The upload zones the teams see in the app.
     */
    public const SECTIONS = [ProjectFileSection::Documents, ProjectFileSection::Schematics, ProjectFileSection::Extra, ProjectFileSection::Images];

    public const LINK_MINUTES = 10;

    /**
     * Open projects in those categories that one of the user's teams is assigned to.
     *
     * @return Builder<Project>
     */
    public static function teamProjects(User $user): Builder
    {
        return Project::query()
            ->whereHas('category', fn (Builder $query) => $query->whereIn('code', self::CATEGORIES))
            ->whereHas('teams', fn (Builder $query) => $query->whereKey($user->teamIds() ?: [0]))
            ->whereNull('archived_at')
            ->where('status', '!=', ProjectStatus::Closed->value);
    }

    public function index(Request $request): JsonResponse
    {
        $projects = self::teamProjects($request->user())
            ->with(['category', 'workSite', 'latestGpwRequest'])
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $projects->map(fn (Project $project): array => $this->summary($project))->values()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = $this->find($request->user(), $id);

        return response()->json(['data' => $this->detail($project, $request->user())]);
    }

    /**
     * Adds an item the employee used: from the stock list, or a free description for something that is not in it.
     */
    public function addPart(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project = $this->find($user, $id);

        $data = $request->validate([
            'stock_item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
            'description' => ['required_without:stock_item_id', 'nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $stockItem = filled($data['stock_item_id'] ?? null) ? StockItem::query()->find($data['stock_item_id']) : null;

        $project->parts()->create([
            'stock_item_id' => $stockItem?->id,
            'description' => filled($data['description'] ?? null) ? $data['description'] : $stockItem?->name,
            'quantity' => $data['quantity'],
            'unit_id' => $data['unit_id'] ?? $stockItem?->unit_id,
            'notes' => $data['notes'] ?? null,
            'added_by' => $user->id,
        ]);

        return response()->json(['message' => __('erp.mobile.projects.part_added'), 'data' => $this->detail($project, $user)], 201);
    }

    /**
     * Removes a line the employee added themselves (the rest of the list is managed on the platform).
     */
    public function removePart(Request $request, int $id, int $part): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project = $this->find($user, $id);
        $line = $project->parts()->whereKey($part)->firstOrFail();
        abort_unless($line->added_by === $user->id, 403, __('erp.mobile.projects.not_your_part'));

        $line->delete();

        return response()->json(['message' => __('erp.mobile.projects.part_removed'), 'data' => $this->detail($project, $user)]);
    }

    /**
     * A short-lived link to open one of the files the team sees, in the phone's browser (it has no session).
     */
    public function file(Request $request, int $id, int $file): JsonResponse
    {
        $project = $this->find($request->user(), $id);
        $found = $project->files()->whereKey($file)->whereIn('section', $this->sectionValues())->firstOrFail();

        return response()->json([
            'url' => URL::temporarySignedRoute('mobile.project-file', now()->addMinutes(self::LINK_MINUTES), ['project' => $project->id, 'file' => $found->id]),
        ]);
    }

    public function stockItems(Request $request): JsonResponse
    {
        abort_unless(modules()->stock(), 404);

        $items = PartsRelationManager::searchItems((string) $request->query('search', ''));
        $units = StockItem::query()->whereKey(array_keys($items))->pluck('unit_id', 'id');

        return response()->json(['data' => collect($items)->map(fn (string $label, int $id): array => ['id' => $id, 'label' => $label, 'unit_id' => $units[$id] ?? null])->values()]);
    }

    protected function find(User $user, int $id): Project
    {
        return self::teamProjects($user)->with(['category', 'workSite.contact', 'workSite.area.contacts', 'latestGpwRequest', 'leaders'])->findOrFail($id);
    }

    /**
     * @return list<string>
     */
    protected function sectionValues(): array
    {
        return array_map(fn (ProjectFileSection $section): string => $section->value, self::SECTIONS);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(Project $project): array
    {
        return [
            'id' => $project->id,
            'reference' => $project->reference,
            'description' => $project->short_description,
            'cow_code' => $project->workSite?->cow_code ?? $project->cow_code,
            'city' => $project->workSite?->city ?? $project->city,
            'status' => $project->status?->getLabel(),
            'gpw' => $this->gpw($project->latestGpwRequest),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function gpw(?GpwRequest $gpw): ?array
    {
        if ($gpw === null) {
            return null;
        }

        return [
            'status' => $gpw->status->value,
            'label' => $gpw->overviewStatus(),
            'approved' => in_array($gpw->status, [GpwStatus::Approved, GpwStatus::Planned], true),
            'starts_on' => $gpw->starts_on->toDateString(),
            'ends_on' => $gpw->ends_on->toDateString(),
            'period' => $gpw->period(),
            'days' => (int) $gpw->starts_on->diffInDays($gpw->ends_on) + 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detail(Project $project, User $user): array
    {
        $site = $project->workSite;
        $contacts = $site
            ? $site->effectiveContacts()->map(fn (WorkSiteContact $contact): array => ['name' => $contact->label(), 'phone' => $contact->phone, 'email' => $contact->email])->values()->all()
            : [];

        if ($contacts === [] && filled($project->client_name)) {
            $contacts = [['name' => trim($project->client_name.(filled($project->client_company) ? " ({$project->client_company})" : '')), 'phone' => $project->client_phone, 'email' => $project->client_email]];
        }

        $files = $project->files()
            ->whereIn('section', $this->sectionValues())
            ->with('latestVersion')
            ->orderBy('name')
            ->get()
            ->filter(fn (ProjectFile $file): bool => $file->latestVersion !== null)
            ->groupBy('section');

        return [
            ...$this->summary($project),
            'overview' => array_values(array_filter([
                ['label' => __('erp.mobile.projects.reference'), 'value' => $project->reference],
                ['label' => __('erp.fields.description'), 'value' => $project->short_description],
                ['label' => __('erp.interventions.cow_code'), 'value' => $site?->cow_code ?? $project->cow_code],
                ['label' => __('erp.batteries.overview.address'), 'value' => $project->addressLine() ?? $site?->addressLine()],
                ['label' => __('erp.projects.leaders'), 'value' => $project->leaders->pluck('name')->implode(', ') ?: null],
                ['label' => __('erp.mobile.projects.status'), 'value' => $project->status?->getLabel()],
            ], fn (array $row): bool => filled($row['value']))),
            'contacts' => $contacts,
            'leaders' => $project->leaders->map(fn (User $leader): array => ['name' => $leader->name, 'email' => $leader->email])->values(),
            'maps_url' => $site?->googleMapsUrl(),
            'planned_days' => $this->plannedDays($project),
            'file_sections' => collect(self::SECTIONS)
                ->filter(fn (ProjectFileSection $section): bool => $files->has($section->value))
                ->map(fn (ProjectFileSection $section): array => [
                    'section' => $section->value,
                    'label' => $section->getLabel(),
                    'files' => $files[$section->value]->map(fn (ProjectFile $file): array => [
                        'id' => $file->id,
                        'name' => $file->name,
                        'version' => $file->version,
                        'size' => $file->latestVersion->humanSize(),
                        'uploaded_at' => $file->latestVersion->created_at?->format('d/m/Y'),
                    ])->values(),
                ])
                ->values(),
            'parts' => $project->parts()->with(['unit', 'stockItem', 'adder'])->get()->map(fn (ProjectPart $part): array => [
                'id' => $part->id,
                'description' => $part->description,
                'quantity' => $part->quantityLabel(),
                'in_stock' => $part->stock_item_id !== null,
                'notes' => $part->notes,
                'added_by' => $part->adder?->name,
                'can_remove' => $part->added_by === $user->id,
            ])->values(),
            'options' => [
                'units' => Unit::query()->orderBy('name')->get()->map(fn (Unit $unit): array => ['value' => $unit->id, 'label' => $unit->abbreviation ?: $unit->name])->values(),
                'stock' => modules()->stock(),
            ],
        ];
    }

    /**
     * The coming days in the planning for this project, with who is planned.
     *
     * @return list<array{date: string, people: list<string>}>
     */
    protected function plannedDays(Project $project): array
    {
        if (! modules()->planning()) {
            return [];
        }

        return $project->planningItems()
            ->whereDate('date', '>=', today())
            ->with('user:id,name')
            ->orderBy('date')
            ->limit(200)
            ->get()
            ->groupBy(fn (PlanningItem $item): string => $item->date->toDateString())
            ->map(fn ($items, string $date): array => ['date' => $date, 'people' => $items->map(fn (PlanningItem $item): ?string => $item->user?->name)->filter()->unique()->values()->all()])
            ->take(60)
            ->values()
            ->all();
    }
}
