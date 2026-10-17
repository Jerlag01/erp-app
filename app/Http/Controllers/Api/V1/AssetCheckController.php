<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssetCheckResult;
use App\Enums\AssetProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\AssetCheck;
use App\Models\AssetCheckItem;
use App\Models\AssetProposal;
use App\Models\User;
use App\Policies\AssetCheckPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The yearly equipment control in the mobile app: the open lists of the user's teams, marking each
 * piece OK / not OK (OK sets the control date), filling in a missing model or serial number, remarks,
 * and proposing equipment that is not on the list (an administrator approves it). Same rules as the
 * list on the platform.
 */
class AssetCheckController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        return response()->json(['data' => $this->openChecks($request->user())->map(fn (AssetCheck $check): array => [
            'id' => $check->id,
            'team' => $check->team?->name,
            'year' => $check->year,
            'total' => $check->items->count(),
            'checked' => $check->checkedCount(),
            'not_ok' => $check->notOkCount(),
        ])->values()]);
    }

    public function show(Request $request, AssetCheck $assetCheck): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        $this->authorizeFill($request->user(), $assetCheck);

        return response()->json(['data' => $this->check($assetCheck)]);
    }

    public function updateItem(Request $request, AssetCheck $assetCheck, AssetCheckItem $item): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        /** @var User $user */
        $user = $request->user();
        $this->authorizeFill($user, $assetCheck);
        abort_unless($item->asset_check_id === $assetCheck->id, 404);

        $data = $request->validate([
            'result' => ['sometimes', 'nullable', Rule::enum(AssetCheckResult::class)],
            'remark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if (array_key_exists('result', $data)) {
            $item->record(AssetCheckResult::tryFrom((string) $data['result']), $user);
        }

        if (array_key_exists('remark', $data)) {
            $item->update(['remark' => filled($data['remark']) ? trim($data['remark']) : null]);
        }

        foreach (['model', 'serial_number'] as $field) {
            if (array_key_exists($field, $data)) {
                abort_unless($item->canComplete($field), 403);
                $item->complete($field, $data[$field]);
            }
        }

        return response()->json(['data' => $this->item($item->fresh(['asset.category', 'asset.vehicle', 'checker']))]);
    }

    public function remarks(Request $request, AssetCheck $assetCheck): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        $this->authorizeFill($request->user(), $assetCheck);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:5000']]);
        $assetCheck->update(['remarks' => filled($data['remarks'] ?? null) ? $data['remarks'] : null, 'remarks_by' => $request->user()->id, 'remarks_at' => now()]);

        return response()->json(['message' => __('erp.asset_checks.remarks_saved'), 'data' => $this->check($assetCheck->fresh())]);
    }

    public function propose(Request $request, AssetCheck $assetCheck): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        $this->authorizeFill($request->user(), $assetCheck);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', Rule::enum(AssetCheckResult::class)],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        AssetProposal::query()->create([
            ...$data,
            'asset_check_id' => $assetCheck->id,
            'team_id' => $assetCheck->team_id,
            'proposed_by' => $request->user()->id,
            'status' => AssetProposalStatus::Pending,
        ]);

        return response()->json(['message' => __('erp.asset_checks.proposed'), 'data' => $this->check($assetCheck->fresh())], 201);
    }

    /**
     * Open lists the user may fill in: those of their teams (all open lists for "assets.control").
     *
     * @return Collection<int, AssetCheck>
     */
    public static function openChecks(User $user)
    {
        if (! AssetCheckPolicy::enabled()) {
            return collect();
        }

        return AssetCheck::query()->open()->with(['team', 'items'])
            ->when(! AssetCheckPolicy::manages($user), fn ($query) => $query->whereIn('team_id', $user->teamIds()))
            ->orderByDesc('year')
            ->get()
            ->filter(fn (AssetCheck $check): bool => Gate::forUser($user)->allows('fill', $check))
            ->values();
    }

    protected function authorizeFill(User $user, AssetCheck $check): void
    {
        abort_unless(Gate::forUser($user)->allows('fill', $check), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function check(AssetCheck $check): array
    {
        $check->load(['team', 'remarksAuthor', 'items.asset.category', 'items.asset.vehicle', 'items.checker', 'proposals.category']);

        return [
            'id' => $check->id,
            'team' => $check->team?->name,
            'year' => $check->year,
            'remarks' => $check->remarks,
            'remarks_by' => $check->remarksAuthor?->name,
            'total' => $check->items->count(),
            'checked' => $check->checkedCount(),
            'items' => $check->items
                ->sortBy(fn (AssetCheckItem $item): string => (string) $item->asset?->asset_tag)
                ->map(fn (AssetCheckItem $item): array => $this->item($item))
                ->values(),
            'proposals' => $check->proposals->sortByDesc('id')->map(fn (AssetProposal $proposal): array => [
                'id' => $proposal->id,
                'name' => $proposal->name,
                'category' => $proposal->category?->name,
                'brand' => $proposal->brand,
                'model' => $proposal->model,
                'serial_number' => $proposal->serial_number,
                'status' => $proposal->status->value,
                'status_label' => $proposal->status->getLabel(),
            ])->values(),
            'categories' => AssetCategory::query()->orderBy('name')->get(['id', 'name'])->map(fn (AssetCategory $category): array => ['id' => $category->id, 'name' => $category->name]),
            'results' => array_map(fn (AssetCheckResult $result): array => ['value' => $result->value, 'label' => $result->getLabel()], AssetCheckResult::cases()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(AssetCheckItem $item): array
    {
        $asset = $item->asset;

        return [
            'id' => $item->id,
            'asset_tag' => $asset?->asset_tag,
            'name' => $asset?->name,
            'category' => $asset?->category?->name,
            'vehicle' => $asset?->vehicle?->plate_number,
            'model' => $asset?->model,
            'serial_number' => $asset?->serial_number,
            'can_complete_model' => $item->canComplete('model'),
            'can_complete_serial_number' => $item->canComplete('serial_number'),
            'inspection_date' => $asset?->inspection_date?->toDateString(),
            'result' => $item->result?->value,
            'remark' => $item->remark,
            'checked_at' => $item->checked_at?->toIso8601String(),
            'checked_by' => $item->checker?->name,
        ];
    }

    protected function blocked(): ?JsonResponse
    {
        return AssetCheckPolicy::enabled() ? null : response()->json(['message' => __('erp.mobile.errors.module_off'), 'code' => 'module_off'], 403);
    }
}
