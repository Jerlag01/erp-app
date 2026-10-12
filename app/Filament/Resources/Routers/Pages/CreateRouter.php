<?php

namespace App\Filament\Resources\Routers\Pages;

use App\Filament\Resources\Routers\RouterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRouter extends CreateRecord
{
    protected static string $resource = RouterResource::class;

    protected function getRedirectUrl(): string
    {
        return RouterResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
