<?php

namespace App\Filament\Resources\Routers\Pages;

use App\Filament\Resources\Routers\RouterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRouter extends EditRecord
{
    protected static string $resource = RouterResource::class;

    /**
     * The password is a hidden attribute: only load it for users who may see it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (RouterResource::canSeeSecrets()) {
            $data['password'] = $this->getRecord()->password;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return RouterResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
