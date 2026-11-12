<?php

namespace App\Filament\Resources\DistributorOrders\Pages;

use App\Filament\Resources\DistributorOrders\DistributorOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDistributorOrder extends ViewRecord
{
    protected static string $resource = DistributorOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
