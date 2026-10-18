<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ToolboxMeeting;
use App\Models\ToolboxMeetingParticipant;
use App\Models\User;
use App\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Toolbox meetings in the mobile app: the published meetings with the user's progress, reading one
 * (the text as a small web page, the attachments through short-lived links), and signing once
 * everything is read. Same rules and records as on the platform.
 */
class ToolboxController extends Controller
{
    /** Minutes a download link stays valid. */
    public const LINK_MINUTES = 10;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(Gate::forUser($user)->allows('viewAny', ToolboxMeeting::class), 403, __('erp.mobile.errors.module_off'));

        $meetings = ToolboxMeeting::query()->published()
            ->with(['participants' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByDesc('meeting_month')->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $meetings->map(function (ToolboxMeeting $meeting): array {
            $participant = $meeting->participants->first();

            return [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'month' => $meeting->monthLabel(app()->getLocale()),
                'subject' => app()->getLocale() === 'fr' ? $meeting->subject_fr : $meeting->subject_nl,
                'signed' => (bool) $participant?->isSigned(),
                'signed_at' => $participant?->signed_at?->toIso8601String(),
            ];
        })->values()]);
    }

    public function show(Request $request, ToolboxMeeting $meeting): JsonResponse
    {
        return response()->json(['data' => $this->payload($meeting, $this->participant($request, $meeting))]);
    }

    /**
     * The end of the text was scrolled into view.
     */
    public function read(Request $request, ToolboxMeeting $meeting): JsonResponse
    {
        $participant = $this->participant($request, $meeting);
        $participant->recordTextRead();

        return response()->json(['data' => $this->payload($meeting, $participant->fresh())]);
    }

    /**
     * A short-lived link to an attachment, to open in the phone's browser. Asking for it counts as
     * opening the attachment.
     */
    public function attachment(Request $request, ToolboxMeeting $meeting, int $index): JsonResponse
    {
        $participant = $this->participant($request, $meeting);
        $file = $meeting->attachmentList()[$index] ?? abort(404);
        $participant->recordDownload($file['path']);

        return response()->json([
            'url' => URL::temporarySignedRoute('toolbox.attachment', now()->addMinutes(self::LINK_MINUTES), ['meeting' => $meeting->id, 'index' => $index]),
            'data' => $this->payload($meeting, $participant->fresh()),
        ]);
    }

    public function sign(Request $request, ToolboxMeeting $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $participant = $this->participant($request, $meeting);
        abort_unless(Gate::forUser($user)->allows('sign', $meeting), 403);
        abort_if($participant->isSigned(), 409, __('erp.mobile.errors.already_signed'));

        if (! $participant->hasReadEverything()) {
            return response()->json(['message' => __('erp.toolbox.read_first'), 'code' => 'read_first'], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'width' => ['required', 'integer', 'min:50', 'max:1200'],
            'height' => ['required', 'integer', 'min:30', 'max:600'],
            'strokes' => ['required', 'array', 'min:1', 'max:300'],
            'strokes.*' => ['array', 'max:2000'],
            'strokes.*.*' => ['array', 'size:2'],
            'strokes.*.*.*' => ['numeric'],
        ]);

        try {
            $image = SignatureImage::fromStrokes($data['strokes'], (int) $data['width'], (int) $data['height']);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => __('erp.mobile.errors.signature'), 'errors' => ['strokes' => [__('erp.mobile.errors.signature')]]], 422);
        }

        $participant->sign($data['name'], $image);

        return response()->json(['message' => __('erp.toolbox.signed_notice'), 'data' => $this->payload($meeting, $participant->fresh())]);
    }

    protected function participant(Request $request, ToolboxMeeting $meeting): ToolboxMeetingParticipant
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($meeting->isPublished() && Gate::forUser($user)->allows('view', $meeting), 404);

        return $meeting->participantFor($user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(ToolboxMeeting $meeting, ToolboxMeetingParticipant $participant): array
    {
        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'month_nl' => $meeting->monthLabel('nl'),
            'month_fr' => $meeting->monthLabel('fr'),
            'location' => $meeting->location,
            'instructors' => $meeting->instructorNames(),
            'subject_nl' => $meeting->subject_nl,
            'subject_fr' => $meeting->subject_fr,
            'has_text' => $meeting->hasText(),
            // The text as a small web page (headings, pictures anywhere, columns, tables).
            'body_html' => $meeting->hasText() ? view('toolbox.mobile-body', ['meeting' => $meeting])->render() : null,
            'attachments' => array_map(fn (array $file): array => [
                'index' => $file['index'],
                'name' => $file['name'],
                'opened' => $participant->hasDownloaded($file['path']),
            ], $meeting->attachmentList()),
            'text_read' => $participant->text_read_at !== null,
            'can_sign' => ! $participant->isSigned() && $participant->hasReadEverything(),
            'signed' => $participant->isSigned(),
            'signer_name' => $participant->signer_name,
            'signed_at' => $participant->signed_at?->toIso8601String(),
            'signature' => $participant->isSigned() ? $participant->signatureDataUri() : null,
            'labels' => collect(['read_first', 'ready_to_sign', 'step_text', 'sign_help', 'location', 'instructors', 'month', 'subject'])
                ->mapWithKeys(fn (string $key): array => [$key => __("erp.toolbox.{$key}")]),
        ];
    }
}
