<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Batteries\SaveBatteryDossier;
use App\Actions\Batteries\SaveFloorPlan;
use App\Http\Controllers\Controller;
use App\Models\BatteryCable;
use App\Models\BatteryDossier;
use App\Models\BatteryDossierBattery;
use App\Models\EnersysDossier;
use App\Models\User;
use App\Support\PrivatePhotos;
use App\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Battery (604, "bae") and Enersys (605, "enersys") dossiers in the mobile app: what the technician
 * fills in on site (delivery note, measurement report, visual inspection, single-line values, shipping
 * note), the written signatures, the photos, the floor plan (battery dossiers, drawn in the platform's
 * editor shown in the app) and finishing. The Input tab and prices stay on the platform. The form is described by the ERP (sections, fields, labels, locks), so the app
 * shows the same fields with the same rules as the dossier page.
 */
class BatteryDossierController extends Controller
{
    /** @var array<string, class-string<BatteryDossier>> */
    public const KINDS = ['bae' => BatteryDossier::class, 'enersys' => EnersysDossier::class];

    /** Units of the number fields. */
    protected const SUFFIXES = [
        'vertical_install_m' => 'm', 'vertical_removal_m' => 'm', 'powerflex_length_m' => 'm', 'tray_length_m' => 'm', 'cable_length_m' => 'm',
        'man_hours' => 'u', 'weight_kg' => 'kg', 'average_temperature' => '°C', 'powerflex_section' => 'mm²', 'lug_section' => 'mm²',
        'cable_section' => 'mm²', 'fuse_amperage' => 'A',
    ];

    /** Text fields (the others are numbers, dates or a choice). */
    protected const TEXT_FIELDS = ['powerflex_section', 'lug_section', 'tray_type', 'fuse_amperage', 'fuse_type'];

    public function index(Request $request, string $kind): JsonResponse
    {
        $class = $this->authorizeKind($request->user(), $kind);

        $dossiers = $class::query()
            ->visibleTo($request->user())
            ->with(['project.workSite', 'signatures'])
            ->orderByRaw('finished_at is not null')
            ->orderByDesc('execution_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $dossiers->map(fn (BatteryDossier $dossier): array => [
            'id' => $dossier->id,
            'reference' => $dossier->reference(),
            'description' => $dossier->description(),
            'cow_code' => $dossier->cowCode(),
            'city' => $dossier->project?->workSite?->city,
            'execution_date' => $dossier->execution_date?->toDateString(),
            'finished' => $dossier->isFinished(),
            'signed' => collect(array_keys($dossier::SIGNED_FIELDS))->mapWithKeys(fn (string $document): array => [$document => $dossier->isSigned($document)]),
        ])->values()]);
    }

    public function show(Request $request, string $kind, int $id): JsonResponse
    {
        $dossier = $this->find($request->user(), $kind, $id);

        return response()->json(['data' => $this->payload($dossier, $request->user())]);
    }

    public function update(Request $request, string $kind, int $id, SaveBatteryDossier $save): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dossier = $this->find($user, $kind, $id);
        $this->authorizeEdit($user, $dossier);

        $data = $request->validate([
            'values' => ['array'],
            ...$this->valueRules($dossier),
            'batteries' => ['array', 'max:'.BatteryDossier::MAX_BATTERIES],
            'batteries.*' => ['array'],
            'batteries.*.cell_voltages' => ['array', 'max:60'],
            'batteries.*.cell_voltages.*' => ['nullable', 'numeric'],
            'batteries.*.cell_densities' => ['array', 'max:60'],
            'batteries.*.cell_densities.*' => ['nullable', 'numeric'],
            'batteries.*.floating_voltages' => ['array', 'max:60'],
            'batteries.*.floating_voltages.*' => ['nullable', 'numeric'],
            'batteries.*.float_voltage' => ['nullable', 'numeric'],
            'batteries.*.cable_type' => ['nullable', 'string', 'max:30'],
            'batteries.*.cable_length_m' => ['nullable', 'numeric', 'min:0'],
            'batteries.*.cables_positive' => ['nullable', 'integer', 'min:0', 'max:20'],
            'batteries.*.cables_negative' => ['nullable', 'integer', 'min:0', 'max:20'],
            'batteries.*.cable_section' => ['nullable', 'string', 'max:20'],
            'batteries.*.fuse_amperage' => ['nullable', 'string', 'max:20'],
            'batteries.*.fuse_type' => ['nullable', 'string', 'max:30'],
        ]);

        $values = (array) ($data['values'] ?? []);

        if (array_key_exists('inspection', $values)) {
            // Keep the answers to questions the app does not show (other dossier type, older questions).
            $values['inspection'] = array_filter([...(array) $dossier->inspection, ...(array) $values['inspection']], fn (mixed $answer): bool => $answer !== null);
        }

        $save->handle($dossier, $values, (array) ($data['batteries'] ?? []), $user);

        return response()->json(['message' => __('erp.mobile.saved'), 'data' => $this->payload($this->find($user, $kind, $id), $user)]);
    }

    public function sign(Request $request, string $kind, int $id, string $document): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dossier = $this->find($user, $kind, $id);
        $this->authorizeEdit($user, $dossier);
        abort_unless(array_key_exists($document, $dossier::SIGNED_FIELDS), 404);
        abort_if($dossier->isSigned($document), 409, __('erp.mobile.errors.already_signed'));

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

        $dossier->sign($document, $data['name'], $image, $user);

        return response()->json(['message' => __('erp.batteries.signature.signed'), 'data' => $this->payload($this->find($user, $kind, $id), $user)]);
    }

    public function addPhoto(Request $request, string $kind, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dossier = $this->find($user, $kind, $id);
        $this->authorizeEdit($user, $dossier);
        $request->validate(['photo' => ['required', 'file', 'mimetypes:'.implode(',', PrivatePhotos::TYPES), 'max:'.PrivatePhotos::MAX_KILOBYTES]]);

        $photos = array_values((array) $dossier->photos);
        abort_if(count($photos) >= BatteryDossier::MAX_PHOTOS, 422, __('erp.mobile.errors.too_many_photos', ['max' => BatteryDossier::MAX_PHOTOS]));

        $photos[] = $request->file('photo')->store('batteries/'.$dossier->id.'/photos', PrivatePhotos::DISK);
        $dossier->update(['photos' => $photos]);

        return response()->json(['data' => $this->payload($dossier->fresh(), $user)], 201);
    }

    public function removePhoto(Request $request, string $kind, int $id, int $index): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dossier = $this->find($user, $kind, $id);
        $this->authorizeEdit($user, $dossier);

        $photos = array_values((array) $dossier->photos);
        abort_unless(isset($photos[$index]), 404);
        $path = $photos[$index];
        unset($photos[$index]);
        $dossier->update(['photos' => array_values($photos)]);
        Storage::disk(PrivatePhotos::DISK)->delete($path);

        return response()->json(['data' => $this->payload($dossier->fresh(), $user)]);
    }

    public function photo(Request $request, string $kind, int $id, int $index): StreamedResponse
    {
        $dossier = $this->find($request->user(), $kind, $id);
        $path = array_values((array) $dossier->photos)[$index] ?? null;
        abort_unless(is_string($path) && Storage::disk(PrivatePhotos::DISK)->exists($path), 404);

        return Storage::disk(PrivatePhotos::DISK)->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Saves the floor plan drawn in the editor page: the shapes and the PNG the editor made of them.
     */
    public function saveFloorPlan(Request $request, string $kind, int $id, SaveFloorPlan $save): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dossier = $this->find($user, $kind, $id);
        abort_if($this->isEnersys($dossier), 404);
        $this->authorizeEdit($user, $dossier);

        $data = $request->validate([
            'shapes' => ['present', 'array', 'max:2000'],
            'png' => ['nullable', 'string', 'max:'.SaveFloorPlan::MAX_PNG_BYTES],
        ]);

        $save->handle($dossier, $data['shapes'], $data['png'] ?? null);

        return response()->json(['message' => __('erp.mobile.saved'), 'data' => $this->payload($this->find($user, $kind, $id), $user)]);
    }

    /**
     * The floor plan as image, for the preview in the app.
     */
    public function floorPlanImage(Request $request, string $kind, int $id): StreamedResponse
    {
        $dossier = $this->find($request->user(), $kind, $id);
        abort_unless(filled($dossier->floor_plan_png) && Storage::disk(BatteryDossier::DISK)->exists($dossier->floor_plan_png), 404);

        return Storage::disk(BatteryDossier::DISK)->response($dossier->floor_plan_png, 'floor-plan.png', ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * The technician finishes the works: photos are required, the dossier is locked and the project
     * goes to "invoicing to do".
     */
    public function finish(Request $request, string $kind, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $dossier = $this->find($user, $kind, $id);
        $this->authorizeEdit($user, $dossier);
        abort_if($dossier->isFinished(), 409, __('erp.batteries.finish.finished'));

        if (count((array) $dossier->photos) === 0) {
            return response()->json(['message' => __('erp.batteries.finish.photos_required_help'), 'code' => 'photos_required'], 422);
        }

        $dossier->finish($user);

        return response()->json(['message' => __('erp.batteries.finish.done_help'), 'data' => $this->payload($dossier->fresh(), $user)]);
    }

    /**
     * @return class-string<BatteryDossier>
     */
    protected function authorizeKind(User $user, string $kind): string
    {
        $class = self::KINDS[$kind] ?? abort(404);
        abort_unless(Gate::forUser($user)->allows('viewAny', $class), 403, __('erp.mobile.errors.no_access'));

        return $class;
    }

    protected function find(User $user, string $kind, int $id): BatteryDossier
    {
        $class = $this->authorizeKind($user, $kind);
        $dossier = $class::query()->with(['project.workSite', 'signatures.signer', 'batteries', 'batteryModel.racks', 'finisher'])->findOrFail($id);
        abort_unless(Gate::forUser($user)->allows('view', $dossier), 403);

        return $dossier;
    }

    protected function authorizeEdit(User $user, BatteryDossier $dossier): void
    {
        abort_unless(Gate::forUser($user)->allows('update', $dossier) && $dossier->isEditableBy($user), 403, __('erp.mobile.errors.dossier_locked'));
    }

    protected function isEnersys(BatteryDossier $dossier): bool
    {
        return $dossier instanceof EnersysDossier;
    }

    /**
     * The fields of the documents the app fills in, without prices (those stay on the platform).
     *
     * @return list<string>
     */
    protected function deliveryFields(BatteryDossier $dossier): array
    {
        return array_values(array_filter($dossier::SIGNED_FIELDS['delivery'], fn (string $field): bool => ! str_ends_with($field, '_price')));
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function valueRules(BatteryDossier $dossier): array
    {
        $rules = [];

        foreach ([...$this->deliveryFields($dossier), 'acid_boxes', 'empty_boxes_returned', 'packages', 'weight_kg'] as $field) {
            $rules["values.{$field}"] = match (true) {
                in_array($field, self::TEXT_FIELDS, true) => ['nullable', 'string', 'max:50'],
                $field === 'battery_cable_id' => ['nullable', 'integer', Rule::exists('battery_cables', 'id')],
                default => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            };
        }

        return [
            ...$rules,
            'values.measured_on' => ['nullable', 'date_format:Y-m-d'],
            'values.average_temperature' => ['nullable', 'numeric', 'min:-50', 'max:100'],
            'values.inspection' => ['nullable', 'array'],
            'values.inspection.*' => ['nullable', Rule::in(['yes', 'no'])],
            'values.inspection_remarks' => ['nullable', 'string', 'max:5000'],
            'values.photos_remark' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function field(string $key, string $type = 'number', ?string $label = null): array
    {
        return [
            'key' => $key,
            'type' => in_array($key, self::TEXT_FIELDS, true) ? 'text' : $type,
            'label' => $label ?? __("erp.batteries.fields.{$key}"),
            'suffix' => self::SUFFIXES[$key] ?? (in_array($type, ['number'], true) ? 'st' : null),
        ];
    }

    /**
     * Everything the app shows: the job, the documents with their fields and values, the batteries,
     * signatures, photos and what the user may still do.
     *
     * @return array<string, mixed>
     */
    protected function payload(BatteryDossier $dossier, User $user): array
    {
        $enersys = $this->isEnersys($dossier);
        $canEdit = Gate::forUser($user)->allows('update', $dossier) && $dossier->isEditableBy($user);
        $cells = max(1, min(60, (int) $dossier->cells_per_battery));
        $delivery = array_map(fn (string $key): array => match ($key) {
            'battery_cable_id' => [...$this->field($key, 'select'), 'suffix' => null, 'options' => BatteryCable::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn (BatteryCable $cable): array => ['value' => $cable->id, 'label' => $cable->name])->all()],
            'leak_trays' => $this->field($key, 'number', __('erp.enersys.leak_trays')),
            default => $this->field($key),
        }, $this->deliveryFields($dossier));

        $sections = [
            ['key' => 'delivery', 'title' => __('erp.batteries.tabs.delivery'), 'document' => 'delivery', 'fields' => $delivery],
            ['key' => 'measurement', 'title' => __('erp.batteries.tabs.measurement'), 'document' => 'measurement', 'fields' => [
                $this->field('measured_on', 'date', __('erp.batteries.measured_on')),
                [...$this->field('average_temperature', 'decimal', __('erp.batteries.average_temperature')), 'suffix' => '°C'],
            ]],
            ['key' => 'inspection', 'title' => __('erp.batteries.tabs.inspection'), 'document' => 'inspection', 'fields' => [
                $this->field('inspection_remarks', 'textarea', __('erp.batteries.inspection_remarks')),
            ], 'groups' => collect($dossier->inspectionSections())->map(fn (array $questions, string $title): array => [
                'title' => $title,
                'questions' => collect($questions)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all(),
            ])->values()->all()],
            ...($enersys ? [] : [['key' => 'single_line', 'title' => __('erp.batteries.tabs.single_line'), 'document' => null, 'fields' => []]]),
            ['key' => 'shipping', 'title' => __('erp.batteries.tabs.shipping'), 'document' => null, 'fields' => [
                $this->field('acid_boxes'), $this->field('empty_boxes_returned'), $this->field('packages'), [...$this->field('weight_kg', 'decimal'), 'suffix' => 'kg'],
            ]],
        ];

        $values = [];

        foreach ([...$this->deliveryFields($dossier), 'acid_boxes', 'empty_boxes_returned', 'packages', 'weight_kg', 'average_temperature', 'inspection_remarks', 'photos_remark'] as $key) {
            $value = $dossier->getAttribute($key);
            $values[$key] = is_numeric($value) && ! in_array($key, self::TEXT_FIELDS, true) ? (float) $value : $value;
        }

        $values['measured_on'] = $dossier->measured_on?->toDateString();
        $values['inspection'] = (object) array_filter((array) $dossier->inspection, fn (mixed $answer): bool => in_array($answer, ['yes', 'no'], true));

        $series = fn (mixed $values): array => array_map(fn (int $index): ?float => is_numeric(((array) $values)[$index] ?? null) ? (float) ((array) $values)[$index] : null, range(0, $cells - 1));
        $technology = $dossier->new_technology;

        return [
            'id' => $dossier->id,
            'kind' => $enersys ? 'enersys' : 'bae',
            'reference' => $dossier->reference(),
            'description' => $dossier->description(),
            'finished' => $dossier->isFinished(),
            'finished_note' => $dossier->isFinished() ? __('erp.batteries.finish.finished_on', ['date' => $dossier->finished_at->format('d/m/Y H:i'), 'user' => $dossier->finisher?->name ?? '—']) : null,
            'can_edit' => $canEdit,
            'overview' => array_values(array_filter([
                ['label' => __('erp.batteries.overview.cow_code'), 'value' => $dossier->cowCode()],
                ['label' => __('erp.batteries.overview.address'), 'value' => implode(', ', $dossier->senderAddressLines()) ?: null],
                ['label' => __('erp.batteries.contact_person'), 'value' => $dossier->contact_person],
                ['label' => __('erp.batteries.execution_date'), 'value' => $dossier->execution_date?->format('d/m/Y')],
                ['label' => __('erp.batteries.overview.to_remove'), 'value' => $dossier->removal ? trim(($dossier->old_count ?: '').' × '.($dossier->old_capacity_ah ? $dossier->old_capacity_ah.' Ah' : '')) : __('erp.batteries.overview.no_removal')],
                ['label' => __('erp.batteries.overview.to_install'), 'value' => trim($dossier->new_count.' × '.trim(implode(' ', array_filter([$dossier->brand, $dossier->typeName()]))))],
                ['label' => __('erp.batteries.overview.rack'), 'value' => trim(implode(' · ', array_filter([$dossier->mounting?->getLabel(), $dossier->rack()?->code])))],
            ], fn (array $row): bool => filled($row['value']))),
            'maps_url' => $dossier->project?->workSite?->googleMapsUrl(),
            'sections' => $sections,
            'values' => $values,
            'cells' => $cells,
            'density' => ! $enersys && (bool) $technology?->hasDensity(),
            'batteries' => $dossier->batteries->map(fn (BatteryDossierBattery $battery): array => [
                'position' => $battery->position,
                'label' => $battery->label(),
                'id_number' => $battery->id_number,
                'cell_voltages' => $series($battery->cell_voltages),
                'cell_densities' => $series($battery->cell_densities),
                'float_voltage' => $battery->float_voltage !== null ? (float) $battery->float_voltage : null,
                'floating_voltages' => $series($battery->floating_voltages),
                'cable_type' => $battery->cable_type,
                'cable_length_m' => $battery->cable_length_m !== null ? (float) $battery->cable_length_m : null,
                'cables_positive' => $battery->cables_positive,
                'cables_negative' => $battery->cables_negative,
                'cable_section' => $battery->cable_section,
                'fuse_amperage' => $battery->fuse_amperage,
                'fuse_type' => $battery->fuse_type,
            ])->values(),
            'single_line_fields' => $enersys ? [] : [
                $this->field('cable_type', 'text'), [...$this->field('cable_length_m', 'decimal'), 'suffix' => 'm'],
                [...$this->field('cables_positive'), 'suffix' => null], [...$this->field('cables_negative'), 'suffix' => null],
                [...$this->field('cable_section', 'text'), 'type' => 'text'], [...$this->field('fuse_amperage', 'text'), 'type' => 'text'], $this->field('fuse_type', 'text'),
            ],
            'signatures' => collect(array_keys($dossier::SIGNED_FIELDS))->mapWithKeys(function (string $document) use ($dossier, $user, $canEdit): array {
                $signature = $dossier->signature($document);

                return [$document => [
                    'document' => __('erp.batteries.documents.'.$document),
                    'signed' => $signature !== null,
                    'locked' => $dossier->isLockedFor($document, $user),
                    'can_sign' => $canEdit && $signature === null,
                    'signer_name' => $signature?->signer_name,
                    'signed_note' => $signature ? __('erp.batteries.signature.signed_at', ['date' => $signature->signed_at->format('d/m/Y H:i'), 'user' => $signature->signer?->name ?? '—']) : null,
                    'image' => $signature?->dataUri(),
                ]];
            }),
            // The version in the path changes with the file, so the phone never shows a cached removed photo.
            'photos' => array_map(fn (int $index, string $path): array => [
                'index' => $index,
                'path' => '/dossiers/'.($enersys ? 'enersys' : 'bae')."/{$dossier->id}/photos/{$index}?v=".substr(md5($path), 0, 10),
            ], array_keys($photos = array_values((array) $dossier->photos)), $photos),
            'max_photos' => BatteryDossier::MAX_PHOTOS,
            // Battery dossiers only: the platform's editor, opened in the app through a short-lived signed link.
            'floor_plan' => $enersys ? null : [
                'has_drawing' => filled($dossier->floor_plan),
                'image_path' => filled($dossier->floor_plan_png) ? "/dossiers/bae/{$dossier->id}/floor-plan?v=".substr(md5((string) $dossier->updated_at?->timestamp), 0, 10) : null,
                'editor_url' => URL::temporarySignedRoute('mobile.floor-plan', now()->addHours(2), ['dossier' => $dossier->id, 'user' => $user->id, 'lang' => app()->getLocale()]),
                'title' => __('erp.batteries.tabs.floor_plan'),
            ],
        ];
    }
}
