<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF export of one or more idea/complaint box entries, grouped by type.
 */
class SuggestionPdfController
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Gate::allows('viewAny', Suggestion::class), 403);

        $ids = collect((array) $request->query('suggestions'))->map(fn (mixed $id): int => (int) $id)->filter()->take(500);
        $suggestions = Suggestion::query()->whereKey($ids)->with(['user', 'handler'])->orderBy('type')->orderBy('submitted_at')->get();

        abort_if($suggestions->isEmpty(), 404);

        $name = $suggestions->count() === 1
            ? 'idea-box-'.$suggestions->first()->id.'-'.$suggestions->first()->submitted_at->format('Y-m-d').'.pdf'
            : 'idea-box-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('suggestions.pdf', ['suggestions' => $suggestions])
            ->setPaper('a4')
            ->download($name);
    }
}
