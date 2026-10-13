<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\Schemas\UserPermissions;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data[UserPermissions::STATE] = UserPermissions::state($this->getRecord()->role, $this->getRecord()->permissions);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['permissions'] = UserPermissions::overrides((array) ($data[UserPermissions::STATE] ?? []), Role::find($data['role_id'] ?? $this->getRecord()->role_id));
        unset($data[UserPermissions::STATE]);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
