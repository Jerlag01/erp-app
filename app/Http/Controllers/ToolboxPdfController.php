<?php

namespace App\Http\Controllers;

use App\Models\ToolboxMeeting;
use App\Policies\ToolboxMeetingPolicy;
use App\Support\PdfAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The attendance list of a toolbox meeting as PDF, like the paper form: company, month in Dutch and
 * French, who gave the instructions, the subject in both languages and every participant with their
 * signature. Needs "toolbox.view_signatures" and "toolbox.generate_pdf".
 */
class ToolboxPdfController
{
    public function __invoke(Request $request, ToolboxMeeting $meeting): Response
    {
        $user = $request->user();
        abort_unless(ToolboxMeetingPolicy::seesSignatures($user) && PdfAccess::canGenerate($user, 'toolbox'), 403);

        $rows = $meeting->participants()->get()->keyBy('user_id');
        $attendees = $meeting->attendees()->map(fn ($attendee): array => [
            'name' => $rows->get($attendee->id)?->signer_name ?: $attendee->name,
            'signature' => $rows->get($attendee->id)?->isSigned() ? $rows->get($attendee->id)->signatureDataUri() : null,
        ])->values();

        return Pdf::loadView('toolbox.pdf', [
            'meeting' => $meeting,
            // Two participants per row, as on the paper form.
            'rows' => $attendees->chunk(2)->map(fn ($pair) => $pair->values())->values(),
        ])
            ->setPaper('a4')
            ->stream(Str::slug('toolbox '.$meeting->meeting_month->format('Y m').' '.$meeting->title).'.pdf');
    }
}
