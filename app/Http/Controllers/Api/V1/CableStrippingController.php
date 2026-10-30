<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlanningKind;
use App\Http\Controllers\Controller;
use App\Models\CableStrippingLine;
use App\Models\PlanningItem;
use App\Models\User;
use App\Models\WorkSite;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cable stripping in the mobile app: the employee planned in the warehouse adds lines with the same fields
 * as on the platform (date, imputation, workers, start–end, weighings, bin, notes) and sees their own lines.
 * Lines are only added for a day the employee is planned in the warehouse.
 */
class CableStrippingController extends Controller
{
    /** How many days back a line may still be added (for a warehouse day). */
    public const DAYS_BACK = 14;

    /**
     * The module is on and the user is planned in the warehouse today.
     */
    public static function canUse(User $user): bool
    {
        return modules()->cableStripping() && self::plannedInWarehouse($user, today());
    }

    public static function plannedInWarehouse(User $user, CarbonInterface $date): bool
    {
        return PlanningItem::query()
            ->where('user_id', $user->id)
            ->where('kind', PlanningKind::Warehouse)
            ->whereDate('date', $date->toDateString())
            ->exists();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->ensureAllowed($request->user());

        $lines = CableStrippingLine::query()
            ->with('workers')
            ->where(fn ($query) => $query->where('created_by', $user->id)->orWhereHas('workers', fn ($query) => $query->whereKey($user->id)))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => [
            // The warehouse days a line may be added for: today and the recent days before.
            'dates' => PlanningItem::query()
                ->where('user_id', $user->id)
                ->where('kind', PlanningKind::Warehouse)
                ->whereDate('date', '<=', today()->toDateString())
                ->whereDate('date', '>=', today()->subDays(self::DAYS_BACK)->toDateString())
                ->orderByDesc('date')
                ->pluck('date')
                ->map(fn ($date): string => substr((string) $date, 0, 10))
                ->unique()
                ->values(),
            'workers' => User::query()->active()->orderBy('name')->get(['id', 'name'])->map(fn (User $worker): array => ['value' => $worker->id, 'label' => $worker->name])->values(),
            'labels' => collect(__('erp.cable_stripping.fields'))
                ->merge(['title' => __('erp.cable_stripping.title'), 'add' => __('erp.cable_stripping.add'), 'weighing' => __('erp.cable_stripping.weighing'), 'notes' => __('erp.fields.notes')]),
            'mine' => $lines->map(fn (CableStrippingLine $line): array => $this->line($line))->values(),
        ]]);
    }

    /**
     * Work sites by COW code or address, for the imputation (free text is allowed as well).
     */
    public function workSites(Request $request): JsonResponse
    {
        $this->ensureAllowed($request->user());
        $search = trim((string) $request->query('search', ''));
        $term = '%'.addcslashes($search, '%_\\').'%';

        $sites = WorkSite::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('cow_code', 'like', $term)
                ->orWhere('street', 'like', $term)
                ->orWhere('city', 'like', $term)))
            ->orderBy('cow_code')
            ->limit(30)
            ->get(['id', 'cow_code', 'street', 'city']);

        return response()->json(['data' => $sites->map(fn (WorkSite $site): array => [
            'id' => $site->id,
            'label' => trim($site->cow_code.' — '.trim($site->street.' '.$site->city), ' —'),
            'cow_code' => $site->cow_code,
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->ensureAllowed($request->user());
        $weight = ['nullable', 'numeric', 'min:0', 'max:100000'];

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.today()->subDays(self::DAYS_BACK)->toDateString()],
            'imputation' => ['nullable', 'string', 'max:100'],
            'workers' => ['nullable', 'array'],
            'workers.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'weight_before' => $weight,
            'empty_bin_weight' => $weight,
            'weight_after' => $weight,
            'bin_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! self::plannedInWarehouse($user, now()->parse($data['date']))) {
            throw ValidationException::withMessages(['date' => __('erp.mobile.cable_stripping.not_planned')]);
        }

        $line = DB::transaction(function () use ($data, $user): CableStrippingLine {
            $line = CableStrippingLine::query()->create([...collect($data)->except('workers')->all(), 'created_by' => $user->id]);
            $line->workers()->sync(filled($data['workers'] ?? null) ? $data['workers'] : [$user->id]);

            return $line;
        });

        return response()->json(['message' => __('erp.mobile.cable_stripping.saved'), 'data' => $this->line($line->load('workers'))], 201);
    }

    protected function ensureAllowed(?User $user): User
    {
        abort_unless($user !== null && modules()->cableStripping(), 403, __('erp.mobile.errors.module_off'));
        abort_unless(self::canUse($user), 403, __('erp.mobile.cable_stripping.not_planned'));

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function line(CableStrippingLine $line): array
    {
        return [
            'id' => $line->id,
            'date' => $line->date->toDateString(),
            'imputation' => $line->imputation,
            'workers' => $line->workerNames(),
            'start_time' => $line->start_time ? substr((string) $line->start_time, 0, 5) : null,
            'end_time' => $line->end_time ? substr((string) $line->end_time, 0, 5) : null,
            'hours' => $line->hours(),
            'weight_before' => $line->weight_before === null ? null : (float) $line->weight_before,
            'empty_bin_weight' => $line->empty_bin_weight === null ? null : (float) $line->empty_bin_weight,
            'weight_after' => $line->weight_after === null ? null : (float) $line->weight_after,
            'cable_before' => $line->cableBefore(),
            'stripped' => $line->strippedCable(),
            'waste' => $line->waste(),
            'bin_number' => $line->bin_number,
            'notes' => $line->notes,
        ];
    }
}
