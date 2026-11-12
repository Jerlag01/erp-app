<?php

namespace App\Filament\Resources\DistributorOrders\Pages;

use App\Filament\Resources\DistributorOrders\DistributorOrderResource;
use App\Models\DistributorOrder;
use Filament\Resources\Pages\CreateRecord;

class CreateDistributorOrder extends CreateRecord
{
    protected static string $resource = DistributorOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'source' => DistributorOrder::SOURCE_MANUAL, 'created_by' => auth()->id()];
    }
}
