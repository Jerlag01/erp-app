<?php

namespace App\Filament\Admin\Resources\ParentalLeaves\Pages;

use App\Filament\Admin\Resources\ParentalLeaves\ParentalLeaveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageParentalLeaves extends ManageRecords
{
    protected static string $resource = ParentalLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
        ];
    }
}
