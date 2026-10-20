<?php

namespace App\Filament\Admin\Resources\ClientUsers\Pages;

use App\Filament\Admin\Resources\ClientUsers\ClientUserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageClientUsers extends ManageRecords
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('portal')
                ->label(__('erp.client_portal.open_portal'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(url('/client'), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }
}
