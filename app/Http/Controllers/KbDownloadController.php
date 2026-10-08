<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Knowledge base downloads, for signed-in employees who may read the article.
 */
class KbDownloadController
{
    public function __invoke(Request $request, KbArticle $article, int $index): StreamedResponse
    {
        abort_unless(modules()->knowledgeBase() && $request->user()->is_active && $article->isReadableBy($request->user()), 404);

        $path = array_values((array) $article->attachments)[$index] ?? null;
        abort_if($path === null || ! Storage::disk(KbArticle::FILE_DISK)->exists($path), 404);

        return Storage::disk(KbArticle::FILE_DISK)->download($path, $article->attachment_names[$path] ?? basename($path), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
