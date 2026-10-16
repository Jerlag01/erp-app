<?php

namespace App\Http\Resources\V1;

use App\Enums\RequestStatus;
use App\Models\Employee;
use App\Models\User;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The user's own details as the mobile app shows them: the same personal details the employee sees on
 * "My details". Labels are translated into the user's language, so the app shows them as they come.
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employee = modules()->employees() ? $this->employee : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->referenceInitials(),
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'locale' => $this->preferredLocale() ?? config('app.locale'),
            'teams' => $this->teams()->orderBy('name')->pluck('name')->all(),
            'employee' => $employee ? $this->employeeDetails($employee) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function employeeDetails(Employee $employee): array
    {
        return [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'full_name' => $employee->fullName(),
            'address' => [
                'street' => $employee->street,
                'house_number' => $employee->house_number,
                'addition' => $employee->addition,
                'postal_code' => $employee->postal_code,
                'city' => $employee->city,
                'country' => $employee->country?->localName(),
                'line' => $employee->addressLine(),
            ],
            'private_email' => $employee->private_email,
            'private_phone' => $employee->private_phone,
            'national_number' => Employee::formatNationalNumber($employee->national_number),
            'bank_account' => Employee::formatIban($employee->bank_account),
            'place_of_birth' => $employee->place_of_birth,
            'date_of_birth' => $employee->date_of_birth?->toDateString(),
            'employment_date' => $employee->employment_date?->toDateString(),
            'mother_tongue' => filled($employee->mother_tongue)
                ? ['value' => $employee->mother_tongue, 'label' => __("erp.employees.languages.{$employee->mother_tongue}")]
                : null,
            'employment_category' => self::option($employee->employment_category),
            'contract_term' => self::option($employee->contract_term),
            'education_level' => self::option($employee->education_level),
            'is_interim' => $employee->is_interim,
            'sizes' => [
                'pants' => $employee->size_pants,
                'shirt' => $employee->size_shirt,
                'sweater' => $employee->size_sweater,
                'shoes' => $employee->size_shoes,
            ],
            'emergency_contacts' => $employee->emergencyContacts(),
            'has_pending_changes' => $employee->changeRequests()->where('status', RequestStatus::Pending)->exists(),
        ];
    }

    /**
     * @return array{value: string, label: string}|null
     */
    protected static function option(?BackedEnum $case): ?array
    {
        return $case === null ? null : ['value' => (string) $case->value, 'label' => $case instanceof HasLabel ? (string) $case->getLabel() : (string) $case->value];
    }
}
