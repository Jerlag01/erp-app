<?php

namespace App\Filament\Admin\Resources\Holidays\Pages;

use App\Filament\Admin\Resources\Holidays\HolidayResource;
use App\Models\Holiday;
use App\Support\BelgianHolidays;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageHolidays extends ManageRecords
{
    protected static string $resource = HolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addBelgianHolidays')
                ->label(__('erp.holidays.add_legal'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->modalDescription(__('erp.holidays.add_legal_help'))
                ->schema([
                    Select::make('year')
                        ->label(__('erp.fields.year'))
                        ->options(fn (): array => collect(range((int) date('Y') - 1, (int) date('Y') + 3))->mapWithKeys(fn (int $year): array => [$year => $year])->all())
                        ->default((int) date('Y') + (now()->month >= 10 ? 1 : 0))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $added = 0;

                    foreach (BelgianHolidays::forYear((int) $data['year']) as $date => $name) {
                        $added += Holiday::firstOrCreate(['date' => $date], ['name' => $name])->wasRecentlyCreated ? 1 : 0;
                    }

                    Notification::make()
                        ->title(__('erp.holidays.added', ['count' => $added, 'year' => $data['year']]))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
