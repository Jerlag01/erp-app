<?php

namespace App\Actions;

use App\Filament\Admin\Resources\Absences\AbsenceResource;
use App\Filament\Resources\VacationRequests\VacationRequestResource;
use App\Models\Absence;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Lets the administrators know in the platform that an employee reported sickness or uploaded a
 * certificate for an absence.
 */
class NotifyAbsenceAdministrators
{
    public const REPORTED = 'reported';

    public const UPLOADED = 'uploaded';

    /** Registered on the platform (by an administrator or Administratie). */
    public const REGISTERED = 'registered';

    public function handle(Absence $absence, string $event): void
    {
        // Administrators and those who manage all leave (e.g. Administratie).
        // Not the one who registered it themselves.
        $admins = User::query()->where('is_active', true)->with('role')->get()->filter(fn (User $user): bool => $user->managesLeave() && $user->id !== auth()->id());

        foreach ($admins as $admin) {
            $locale = $admin->preferredLocale() ?? config('app.locale');

            Notification::make()
                ->title(__(match ($event) {
                    self::REPORTED => 'erp.absences.reported_title',
                    self::REGISTERED => 'erp.absences.registered_title',
                    default => 'erp.absences.notification_title',
                }, ['name' => $absence->user->name], $locale))
                ->body(trim(__('erp.enums.absence_type.'.$absence->type->value, [], $locale).' · '.VacationRequestResource::period($absence).' '.($absence->note ? '· '.$absence->note : '')))
                ->icon(match ($event) {
                    self::REPORTED => Heroicon::OutlinedHeart,
                    self::REGISTERED => Heroicon::OutlinedCalendarDays,
                    default => Heroicon::OutlinedDocumentArrowUp,
                })
                ->actions(array_values(array_filter([
                    $absence->hasDocument() ? Action::make('open')
                        ->label(__('erp.absences.download', [], $locale))
                        ->url(AbsenceResource::documentUrl($absence), shouldOpenInNewTab: true) : null,
                ])))
                ->sendToDatabase($admin);
        }
    }
}
