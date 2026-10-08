<?php

namespace App\Filament\Admin\Resources\Employees\Pages;

use App\Actions\Employees\RegisterEmployee;
use App\Filament\Admin\Resources\Employees\EmployeeResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Adding an employee also creates their login account and sends the welcome mail
 * (or links an existing account).
 */
class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (blank($data['existing_user_id'] ?? null)) {
            $data['login_email'] = strtolower(trim((string) (filled($data['login_email'] ?? null) ? $data['login_email'] : $data['private_email'])));

            // The private address may already belong to an account; say so on the right field.
            if (User::query()->where('email', $data['login_email'])->exists()) {
                throw ValidationException::withMessages(['data.login_email' => __('erp.employees.login_email_taken', ['email' => $data['login_email']])]);
            }
        }

        $result = app(RegisterEmployee::class)->handle($data);

        if (blank($data['existing_user_id'] ?? null)) {
            $result['mailed']
                ? Notification::make()->title(__('erp.employees.welcome_sent', ['email' => $data['login_email']]))->success()->send()
                : Notification::make()->title(__('erp.employees.welcome_failed'))->body(__('erp.employees.welcome_failed_help'))->warning()->persistent()->send();
        }

        return $result['employee'];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return EmployeeResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
