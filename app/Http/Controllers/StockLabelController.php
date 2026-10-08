<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A printable sheet of QR labels (A4, 3 × 7 labels of 63.5 × 38.1 mm) for one or more items.
 */
class StockLabelController
{
    public function __invoke(Request $request): View
    {
        abort_unless(modules()->stock() && $request->user()?->can('viewAny', StockItem::class), 403);

        $ids = collect((array) $request->query('items'))->map(fn (mixed $id): int => (int) $id)->filter()->take(210);

        return view('stock.labels', [
            'items' => StockItem::query()->whereKey($ids)->with(['unit', 'manufacturer'])->orderBy('name')->get(),
        ]);
    }
}
