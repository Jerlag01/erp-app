<?php

namespace App\Http\Controllers;

use App\Models\ParentalLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The certificate of a parental leave: for administrators only.
 */
class ParentalLeaveDocumentController
{
    public function __invoke(Request $request, ParentalLeave $leave): Response
    {
        abort_unless($request->user()?->isAdmin() && filled($leave->document), 403);

        $disk = Storage::disk(ParentalLeave::DISK);
        abort_unless($disk->exists($leave->document), 404);

        return $disk->response($leave->document, $leave->document_name ?? basename($leave->document), ['X-Content-Type-Options' => 'nosniff']);
    }
}
