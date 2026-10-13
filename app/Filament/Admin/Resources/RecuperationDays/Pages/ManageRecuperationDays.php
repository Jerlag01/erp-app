<?php

namespace App\Filament\Admin\Resources\RecuperationDays\Pages;

use App\Filament\Admin\Resources\RecuperationDays\RecuperationDayResource;
use App\Models\Holiday;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageRecuperationDays extends ManageRecords
{
    protected static string $resource = RecuperationDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Several IR days at once, e.g. all of next year.
            Action::make('addSeveral')
                ->label(__('erp.recuperation_days.add_several'))
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->modalDescription(__('erp.recuperation_days.add_several_help'))
                ->schema([
                    Repeater::make('days')
                        ->hiddenLabel()
                        ->table([
                            Repeater\TableColumn::make(__('erp.fields.date')),
                            Repeater\TableColumn::make(__('erp.recuperation_days.description')),
                        ])
                        ->schema([
                            DatePicker::make('date')->native(false)->displayFormat('d/m/Y')->required(),
                            TextInput::make('name')->default(__('erp.recuperation_days.default_name'))->required()->maxLength(100),
                        ])
                        ->defaultItems(3)
                        ->minItems(1)
                        ->maxItems(60),
                ])
                ->action(function (array $data): void {
                    [$added, $taken] = [0, []];

                    foreach ($data['days'] as $day) {
                        $existing = Holiday::query()->whereDate('date', $day['date'])->first();

                        if ($existing) {
                            $taken[] = $existing->date->format('d/m/Y').' ('.$existing->label().')';

                            continue;
                        }

                        Holiday::query()->create(['date' => $day['date'], 'name' => $day['name'], 'type' => Holiday::RECUPERATION]);
                        $added++;
                    }

                    Notification::make()
                        ->title(trans_choice('erp.recuperation_days.added', $added, ['count' => $added]))
                        ->body($taken ? __('erp.recuperation_days.skipped', ['dates' => implode(', ', $taken)]) : null)
                        ->{$taken ? 'warning' : 'success'}()
                        ->send();
                }),
            CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'type' => Holiday::RECUPERATION]),
        ];
    }
}
