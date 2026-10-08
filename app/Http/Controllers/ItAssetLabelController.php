<?php

namespace App\Http\Controllers;

use App\Models\ItAsset;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable asset tags (A4, 3 × 7 labels) with a QR code, for the assets the user may see.
 */
class ItAssetLabelController
{
    public function __invoke(Request $request): View
    {
        abort_unless(modules()->it() && $request->user()?->can('viewAny', ItAsset::class), 403);

        $ids = collect((array) $request->query('assets'))->map(fn (mixed $id): int => (int) $id)->filter()->take(210);

        return view('it.labels', [
            'assets' => ItAsset::query()->visibleTo($request->user())->whereKey($ids)->with('model.manufacturer')->orderBy('asset_tag')->get(),
        ]);
    }
}
