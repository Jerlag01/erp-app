<?php

namespace App\Filament\Admin\Resources\EmployeeChangeRequests\Pages;

use App\Filament\Admin\Resources\EmployeeChangeRequests\EmployeeChangeRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeChangeRequests extends ManageRecords
{
    protected static string $resource = EmployeeChangeRequestResource::class;
}
