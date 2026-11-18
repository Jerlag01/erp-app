<?php

namespace App\Filament\App\Resources\EmployeeTrainings\Pages;

use App\Filament\App\Resources\EmployeeTrainings\EmployeeTrainingResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeTrainings extends ListRecords
{
    protected static string $resource = EmployeeTrainingResource::class;
}
