<?php

namespace App\Filament\Client\Resources\Projects\Pages;

use App\Filament\Client\Resources\Projects\ClientProjectResource;
use Filament\Resources\Pages\ListRecords;

class ListClientProjects extends ListRecords
{
    protected static string $resource = ClientProjectResource::class;
}
