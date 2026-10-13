<?php

namespace App\Filament\App\Pages;

use App\Enums\CertificateType;
use App\Enums\NavigationGroup;
use App\Enums\RequestStatus;
use App\Filament\Admin\Resources\EmployeeCertificateRequests\EmployeeCertificateRequestResource;
use App\Filament\Admin\Resources\EmployeeChangeRequests\EmployeeChangeRequestResource;
use App\Filament\Admin\Resources\Employees\Schemas\EmployeeForm;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * "My details": employees see their own personal details in the employee register and change them
 * themselves. The changes wait for an administrator's approval; editing again replaces the pending
 * request. They also add their own certificates, which an administrator approves as well.
 *
 * @property-read Schema $form
 */
class MyDetails extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.app.my-details';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'my-details';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function employee(): ?Employee
    {
        $user = auth()->user();

        return $user instanceof User ? $user->employee : null;
    }

    public static function canAccess(): bool
    {
        return modules()->employees() && (bool) self::employee()?->isEmployed();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.my_details.title');
    }

    public function getTitle(): string
    {
        return __('erp.my_details.title');
    }

    public function getSubheading(): ?string
    {
        return __('erp.my_details.subheading');
    }

    public function pendingRequest(): ?EmployeeChangeRequest
    {
        return self::employee()?->changeRequests()->where('status', RequestStatus::Pending)->latest('id')->first();
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->fillForm();
    }

    /**
     * The register's values, with the pending changes on top so the employee can keep editing them.
     */
    protected function fillForm(): void
    {
        $employee = self::employee();
        $values = [];

        foreach (EmployeeChangeRequest::FIELDS as $field) {
            $values[$field] = EmployeeChangeRequest::normalize($field, $employee->{$field});
        }

        $this->form->fill([...$values, ...($this->pendingRequest()?->new_values ?? [])]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('erp.employees.tabs.personal'))->icon(Heroicon::OutlinedUser)->columns(6)->schema(EmployeeForm::personal()),
                        Tab::make(__('erp.employees.tabs.emergency'))->icon(Heroicon::OutlinedPhone)->schema([
                            EmployeeForm::emergencyContact(1, required: true),
                            EmployeeForm::emergencyContact(2, required: false),
                        ]),
                        Tab::make(__('erp.employees.tabs.clothing'))->icon(Heroicon::OutlinedSwatch)->columns(4)->schema(EmployeeForm::clothing()),
                    ]),
            ]);
    }

    /**
     * Sends the changes for approval (or withdraws the request when nothing differs any more).
     */
    public function submit(): void
    {
        $employee = self::employee();
        abort_unless($employee && self::canAccess(), 403);

        $data = $this->form->getState();
        $diff = EmployeeChangeRequest::diff($employee, $data);
        $pending = $this->pendingRequest();

        if ($diff === []) {
            $pending?->delete();
            Notification::make()->title(__('erp.my_details.no_changes'))->info()->send();

            return;
        }

        $request = $pending ?? new EmployeeChangeRequest(['employee_id' => $employee->id, 'status' => RequestStatus::Pending]);
        $request->fill([
            'submitted_by' => auth()->id(),
            'new_values' => array_map(fn (array $pair): mixed => $pair[1], $diff),
            'old_values' => array_map(fn (array $pair): mixed => $pair[0], $diff),
        ])->save();

        self::notifyAdmins(__('erp.my_details.notification_changes', ['name' => $employee->fullName()]), EmployeeChangeRequestResource::getUrl(panel: 'admin'));

        Notification::make()->title(__('erp.my_details.submitted'))->body(__('erp.my_details.submitted_help'))->success()->send();
    }

    public static function notifyAdmins(string $title, string $url): void
    {
        User::query()->where('is_active', true)->whereHas('role', fn (Builder $query) => $query->where('is_admin', true))->get()
            ->reject(fn (User $admin): bool => $admin->id === auth()->id())
            ->each(fn (User $admin) => Notification::make()
                ->title($title)
                ->icon('heroicon-o-identification')
                ->warning()
                ->actions([Action::make('open')->label(__('erp.my_details.review', locale: $admin->preferredLocale()))->url($url)])
                ->sendToDatabase($admin));
    }

    public function withdrawAction(): Action
    {
        return Action::make('withdraw')
            ->label(__('erp.my_details.withdraw'))
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (): void {
                $this->pendingRequest()?->delete();
                $this->fillForm();
                Notification::make()->title(__('erp.my_details.withdrawn'))->success()->send();
            });
    }

    public function addCertificateAction(): Action
    {
        $type = fn (Get $get): ?CertificateType => $get('type') instanceof CertificateType ? $get('type') : CertificateType::tryFrom((string) $get('type'));

        return Action::make('addCertificate')
            ->label(__('erp.my_details.add_certificate'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalDescription(__('erp.my_details.add_certificate_help'))
            ->schema([
                Select::make('type')
                    ->label(__('erp.employees.certificate'))
                    ->options(CertificateType::class)
                    ->required()
                    ->live(),
                CheckboxList::make('categories')
                    ->label(__('erp.employees.licence_categories'))
                    ->options(array_combine(CertificateType::DRIVING_LICENCE_CATEGORIES, CertificateType::DRIVING_LICENCE_CATEGORIES))
                    ->columns(['default' => 4, 'md' => 8])
                    ->in(CertificateType::DRIVING_LICENCE_CATEGORIES)
                    ->visible(fn (Get $get): bool => $type($get) === CertificateType::DrivingLicence)
                    ->required(fn (Get $get): bool => $type($get) === CertificateType::DrivingLicence),
                DatePicker::make('obtained_on')->label(__('erp.employees.obtained_on'))->native(false)->displayFormat('d/m/Y')->maxDate(today()),
                DatePicker::make('expires_on')
                    ->label(__('erp.employees.expires_on'))
                    ->helperText(__('erp.employees.expires_on_help'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('obtained_on')
                    ->visible(fn (Get $get): bool => $type($get)?->canExpire() ?? true),
                FileUpload::make('document')
                    ->label(__('erp.employees.certificate_pdf'))
                    ->disk(Employee::DISK)
                    ->directory(fn (): string => 'employees/'.self::employee()?->id.'/certificate-requests')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('document_name'),
                TextInput::make('notes')
                    ->label(fn (Get $get): string => $type($get) === CertificateType::SchoolDegree ? __('erp.employees.degree') : __('erp.fields.notes'))
                    ->placeholder(fn (Get $get): ?string => $type($get) === CertificateType::SchoolDegree ? __('erp.employees.degree_placeholder') : null)
                    ->required(fn (Get $get): bool => $type($get) === CertificateType::SchoolDegree)
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $employee = self::employee();
                abort_unless($employee && self::canAccess(), 403);

                $employee->certificateRequests()->create([
                    ...$data,
                    'submitted_by' => auth()->id(),
                    'status' => RequestStatus::Pending,
                ]);

                self::notifyAdmins(
                    __('erp.my_details.notification_certificate', ['name' => $employee->fullName()]),
                    EmployeeCertificateRequestResource::getUrl(panel: 'admin'),
                );

                Notification::make()->title(__('erp.my_details.certificate_submitted'))->body(__('erp.my_details.submitted_help'))->success()->send();
            });
    }

    public function withdrawCertificateAction(): Action
    {
        return Action::make('withdrawCertificate')
            ->label(__('erp.my_details.withdraw'))
            ->color('gray')
            ->size('sm')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                self::employee()?->certificateRequests()->where('status', RequestStatus::Pending)->whereKey($arguments['request'] ?? 0)->first()?->delete();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $employee = self::employee();

        return [
            'pending' => $this->pendingRequest(),
            'certificates' => $employee->certificates()->orderBy('type')->get(),
            'requests' => $employee->certificateRequests()->latest()->limit(20)->get(),
        ];
    }
}
