<?php

namespace App\Filament\Resources\AssetLogs\Pages;

use App\Filament\Resources\AssetLogs\AssetLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAssetLogs extends ManageRecords
{
    protected static string $resource = AssetLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
        ];
    }
}
