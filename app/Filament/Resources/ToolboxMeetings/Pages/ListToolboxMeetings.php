<?php

namespace App\Filament\Resources\ToolboxMeetings\Pages;

use App\Filament\Pages\ToolboxOverview;
use App\Filament\Resources\ToolboxMeetings\ToolboxMeetingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListToolboxMeetings extends ListRecords
{
    protected static string $resource = ToolboxMeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('overview')
                ->label(__('erp.toolbox.overview.title'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->visible(fn (): bool => ToolboxOverview::canAccess())
                ->url(fn (): string => ToolboxOverview::getUrl()),
            CreateAction::make(),
        ];
    }
}
