<?php

namespace App\Http\Controllers;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockItem;
use Illuminate\Http\RedirectResponse;

/**
 * The address in an item's QR code. It stays short and stable, and forwards to the item's page
 * on the employee side (via the login page when needed), where stock can be raised or lowered.
 */
class StockQrController
{
    public function __invoke(StockItem $item): RedirectResponse
    {
        abort_unless(modules()->stock(), 404);

        return redirect()->to(StockItemResource::getUrl('view', ['record' => $item], panel: 'app'));
    }
}
