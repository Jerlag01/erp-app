<?php

namespace App\Http\Controllers;

use App\Enums\WasteRegion;
use App\Models\WasteEntry;
use App\Support\WasteTotals;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDFs of the waste registry: the full list or a regional battery list (exactly the rows shown
 * on screen, kept for 30 minutes under a token so long selections need no long URL), and the
 * totals per category.
 */
class WastePdfController
{
    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $period
     */
    public static function urlFor(string $list, array $ids, array $period = []): string
    {
        $token = Str::random(40);

        Cache::put("waste-pdf:{$token}", [
            'user' => auth()->id(),
            'list' => WasteRegion::tryFrom($list)?->value ?? 'all',
            'ids' => array_map('intval', $ids),
            'from' => $period['from'] ?? null,
            'until' => $period['until'] ?? null,
        ], now()->addMinutes(30));

        return route('waste.pdf', ['token' => $token]);
    }

    public function list(Request $request, string $token): Response
    {
        abort_unless(Gate::allows('viewAny', WasteEntry::class), 403);

        $export = Cache::get("waste-pdf:{$token}");
        abort_if($export === null || $export['user'] !== $request->user()->id, 404);

        $order = array_flip($export['ids']);
        $entries = WasteEntry::query()->whereKey($export['ids'])->with(['category.parent', 'processor'])->get()
            ->sortBy(fn (WasteEntry $entry): int => $order[$entry->id] ?? 0)
            ->values();

        $region = WasteRegion::tryFrom($export['list']);
        $title = $region ? __('erp.waste.batteries_region', ['region' => $region->getLabel()]) : __('erp.waste.registry');

        return Pdf::loadView('waste.list-pdf', [
            'entries' => $entries,
            'title' => $title,
            'region' => $region,
            'period' => WasteTotals::periodLabel($export['from'], $export['until']),
        ])
            ->setPaper('a4', $region ? 'portrait' : 'landscape')
            ->download(Str::slug($title).'-'.now()->format('Y-m-d').'.pdf');
    }

    public function totals(Request $request): Response
    {
        abort_unless(Gate::allows('viewAny', WasteEntry::class), 403);

        $period = $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
        ]);

        return Pdf::loadView('waste.totals-pdf', [
            'totals' => WasteTotals::between($period['from'] ?? null, $period['until'] ?? null),
            'period' => WasteTotals::periodLabel($period['from'] ?? null, $period['until'] ?? null),
        ])
            ->setPaper('a4')
            ->download('waste-totals-'.now()->format('Y-m-d').'.pdf');
    }
}
