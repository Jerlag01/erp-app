<?php

namespace App\Actions;

use App\Enums\IncidentType;
use App\Filament\Admin\Resources\Incidents\IncidentResource;
use App\Models\Incident;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkSite;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Saves an incident report for the signed-in employee, with the system date and time,
 * and lets the administrators know.
 */
class ReportIncident
{
    /**
     * @param  array<string, mixed>  $data  form data: type, place_type, place_id, location_details, other_victims(_details),
     *                                      material_damage(_details), description, photos
     */
    public function handle(User $reporter, array $data): Incident
    {
        $place = $this->resolvePlace($data['place_type'] ?? null, $data['place_id'] ?? null);

        $incident = Incident::create([
            'user_id' => $reporter->id,
            'reporter_name' => $reporter->name,
            'reported_at' => now(),
            'type' => $data['type'] instanceof IncidentType ? $data['type'] : IncidentType::from($data['type']),
            'place_type' => $place?->getMorphClass(),
            'place_id' => $place?->getKey(),
            'place_label' => $place ? Incident::labelFor($place) : null,
            'location_details' => $data['location_details'] ?? null,
            'other_victims' => (bool) ($data['other_victims'] ?? false),
            'other_victims_details' => ($data['other_victims'] ?? false) ? ($data['other_victims_details'] ?? null) : null,
            'material_damage' => (bool) ($data['material_damage'] ?? false),
            'material_damage_details' => ($data['material_damage'] ?? false) ? ($data['material_damage_details'] ?? null) : null,
            'description' => $data['description'],
            'photos' => array_values((array) ($data['photos'] ?? [])),
        ]);

        $this->notifyAdministrators($incident);

        return $incident;
    }

    /**
     * Only places from switched-on modules can be chosen.
     */
    protected function resolvePlace(?string $type, mixed $id): Location|WorkSite|null
    {
        if (blank($type) || blank($id)) {
            return null;
        }

        $place = match ($type) {
            'location' => modules()->locations() ? Location::find($id) : null,
            'work_site' => modules()->workSites() ? WorkSite::find($id) : null,
            default => null,
        };

        if ($place === null) {
            throw ValidationException::withMessages(['place_id' => __('erp.incidents.errors.place')]);
        }

        return $place;
    }

    protected function notifyAdministrators(Incident $incident): void
    {
        $admins = User::query()->active()->whereHas('role', fn ($query) => $query->where('is_admin', true))->get();

        foreach ($admins as $admin) {
            $locale = $admin->preferredLocale();

            Notification::make()
                ->title(__('erp.incidents.notification_title', ['type' => __('erp.enums.incident_type.'.$incident->type->value, [], $locale)], $locale))
                ->body(__('erp.incidents.notification_body', ['name' => $incident->reporter_name, 'place' => $incident->place_label ?? $incident->location_details ?? '—'], $locale))
                ->icon('heroicon-o-exclamation-triangle')
                ->status($incident->type === IncidentType::Accident ? 'danger' : 'warning')
                ->actions([
                    Action::make('open')
                        ->label(__('erp.incidents.open', [], $locale))
                        ->url(IncidentResource::getUrl('view', ['record' => $incident], panel: 'admin')),
                ])
                ->sendToDatabase($admin);
        }
    }
}
