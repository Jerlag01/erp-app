<?php

namespace App\Http\Controllers;

use App\Models\BatteryDelivery;
use App\Support\PdfAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The delivery overview as PDF, with the columns of the Excel print area (location up to the actual
 * delivery date), for the tab that is open.
 */
class BatteryDeliveriesPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Gate::allows('viewAny', BatteryDelivery::class) && PdfAccess::canGenerate($request->user(), 'batteries'), 403);

        $tab = (string) $request->query('tab', 'to_deliver');
        $deliveries = BatteryDelivery::query()
            ->visibleTo($request->user())
            ->with(['project.workSite', 'project.category', 'batteryModel'])
            ->when($tab === 'to_deliver', fn (Builder $query) => $query->where('delivered', false)->where('executed', false))
            ->when($tab === 'delivered', fn (Builder $query) => $query->where('delivered', true)->where('executed', false))
            ->when($tab === 'executed', fn (Builder $query) => $query->where('executed', true))
            ->get()
            ->sortBy(fn (BatteryDelivery $delivery): int => (int) $delivery->project?->number)
            ->values();

        $pdf = Pdf::loadView('batteries.pdf.deliveries', ['deliveries' => $deliveries])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="overzicht-levering-batterijen.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
