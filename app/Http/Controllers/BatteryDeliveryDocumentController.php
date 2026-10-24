<?php

namespace App\Http\Controllers;

use App\Models\BatteryDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A document of a battery delivery (order, confirmation, delivery note), only with
 * "batteries.documents" on a project the user sees.
 */
class BatteryDeliveryDocumentController
{
    public function __invoke(BatteryDelivery $delivery, string $kind): StreamedResponse
    {
        // An employee's login only: client portal accounts never get these documents.
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User && Gate::forUser($user)->allows('viewDocuments', $delivery), 403);

        $path = $delivery->documentPath($kind);
        abort_unless(filled($path) && Storage::disk(BatteryDelivery::DISK)->exists($path), 404);

        return Storage::disk(BatteryDelivery::DISK)->download($path, $delivery->documentName($kind), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
