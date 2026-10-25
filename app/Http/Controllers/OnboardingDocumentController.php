<?php

namespace App\Http\Controllers;

use App\Models\OnboardingDocument;
use App\Models\User;
use App\Support\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opens an onboarding document: the user's own (active, in their language) is marked as opened, so
 * they can sign it; those who manage onboarding open any document.
 */
class OnboardingDocumentController
{
    public function __invoke(Request $request, OnboardingDocument $document, Onboarding $onboarding): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $onboarding->enabled(), 403);

        $own = $onboarding->documentsFor($user)->contains('id', $document->id);
        abort_unless($own || $user->hasPermission('onboarding.manage'), 403);

        $disk = Storage::disk(OnboardingDocument::DISK);
        abort_unless($disk->exists($document->path), 404);

        if ($own) {
            $onboarding->open($user, $document);
        }

        return $disk->response($document->path, $document->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
