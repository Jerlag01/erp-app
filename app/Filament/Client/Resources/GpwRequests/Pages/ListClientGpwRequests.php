<?php

namespace App\Filament\Client\Resources\GpwRequests\Pages;

use App\Filament\Client\Resources\GpwRequests\ClientGpwRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListClientGpwRequests extends ListRecords
{
    protected static string $resource = ClientGpwRequestResource::class;
}
