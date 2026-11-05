<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\It\ItDeliveryReports;
use App\Http\Controllers\Controller;
use App\Models\ItDeliveryReport;
use App\Models\SignedDocument;
use App\Models\User;
use App\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Delivery reports of IT assets in the mobile app: the reports the employee still has to sign and their
 * signed documents, each opened as PDF through a short-lived link, and signing with a finger ("read and
 * approved"). Same records as on the platform.
 */
class DeliveryReportController extends Controller
{
    /** Minutes a PDF link stays valid. */
    public const LINK_MINUTES = 10;

    public static function openCount(User $user): int
    {
        return ItDeliveryReport::query()->where('user_id', $user->id)->unsigned()->count();
    }

    /**
     * Whether the app shows the menu item: the user has (or had) a report or a signed document.
     */
    public static function available(User $user): bool
    {
        return ItDeliveryReport::query()->where('user_id', $user->id)->exists() || SignedDocument::query()->where('user_id', $user->id)->exists();
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            'to_sign' => ItDeliveryReport::query()->where('user_id', $user->id)->unsigned()->with('asset')->latest('id')->get()
                ->map(fn (ItDeliveryReport $report): array => [
                    'id' => $report->id,
                    'device' => $report->deviceLabel(),
                    'handed_over_on' => $report->handed_over_on->toDateString(),
                ])->values(),
            'signed' => SignedDocument::query()->where('user_id', $user->id)->latest('signed_at')->limit(100)->get()
                ->map(fn (SignedDocument $document): array => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'kind' => $document->kindLabel(),
                    'signed_at' => $document->signed_at->toIso8601String(),
                ])->values(),
            'labels' => collect(['page_title', 'to_sign', 'none_to_sign', 'view', 'sign', 'sign_help', 'approve', 'pending'])
                ->mapWithKeys(fn (string $key): array => [$key => __("erp.it.delivery_report.{$key}")])
                ->merge(['signed_documents' => __('erp.signed_documents.title'), 'no_signed_documents' => __('erp.signed_documents.none')]),
        ]]);
    }

    /**
     * A short-lived link to the report as PDF.
     */
    public function link(Request $request, ItDeliveryReport $report): JsonResponse
    {
        abort_unless($report->user_id === $request->user()->id, 404);

        return response()->json(['url' => URL::temporarySignedRoute('mobile.it-delivery-report', now()->addMinutes(self::LINK_MINUTES), ['report' => $report->id])]);
    }

    /**
     * A short-lived link to a signed document.
     */
    public function documentLink(Request $request, SignedDocument $document): JsonResponse
    {
        abort_unless($document->user_id === $request->user()->id, 404);

        return response()->json(['url' => URL::temporarySignedRoute('mobile.signed-document', now()->addMinutes(self::LINK_MINUTES), ['document' => $document->id])]);
    }

    public function sign(Request $request, ItDeliveryReport $report): JsonResponse
    {
        abort_unless($report->user_id === $request->user()->id, 404);
        abort_if($report->isSigned(), 409, __('erp.it.delivery_report.already_signed'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'approved' => ['accepted'],
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

        app(ItDeliveryReports::class)->sign($report, $data['name'], $image);

        return response()->json(['message' => __('erp.it.delivery_report.signed')]);
    }
}
