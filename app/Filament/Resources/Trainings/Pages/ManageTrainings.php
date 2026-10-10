<?php

namespace App\Filament\Resources\Trainings\Pages;

use App\Filament\Pages\Planning;
use App\Filament\Resources\Trainings\TrainingResource;
use App\Models\Training;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageTrainings extends ManageRecords
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()])
                ->after(fn (Training $record) => self::notifyParticipants($record)),
        ];
    }

    /**
     * Tells the participants in the platform that they have a training (or that it changed).
     */
    public static function notifyParticipants(Training $training, bool $updated = false): void
    {
        $training->load('participants');
        $period = $training->start_date->equalTo($training->end_date)
            ? $training->start_date->format('d/m/Y')
            : $training->start_date->format('d/m/Y').' → '.$training->end_date->format('d/m/Y');

        $training->participants
            ->reject(fn (User $user): bool => $user->id === auth()->id() || ! $user->is_active)
            ->each(fn (User $user) => Notification::make()
                ->title(__($updated ? 'erp.trainings.notification_updated' : 'erp.trainings.notification_new', ['name' => $training->name], $user->preferredLocale()))
                ->body(implode(' · ', array_filter([$period, $training->company, $training->addressLine()])))
                ->icon('heroicon-o-academic-cap')
                ->info()
                ->actions([
                    Action::make('open')
                        ->label(__('erp.planning.notifications.open', locale: $user->preferredLocale()))
                        ->url(Planning::getUrl(['date' => $training->start_date->toDateString()], panel: 'app')),
                ])
                ->sendToDatabase($user));
    }
}
