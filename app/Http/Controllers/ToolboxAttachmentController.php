<?php

namespace App\Http\Controllers;

use App\Models\ToolboxMeeting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An attachment of a toolbox meeting through a short-lived signed link, made by the mobile app for the
 * signed-in employee (the phone's browser has no session).
 */
class ToolboxAttachmentController
{
    public function __invoke(ToolboxMeeting $meeting, int $index): StreamedResponse
    {
        abort_unless($meeting->isPublished(), 404);

        $file = $meeting->attachmentList()[$index] ?? abort(404);
        $disk = Storage::disk(ToolboxMeeting::FILE_DISK);
        abort_unless($disk->exists($file['path']), 404);

        return $disk->download($file['path'], $file['name'], ['Cache-Control' => 'private, no-store']);
    }
}
