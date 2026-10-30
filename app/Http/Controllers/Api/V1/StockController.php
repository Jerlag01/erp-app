<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AdjustStock;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockItemSupplier;
use App\Models\StockLevel;
use App\Models\User;
use App\Support\StockCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Stock in the mobile app: scanning an item's QR label (or its EAN barcode) opens the item with its details
 * and the stock at every location, whichever location the employee belongs to. With "stock.adjust" they add
 * or take stock; during the yearly count they can only count.
 */
class StockController extends Controller
{
    public function __construct(protected StockCount $count) {}

    public static function canView(User $user): bool
    {
        return modules()->stock() && ($user->hasPermission('stock_items.view') || $user->hasPermission('stock.adjust'));
    }

    /**
     * The item behind a scanned code: the QR label's link ("…/q/12"), an EAN code, or a manufacturer or supplier reference.
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->ensureAllowed($request->user());
        $code = trim((string) $request->query('code', ''));
        abort_if($code === '', 404, __('erp.mobile.stock.not_found'));

        $item = preg_match('~/q/(\d+)(?:[/?#]|$)~', $code, $match)
            ? StockItem::query()->find((int) $match[1])
            : StockItem::query()
                ->where(fn (Builder $query) => $query
                    ->where('ean', $code)
                    ->orWhere('manufacturer_reference', $code)
                    ->orWhereHas('suppliers', fn (Builder $query) => $query->where('reference', $code)))
                ->orderByDesc('is_active')
                ->first();

        abort_if($item === null, 404, __('erp.mobile.stock.not_found'));

        return response()->json(['data' => ['id' => $item->id]]);
    }

    /**
     * Items by name (any language), reference or EAN, for when there is no label to scan.
     */
    public function search(Request $request): JsonResponse
    {
        $this->ensureAllowed($request->user());
        $term = '%'.addcslashes(trim((string) $request->query('search', '')), '%_\\').'%';

        $items = StockItem::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)->orWhere('name_nl', 'like', $term)->orWhere('name_fr', 'like', $term)->orWhere('name_en', 'like', $term)
                ->orWhere('manufacturer_reference', 'like', $term)->orWhere('ean', 'like', $term)
                ->orWhereHas('suppliers', fn (Builder $query) => $query->where('reference', 'like', $term)))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(['data' => $items->map(fn (StockItem $item): array => ['id' => $item->id, 'label' => trim($item->localizedName().' — '.$item->manufacturer_reference, ' —')])->values()]);
    }

    public function show(Request $request, StockItem $item): JsonResponse
    {
        $this->ensureAllowed($request->user());

        return response()->json(['data' => $this->detail($item, $request->user())]);
    }

    /**
     * Adds ("add") or takes ("take") an amount at one location.
     */
    public function adjust(Request $request, StockLevel $level, AdjustStock $adjust): JsonResponse
    {
        $user = $this->ensureAdjust($request->user());
        $data = $request->validate([
            'direction' => ['required', 'in:add,take'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $adjust->handle($level, ($data['direction'] === 'take' ? -1 : 1) * (float) $data['quantity'], $user, $data['note'] ?? null);

        return response()->json(['message' => __('erp.stock.saved'), 'data' => $this->detail($level->item->fresh(), $user)]);
    }

    /**
     * What is really there (stocktake, and the only change during the yearly count).
     */
    public function count(Request $request, StockLevel $level, AdjustStock $adjust): JsonResponse
    {
        $user = $this->ensureAdjust($request->user());
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $adjust->setTo($level, (float) $data['quantity'], $user, ($data['note'] ?? null) ?: __('erp.stock.stocktake'));

        return response()->json(['message' => __('erp.stock.saved'), 'data' => $this->detail($level->item->fresh(), $user)]);
    }

    protected function ensureAllowed(?User $user): User
    {
        abort_unless($user !== null && self::canView($user), 403, __('erp.mobile.errors.module_off'));

        return $user;
    }

    protected function ensureAdjust(?User $user): User
    {
        $user = $this->ensureAllowed($user);
        abort_unless($user->hasPermission('stock.adjust'), 403, __('erp.mobile.stock.no_adjust'));

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function detail(StockItem $item, User $user): array
    {
        $item->loadMissing(['unit', 'category.parent', 'manufacturer', 'suppliers.distributor', 'levels.location']);
        $unit = $item->unit;
        $counting = $this->count->active();

        return [
            'id' => $item->id,
            'name' => $item->localizedName(),
            'names' => collect(StockItem::LOCALES)->mapWithKeys(fn (string $locale): array => [$locale => $item->{'name_'.$locale}])->all(),
            'description' => $item->description,
            'notes' => $item->notes,
            'image_url' => filled($item->image) ? url(Storage::disk('public')->url($item->image)) : null,
            'ean' => $item->ean,
            'category' => $item->category?->fullName(),
            'manufacturer' => $item->manufacturer?->name,
            'manufacturer_reference' => $item->manufacturer_reference,
            'is_active' => (bool) $item->is_active,
            'unit' => ['label' => $unit->abbreviation ?: $unit->name, 'allows_decimals' => (bool) $unit->allows_decimals],
            'suppliers' => $item->suppliers->map(fn (StockItemSupplier $supplier): array => [
                'name' => $supplier->distributor?->name,
                'reference' => $supplier->reference,
                'preferred' => (bool) $supplier->is_preferred,
            ])->values(),
            // Every active location, whichever the employee belongs to.
            'levels' => $item->levels
                ->filter(fn (StockLevel $level): bool => (bool) $level->location?->is_active)
                ->sortBy(fn (StockLevel $level): string => mb_strtolower((string) $level->location->name))
                ->map(fn (StockLevel $level): array => [
                    'id' => $level->id,
                    'location' => $level->location->name,
                    'quantity' => (float) $level->quantity,
                    'quantity_label' => $unit->format($level->quantity),
                    'minimum_label' => $level->threshold() !== null ? $unit->format($level->threshold()) : null,
                    'low' => $level->isLow(),
                    'counted_at' => $counting ? $level->counted_at?->format('d/m H:i') : null,
                ])
                ->values(),
            'total_label' => $unit->format($item->levels->filter(fn (StockLevel $level): bool => (bool) $level->location?->is_active)->sum('quantity')),
            'yearly_count' => $counting,
            'can_adjust' => $user->hasPermission('stock.adjust'),
        ];
    }
}
