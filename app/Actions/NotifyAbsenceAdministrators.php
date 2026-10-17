<?php

namespace App\Actions;

use App\Filament\Admin\Resources\Absences\AbsenceResource;
use App\Filament\Resources\VacationRequests\VacationRequestResource;
use App\Models\Absence;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lets the administrators know in the platform that an employee reported sickness or uploaded a
 * certificate for an absence.
 */
class NotifyAbsenceAdministrators
{
    public const REPORTED = 'reported';

    public const UPLOADED = 'uploaded';

    public function handle(Absence $absence, string $event): void
    {
        $admins = User::query()->where('is_active', true)->whereHas('role', fn (Builder $query) => $query->where('is_admin', true))->get();

        foreach ($admins as $admin) {
            $locale = $admin->preferredLocale() ?? config('app.locale');

            Notification::make()
                ->title(__($event === self::REPORTED ? 'erp.absences.reported_title' : 'erp.absences.notification_title', ['name' => $absence->user->name], $locale))
                ->body(trim(__('erp.enums.absence_type.'.$absence->type->value, [], $locale).' · '.VacationRequestResource::period($absence).' '.($absence->note ? '· '.$absence->note : '')))
                ->icon($event === self::REPORTED ? Heroicon::OutlinedHeart : Heroicon::OutlinedDocumentArrowUp)
                ->actions(array_values(array_filter([
                    $absence->hasDocument() ? Action::make('open')
                        ->label(__('erp.absences.download', [], $locale))
                        ->url(AbsenceResource::documentUrl($absence), shouldOpenInNewTab: true) : null,
                ])))
                ->sendToDatabase($admin);
        }
    }
}
