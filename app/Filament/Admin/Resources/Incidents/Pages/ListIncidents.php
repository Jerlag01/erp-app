<?php

namespace App\Filament\Admin\Resources\Incidents\Pages;

use App\Filament\Admin\Resources\Incidents\IncidentResource;
use App\Models\Incident;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListIncidents extends ListRecords
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // All reports of one employee in one PDF.
            Action::make('exportEmployee')
                ->label(__('erp.incidents.export_employee'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->schema([
                    Select::make('user_id')
                        ->label(__('erp.incidents.reporter'))
                        ->options(fn (): array => IncidentResource::reporters())
                        ->searchable()
                        ->required(),
                ])
                ->action(fn (array $data) => $this->js('window.open('.json_encode(IncidentResource::pdfUrl(
                    Incident::query()->where('user_id', $data['user_id'])->pluck('id'),
                )).', "_blank")')),
        ];
    }
}
