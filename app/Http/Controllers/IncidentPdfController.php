<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF export of one incident, or of several (e.g. all reports of one employee) in one file.
 */
class IncidentPdfController
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Gate::allows('viewAny', Incident::class), 403);

        $ids = collect((array) $request->query('incidents'))->map(fn (mixed $id): int => (int) $id)->filter()->take(200);
        $incidents = Incident::query()->whereKey($ids)->with(['user', 'place', 'handler'])->orderBy('reporter_name')->orderBy('reported_at')->get();

        abort_if($incidents->isEmpty(), 404);

        $name = $incidents->count() === 1
            ? 'incident-'.$incidents->first()->id.'-'.$incidents->first()->reported_at->format('Y-m-d').'.pdf'
            : 'incidents-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('incidents.pdf', ['incidents' => $incidents])
            ->setPaper('a4')
            ->download($name);
    }
}
