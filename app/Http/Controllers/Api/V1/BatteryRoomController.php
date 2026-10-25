<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BatteryRoomDossier;
use App\Models\User;
use App\Support\PrivatePhotos;
use App\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Battery rooms (609) in the mobile app, as the dossier page on the platform: the delivery note
 * (quantities, remarks) with its written signature, extra info, the checklist (OK/NOK and the action
 * per point) and photos. The PO number, ventilation calculation and BTV report stay on the platform. Labels and the
 * layout come from the ERP, so the app shows the same points and lines.
 */
class BatteryRoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('viewAny', BatteryRoomDossier::class), 403, __('erp.mobile.errors.no_access'));

        $rooms = BatteryRoomDossier::query()
            ->visibleTo($request->user())
            ->with(['project.workSite', 'project.category'])
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rooms->map(fn (BatteryRoomDossier $room): array => [
            'id' => $room->id,
            'reference' => $room->reference(),
            'cow_code' => $room->cowCode(),
            'room_number' => $room->room_number,
            'city' => $room->project?->workSite?->city,
            'status' => $room->project?->status?->getLabel(),
            'closed' => (bool) $room->project?->isArchived(),
            'checklist' => implode('/', $room->checklistProgress()),
            'btv' => filled($room->btv_file),
        ])->values()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->find($request->user(), $id), $request->user())]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $room = $this->find($user, $id);
        $this->authorizeEdit($user, $room);

        $rules = [
            'room_number' => ['nullable', 'string', 'max:50'],
            'note_date' => ['nullable', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'extra_info' => ['nullable', 'string', 'max:10000'],
            'checklist_date' => ['nullable', 'date_format:Y-m-d'],
            'quantities' => ['array'],
            'checklist' => ['array'],
        ];

        foreach (BatteryRoomDossier::SECTIONS as $section => $lines) {
            foreach (array_keys($lines) as $line) {
                $rules["quantities.{$section}.{$line}"] = ['nullable', 'numeric', 'min:0', 'max:100000'];
            }
        }

        foreach (array_keys(BatteryRoomDossier::CHECKLIST) as $point) {
            $rules["checklist.{$point}.ok"] = ['boolean'];
            $rules["checklist.{$point}.action"] = ['nullable', 'string', 'max:2000'];
        }

        $data = $request->validate($rules);

        // Only what was sent changes; unknown sections and points are left out.
        $quantities = (array) $room->quantities;

        foreach ((array) ($data['quantities'] ?? []) as $section => $lines) {
            foreach ((array) $lines as $line => $value) {
                if (isset(BatteryRoomDossier::SECTIONS[$section][$line])) {
                    $quantities[$section][$line] = $value === null || $value === '' ? null : (float) $value;
                }
            }
        }

        $checklist = (array) $room->checklist;

        foreach ((array) ($data['checklist'] ?? []) as $point => $answer) {
            if (array_key_exists($point, BatteryRoomDossier::CHECKLIST)) {
                $checklist[$point] = ['ok' => (bool) ($answer['ok'] ?? false), 'action' => filled($answer['action'] ?? null) ? trim((string) $answer['action']) : null];
            }
        }

        $values = [
            ...array_intersect_key($data, array_flip(['room_number', 'note_date', 'remarks', 'extra_info', 'checklist_date'])),
            'quantities' => $quantities,
            'checklist' => $checklist,
        ];

        // A signed delivery note stays as it was signed (except for administrators).
        if ($room->isLockedFor($user)) {
            $values = array_diff_key($values, array_flip(BatteryRoomDossier::NOTE_FIELDS));
        }

        $room->update($values);

        return response()->json(['message' => __('erp.mobile.saved'), 'data' => $this->payload($room->fresh(), $user)]);
    }

    public function addPhoto(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $room = $this->find($user, $id);
        $this->authorizeEdit($user, $room);
        $request->validate(['photo' => ['required', 'file', 'mimetypes:'.implode(',', PrivatePhotos::TYPES), 'max:'.PrivatePhotos::MAX_KILOBYTES]]);

        $photos = array_values((array) $room->photos);
        abort_if(count($photos) >= BatteryRoomDossier::MAX_PHOTOS, 422, __('erp.mobile.errors.too_many_photos', ['max' => BatteryRoomDossier::MAX_PHOTOS]));

        $photos[] = $request->file('photo')->store('battery-rooms/'.$room->id.'/photos', BatteryRoomDossier::DISK);
        $room->update(['photos' => $photos]);

        return response()->json(['data' => $this->payload($room->fresh(), $user)], 201);
    }

    public function removePhoto(Request $request, int $id, int $index): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $room = $this->find($user, $id);
        $this->authorizeEdit($user, $room);

        $photos = array_values((array) $room->photos);
        abort_unless(isset($photos[$index]), 404);
        unset($photos[$index]);
        // The model removes the file from the disk.
        $room->update(['photos' => array_values($photos)]);

        return response()->json(['data' => $this->payload($room->fresh(), $user)]);
    }

    public function photo(Request $request, int $id, int $index): StreamedResponse
    {
        $room = $this->find($request->user(), $id);
        $path = array_values((array) $room->photos)[$index] ?? null;
        abort_unless(is_string($path) && Storage::disk(BatteryRoomDossier::DISK)->exists($path), 404);

        return Storage::disk(BatteryRoomDossier::DISK)->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * The written signature on the delivery note (as on the 604 documents), which locks it.
     */
    public function sign(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $room = $this->find($user, $id);
        $this->authorizeEdit($user, $room);
        abort_if($room->isSigned(), 409, __('erp.mobile.errors.already_signed'));

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

        $room->sign($data['name'], $image, $user);

        return response()->json(['message' => __('erp.batteries.signature.signed'), 'data' => $this->payload($room->fresh(), $user)]);
    }

    protected function find(User $user, int $id): BatteryRoomDossier
    {
        abort_unless(Gate::forUser($user)->allows('viewAny', BatteryRoomDossier::class), 403, __('erp.mobile.errors.no_access'));
        $room = BatteryRoomDossier::query()->with(['project.workSite.area', 'project.category', 'project.poNumbers'])->findOrFail($id);
        abort_unless(Gate::forUser($user)->allows('view', $room), 403);

        return $room;
    }

    protected function authorizeEdit(User $user, BatteryRoomDossier $room): void
    {
        abort_unless($this->canEdit($user, $room), 403, __('erp.mobile.errors.dossier_locked'));
    }

    /**
     * Closed (archived) projects stay as they are.
     */
    protected function canEdit(User $user, BatteryRoomDossier $room): bool
    {
        return Gate::forUser($user)->allows('update', $room) && ! $room->project?->isArchived();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(BatteryRoomDossier $room, User $user): array
    {
        $site = $room->project?->workSite;

        return [
            'id' => $room->id,
            'reference' => $room->reference(),
            'can_edit' => $this->canEdit($user, $room),
            'closed' => (bool) $room->project?->isArchived(),
            'overview' => array_values(array_filter([
                ['label' => __('battery_rooms.fields.cow_code'), 'value' => $room->cowCode()],
                ['label' => __('erp.batteries.overview.address'), 'value' => $site?->addressLine()],
                ['label' => __('erp.fields.status'), 'value' => $room->project?->status?->getLabel()],
                ['label' => __('battery_rooms.fields.client_reference'), 'value' => $room->clientReference() ?? __('battery_rooms.doc.pending', [], 'nl')],
            ], fn (array $row): bool => filled($row['value']))),
            'maps_url' => $site?->googleMapsUrl(),
            'values' => [
                'room_number' => $room->room_number,
                'note_date' => $room->note_date?->toDateString(),
                'remarks' => $room->remarks,
                'extra_info' => $room->extra_info,
                'checklist_date' => $room->checklist_date?->toDateString(),
            ],
            'sections' => array_map(fn (string $section): array => [
                'key' => $section,
                'title' => __("battery_rooms.sections.{$section}.title"),
                'text' => BatteryRoomDossier::sectionText($section),
                'lines' => array_map(fn (string $line, string $unit): array => [
                    'key' => $line,
                    'label' => __("battery_rooms.sections.{$section}.lines.{$line}"),
                    'unit' => __("battery_rooms.units.{$unit}"),
                    'value' => isset(((array) $room->quantities)[$section][$line]) ? $room->quantity($section, $line) : null,
                ], array_keys(BatteryRoomDossier::SECTIONS[$section]), BatteryRoomDossier::SECTIONS[$section]),
            ], array_keys(BatteryRoomDossier::SECTIONS)),
            'checklist' => array_map(fn (string $point): array => [
                'key' => $point,
                'subject' => __("battery_rooms.checklist.{$point}.subject"),
                'description' => __("battery_rooms.checklist.{$point}.description"),
                'ok' => $room->isOk($point),
                'action' => $room->action($point),
            ], array_keys(BatteryRoomDossier::CHECKLIST)),
            'checklist_progress' => implode('/', $room->checklistProgress()),
            // The version in the path changes with the file, so the phone never shows a cached removed photo.
            'photos' => array_map(fn (int $index, string $path): array => [
                'index' => $index,
                'path' => "/battery-rooms/{$room->id}/photos/{$index}?v=".substr(md5($path), 0, 10),
            ], array_keys($photos = array_values((array) $room->photos)), $photos),
            'max_photos' => BatteryRoomDossier::MAX_PHOTOS,
            'signature' => [
                'document' => __('battery_rooms.tabs.note'),
                'signed' => $room->isSigned(),
                'locked' => $room->isLockedFor($user),
                'can_sign' => $this->canEdit($user, $room) && ! $room->isSigned(),
                'signer_name' => $room->signer_name,
                'signed_note' => $room->isSigned() ? __('erp.batteries.signature.signed_at', ['date' => $room->signed_at->format('d/m/Y H:i'), 'user' => $room->signer?->name ?? '—']) : null,
                'image' => $room->signatureDataUri(),
            ],
        ];
    }
}
