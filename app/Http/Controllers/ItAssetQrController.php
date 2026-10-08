<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ItAssets\ItAssetResource;
use App\Models\ItAsset;
use Illuminate\Http\RedirectResponse;

/**
 * The address in an IT asset's QR label: forwards to the asset page (via login when needed).
 */
class ItAssetQrController
{
    public function __invoke(ItAsset $asset): RedirectResponse
    {
        abort_unless(modules()->it(), 404);

        return redirect()->to(ItAssetResource::getUrl('view', ['record' => $asset], panel: 'app'));
    }
}
