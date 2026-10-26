<?php

namespace App\Http\Resources\V1;

use App\Enums\DriverLicenseCategory;
use App\Enums\RequestStatus;
use App\Http\Controllers\Api\V1\AssetCheckController;
use App\Models\BatteryDossier;
use App\Models\BatteryRoomDossier;
use App\Models\Employee;
use App\Models\EnersysDossier;
use App\Models\Incident;
use App\Models\MoveDossier;
use App\Models\Suggestion;
use App\Models\ToolboxMeeting;
use App\Models\User;
use App\Support\MobileApp;
use App\Support\Planning;
use App\Support\Timesheets;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

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
            'features' => $this->features(),
            // The newest app on the platform (Settings > Mobile app): the phone compares it with its own version.
            'app_update' => app(MobileApp::class)->available() && filled($version = app(MobileApp::class)->version())
                ? ['version' => $version, 'url' => route('mobile-app.download')]
                : null,
        ];
    }

    /**
     * Which parts of the app the user has: the modules that are on and what they may see. Open equipment
     * controls get their own tab while they are open.
     *
     * @return array<string, bool|int>
     */
    protected function features(): array
    {
        /** @var User $user */
        $user = $this->resource;
        $gate = Gate::forUser($user);

        return [
            // A "bediende" has fixed days without times or kilometres: no prestations tab in the app.
            'timesheets' => app(Timesheets::class)->enabled() && ! app(Timesheets::class)->fixedDay($user),
            'leave' => modules()->vacations(),
            'overtime_leave' => modules()->vacations() && app(Timesheets::class)->enabled(),
            'planning' => app(Planning::class)->enabled(),
            'open_asset_checks' => AssetCheckController::openChecks($user)->count(),
            'battery_dossiers' => $gate->allows('viewAny', BatteryDossier::class),
            'enersys_dossiers' => $gate->allows('viewAny', EnersysDossier::class),
            'move_dossiers' => $gate->allows('viewAny', MoveDossier::class),
            'battery_rooms' => $gate->allows('viewAny', BatteryRoomDossier::class),
            'incidents' => $gate->allows('create', Incident::class),
            'suggestions' => $gate->allows('create', Suggestion::class),
            'toolbox' => $gate->allows('viewAny', ToolboxMeeting::class),
            'open_toolbox' => $gate->allows('viewAny', ToolboxMeeting::class)
                ? ToolboxMeeting::query()->published()->whereDoesntHave('participants', fn ($query) => $query->where('user_id', $user->id)->whereNotNull('signed_at'))->count()
                : 0,
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
            'driver_license' => $employee->hasDriverLicense() ? [
                'categories' => array_map(fn (DriverLicenseCategory $category): array => ['value' => $category->value, 'label' => $category->getDescription()], $employee->driverLicenseCategories()),
                'number' => $employee->driver_license_number,
                'expires_on' => $employee->driver_license_expires_on?->toDateString(),
                'expires_soon' => $employee->driverLicenseExpiresSoon(),
                'copies' => count((array) $employee->driver_license_files),
            ] : null,
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
