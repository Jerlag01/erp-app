<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SubmitSuggestion;
use App\Enums\SuggestionType;
use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use App\Models\User;
use App\Support\PrivatePhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The idea/complaint box in the mobile app: an idea, complaint or other remark with photos, saved like
 * on the platform (name and time from the account), and the user's own earlier entries with the answer.
 */
class SuggestionController extends Controller
{
    public const MAX_PHOTOS = 20;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeSubmit($user);

        return response()->json(['data' => [
            'types' => array_map(fn (SuggestionType $type): array => ['value' => $type->value, 'label' => $type->getLabel()], SuggestionType::cases()),
            'max_photos' => self::MAX_PHOTOS,
            'labels' => collect(['title', 'intro', 'type', 'description', 'description_placeholder', 'may_be_public', 'may_be_public_help', 'submit', 'response'])
                ->mapWithKeys(fn (string $key): array => [$key => __("erp.suggestions.{$key}")]),
            'mine' => Suggestion::query()->where('user_id', $user->id)->with(['responses' => fn ($query) => $query->where('from_admin', true)])->latest('submitted_at')->limit(10)->get()->map(fn (Suggestion $suggestion): array => [
                'id' => $suggestion->id,
                'submitted_at' => $suggestion->submitted_at->toIso8601String(),
                'type' => $suggestion->type->getLabel(),
                'description' => $suggestion->description,
                'status' => $suggestion->status->getLabel(),
                'status_value' => $suggestion->status->value,
                // The latest answer of management (the replies are read and answered on the platform).
                'response' => $suggestion->responses->last()?->body ?? $suggestion->response,
            ])->values(),
        ]]);
    }

    public function store(Request $request, SubmitSuggestion $submit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeSubmit($user);

        $data = $request->validate([
            'type' => ['required', Rule::enum(SuggestionType::class)],
            'description' => ['required', 'string', 'max:10000'],
            'may_be_public' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimetypes:'.implode(',', PrivatePhotos::TYPES), 'max:'.PrivatePhotos::MAX_KILOBYTES],
        ]);

        $data['photos'] = array_map(fn ($photo): string => $photo->store('suggestions/'.now()->format('Y/m'), PrivatePhotos::DISK), $request->file('photos', []));
        $suggestion = $submit->handle($user, $data);

        return response()->json(['message' => __('erp.suggestions.thanks'), 'data' => ['id' => $suggestion->id]], 201);
    }

    protected function authorizeSubmit(User $user): void
    {
        abort_unless(Gate::forUser($user)->allows('create', Suggestion::class), 403, __('erp.mobile.errors.module_off'));
    }
}
