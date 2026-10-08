<?php

namespace App\Filament\App\Resources\MyVacations\Pages;

use App\Actions\SubmitVacationRequest;
use App\Filament\App\Resources\MyVacations\MyVacationResource;
use App\Filament\App\Widgets\VacationBalance;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ListMyVacations extends ManageRecords
{
    protected static string $resource = MyVacationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            VacationBalance::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('erp.vacations.request'))
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading(__('erp.vacations.request'))
                ->createAnother(false)
                ->using(fn (array $data): Model => app(SubmitVacationRequest::class)->handle(auth()->user(), $data))
                ->successNotificationTitle(__('erp.vacations.submitted'))
                ->after(fn () => $this->dispatch('vacation-balance-changed')),
        ];
    }
}
