<?php

namespace App\Filament\Resources\WorkSites\Actions;

use App\Enums\BuildingType;
use App\Models\WorkSite;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * The separate form to change a site's building type: new type, date, reason and remarks,
 * all kept in the site's building type log.
 */
class ChangeBuildingTypeAction
{
    public static function make(): Action
    {
        return Action::make('changeBuildingType')
            ->label(__('erp.work_sites.change_type'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('warning')
            ->visible(fn (WorkSite $record): bool => Gate::allows('changeType', $record))
            ->modalHeading(fn (WorkSite $record): string => __('erp.work_sites.change_type').': '.$record->cow_code)
            ->modalDescription(fn (WorkSite $record): string => __('erp.work_sites.current_type', ['type' => $record->building_type->getLabel()]))
            ->modalSubmitActionLabel(__('erp.work_sites.change_type_submit'))
            ->schema(fn (WorkSite $record): array => [
                Select::make('building_type')
                    ->label(__('erp.work_sites.new_type'))
                    ->options(collect(BuildingType::cases())
                        ->reject(fn (BuildingType $type): bool => $type === $record->building_type)
                        ->mapWithKeys(fn (BuildingType $type): array => [$type->value => $type->getLabel()])
                        ->all())
                    ->required()
                    ->live(),
                DatePicker::make('changed_on')
                    ->label(__('erp.work_sites.changed_on'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->maxDate(today()->addYear())
                    ->required(),
                TextInput::make('reason')
                    ->label(__('erp.work_sites.reason'))
                    ->placeholder(__('erp.work_sites.reason_placeholder'))
                    ->maxLength(150)
                    ->required(),
                Textarea::make('remarks')
                    ->label(__('erp.fields.notes'))
                    ->rows(3),
            ])
            ->fillForm(['changed_on' => today()->toDateString()])
            ->action(fn (WorkSite $record, array $data) => $record->changeBuildingType(
                BuildingType::from($data['building_type']),
                $data['changed_on'],
                $data['reason'] ?? null,
                $data['remarks'] ?? null,
            ))
            ->successNotificationTitle(__('erp.work_sites.type_changed'));
    }
}
