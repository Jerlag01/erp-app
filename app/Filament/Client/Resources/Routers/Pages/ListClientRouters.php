<?php

namespace App\Filament\Client\Resources\Routers\Pages;

use App\Filament\Client\Resources\Routers\ClientRouterResource;
use Filament\Resources\Pages\ListRecords;

class ListClientRouters extends ListRecords
{
    protected static string $resource = ClientRouterResource::class;
}
