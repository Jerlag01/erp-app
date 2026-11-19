<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * A client with its communication points, where its invoices stand and the list of its invoices and credit notes.
 */
class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    public function getTitle(): string
    {
        /** @var Client $client */
        $client = $this->getRecord();

        return $client->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->slideOver(),
        ];
    }
}
