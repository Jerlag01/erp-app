<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReportIncident;
use App\Enums\IncidentType;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\PrivatePhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Reporting an incident from the mobile app (accident, near miss, dangerous situation or action), with
 * photos from the camera: saved like the "Report an incident" page, the administrators are told. The
 * form texts come from the ERP, in the user's language.
 */
class IncidentController extends Controller
{
    public const MAX_PHOTOS = 20;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeReport($user);

        return response()->json(['data' => [
            'types' => array_map(fn (IncidentType $type): array => ['value' => $type->value, 'label' => $type->getLabel()], IncidentType::cases()),
            'place_types' => $this->placeTypes(),
            'locations' => modules()->locations()
                ? Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn (Location $location): array => ['id' => $location->id, 'label' => $location->name])->values()
                : [],
            'max_photos' => self::MAX_PHOTOS,
            'labels' => collect([
                'what', 'type', 'where', 'place_type', 'location_details', 'location_details_placeholder', 'consequences',
                'other_victims', 'other_victims_details', 'material_damage', 'material_damage_details', 'description',
                'description_placeholder', 'photos', 'photos_help', 'submit', 'my_reports', 'automatic_time',
            ])->mapWithKeys(fn (string $key): array => [$key => __("erp.incidents.{$key}")]),
            'mine' => Incident::query()->where('user_id', $user->id)->latest('reported_at')->limit(10)->get()->map(fn (Incident $incident): array => [
                'id' => $incident->id,
                'reported_at' => $incident->reported_at->toIso8601String(),
                'type' => $incident->type->getLabel(),
                'place' => $incident->placeName() ?? $incident->location_details,
                'status' => $incident->status->getLabel(),
                'status_value' => $incident->status->value,
            ])->values(),
        ]]);
    }

    /**
     * Work sites by COW code or address, for the place of the incident.
     */
    public function workSites(Request $request): JsonResponse
    {
        $this->authorizeReport($request->user());
        abort_unless(modules()->workSites(), 404);

        $search = trim((string) $request->query('search', ''));

        $sites = WorkSite::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('cow_code', 'like', "%{$search}%")
                ->orWhere('street', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('postal_code', 'like', "{$search}%")))
            ->orderBy('cow_code')
            ->limit(30)
            ->get();

        return response()->json(['data' => $sites->map(fn (WorkSite $site): array => ['id' => $site->id, 'label' => Incident::labelFor($site)])->values()]);
    }

    public function store(Request $request, ReportIncident $report): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeReport($user);
        $placeTypes = array_column($this->placeTypes(), 'value');

        $data = $request->validate([
            'type' => ['required', Rule::enum(IncidentType::class)],
            'place_type' => [$placeTypes === [] ? 'nullable' : 'required', Rule::in($placeTypes)],
            'place_id' => ['nullable', 'required_with:place_type', 'integer'],
            'location_details' => [$placeTypes === [] ? 'required' : 'nullable', 'string', 'max:255'],
            'other_victims' => ['nullable', 'boolean'],
            'other_victims_details' => ['nullable', 'string', 'max:5000'],
            'material_damage' => ['nullable', 'boolean'],
            'material_damage_details' => ['nullable', 'string', 'max:5000'],
            'description' => ['required', 'string', 'max:10000'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimetypes:'.implode(',', PrivatePhotos::TYPES), 'max:'.PrivatePhotos::MAX_KILOBYTES],
        ]);

        $data['photos'] = array_map(fn ($photo): string => $photo->store('incidents/'.now()->format('Y/m'), PrivatePhotos::DISK), $request->file('photos', []));
        $incident = $report->handle($user, $data);

        return response()->json(['message' => __('erp.incidents.thanks').'. '.__('erp.incidents.thanks_body'), 'data' => ['id' => $incident->id]], 201);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function placeTypes(): array
    {
        return array_values(array_filter([
            modules()->locations() ? ['value' => 'location', 'label' => __('erp.incidents.place_types.location')] : null,
            modules()->workSites() ? ['value' => 'work_site', 'label' => __('erp.incidents.place_types.work_site')] : null,
        ]));
    }

    protected function authorizeReport(User $user): void
    {
        abort_unless(Gate::forUser($user)->allows('create', Incident::class), 403, __('erp.mobile.errors.module_off'));
    }
}
