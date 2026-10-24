<?php

namespace App\Http\Controllers;

use App\Actions\Batteries\BaeOrderDocument;
use App\Models\BaeOrder;
use App\Models\User;
use App\Support\PdfAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The BAE order as PDF: employees with "batteries.bae_orders" and "batteries.generate_pdf" only.
 * Exporting stamps the order as exported.
 */
class BaeOrderPdfController extends Controller
{
    public function __invoke(BaeOrder $order, BaeOrderDocument $document): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User && Gate::forUser($user)->allows('view', $order) && PdfAccess::canGenerate($user, 'batteries'), 403);

        $pdf = $document->render($order);
        $order->forceFill(['exported_at' => now()])->saveQuietly();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.BaeOrderDocument::filename($order).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
