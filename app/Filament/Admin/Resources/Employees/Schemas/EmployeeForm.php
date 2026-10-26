<?php

namespace App\Filament\Admin\Resources\Employees\Schemas;

use App\Enums\ContractTerm;
use App\Enums\DriverLicenseCategory;
use App\Enums\EducationLevel;
use App\Enums\EmploymentCategory;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

class EmployeeForm
{
    /**
     * Interim employees (through an interim agency) are not asked for their national number, bank account,
     * date of birth and emergency contacts; the fields come back when "interim" is switched off.
     */
    public static function isInterim(Get $get): bool
    {
        return (bool) $get('is_interim');
    }

    public const SHIRT_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('erp.employees.tabs.personal'))
                            ->icon(Heroicon::OutlinedUser)
                            ->columns(6)
                            ->schema(self::personal()),
                        Tab::make(__('erp.employees.tabs.employment'))
                            ->icon(Heroicon::OutlinedBriefcase)
                            ->columns(2)
                            ->schema(self::employment()),
                        Tab::make(__('erp.employees.tabs.emergency'))
                            ->icon(Heroicon::OutlinedPhone)
                            ->hidden(fn (Get $get): bool => self::isInterim($get))
                            ->schema([
                                self::emergencyContact(1, required: true),
                                self::emergencyContact(2, required: false),
                            ]),
                        Tab::make(__('erp.employees.tabs.clothing'))
                            ->icon(Heroicon::OutlinedSwatch)
                            ->columns(4)
                            ->schema(self::clothing()),
                        Tab::make(__('erp.employees.tabs.driver_license'))
                            ->icon(Heroicon::OutlinedIdentification)
                            ->columns(2)
                            ->schema(self::driverLicense()),
                        Tab::make(__('erp.employees.tabs.account'))
                            ->icon(Heroicon::OutlinedKey)
                            ->columns(2)
                            ->visibleOn('create')
                            ->schema(self::account()),
                    ]),
            ]);
    }

    /**
     * The driver license: one or more categories, the number, until when it is valid and a copy of the
     * card (front and back, photos or PDF).
     *
     * @return list<mixed>
     */
    public static function driverLicense(): array
    {
        return [
            CheckboxList::make('driver_license_categories')
                ->label(__('erp.employees.driver_license_categories'))
                ->options(DriverLicenseCategory::class)
                ->descriptions(collect(DriverLicenseCategory::cases())->mapWithKeys(fn (DriverLicenseCategory $category): array => [$category->value => $category->getDescription()])->all())
                ->columns(['default' => 2, 'md' => 3, 'xl' => 5])
                ->gridDirection('row')
                ->bulkToggleable()
                ->columnSpanFull(),
            TextInput::make('driver_license_number')
                ->label(__('erp.employees.driver_license_number'))
                ->maxLength(30),
            DatePicker::make('driver_license_expires_on')
                ->label(__('erp.employees.driver_license_expires_on')),
            FileUpload::make('driver_license_files')
                ->label(__('erp.employees.driver_license_files'))
                ->helperText(__('erp.employees.driver_license_files_help'))
                ->multiple()
                ->maxFiles(6)
                ->appendFiles()
                ->disk(Employee::DISK)
                ->directory('employees/driver-licenses')
                ->visibility('private')
                ->acceptedFileTypes(Employee::DRIVER_LICENSE_FILE_TYPES)
                ->maxSize(12288)
                ->storeFileNamesIn('driver_license_file_names')
                ->downloadable()
                ->openable()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function personal(): array
    {
        return [
            TextInput::make('first_name')
                ->label(__('erp.fields.first_name'))
                ->required()
                ->maxLength(100)
                ->columnSpan(3),
            TextInput::make('last_name')
                ->label(__('erp.fields.last_name'))
                ->required()
                ->maxLength(100)
                ->columnSpan(3),
            TextInput::make('street')
                ->label(__('erp.fields.street_only'))
                ->required()
                ->maxLength(150)
                ->columnSpan(4),
            TextInput::make('house_number')
                ->label(__('erp.fields.house_number'))
                ->required()
                ->maxLength(20)
                ->columnSpan(1),
            TextInput::make('addition')
                ->label(__('erp.fields.addition'))
                ->maxLength(20)
                ->columnSpan(1),
            TextInput::make('postal_code')
                ->label(__('erp.fields.postal_code'))
                ->required()
                ->maxLength(20)
                ->columnSpan(2),
            TextInput::make('city')
                ->label(__('erp.fields.city'))
                ->required()
                ->maxLength(100)
                ->columnSpan(2),
            Select::make('country_id')
                ->label(__('erp.fields.country'))
                ->options(fn (): array => Country::options())
                ->default(fn (): ?int => Country::belgiumId())
                ->searchable()
                ->required()
                ->columnSpan(2),
            TextInput::make('private_email')
                ->label(__('erp.employees.private_email'))
                ->email()
                ->maxLength(255)
                ->live(onBlur: true)
                ->columnSpan(3),
            TextInput::make('private_phone')
                ->label(__('erp.employees.private_phone'))
                ->tel()
                ->maxLength(50)
                ->columnSpan(3),
            TextInput::make('national_number')
                ->label(__('erp.employees.national_number'))
                ->helperText(__('erp.employees.national_number_help'))
                ->placeholder('85.07.30-033.28')
                ->maxLength(20)
                ->formatStateUsing(fn (?string $state): ?string => Employee::formatNationalNumber($state))
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! Employee::isValidNationalNumber((string) $value)) {
                        $fail(__('erp.employees.national_number_invalid'));
                    }
                })
                ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);'])
                ->hidden(fn (Get $get): bool => self::isInterim($get))
                ->columnSpan(2),
            TextInput::make('place_of_birth')
                ->label(__('erp.employees.place_of_birth'))
                ->maxLength(100)
                ->columnSpan(2),
            DatePicker::make('date_of_birth')
                ->label(__('erp.employees.date_of_birth'))
                ->native(false)
                ->displayFormat('d/m/Y')
                ->maxDate(today())
                ->hidden(fn (Get $get): bool => self::isInterim($get))
                ->columnSpan(2),
            Select::make('mother_tongue')
                ->label(__('erp.employees.mother_tongue'))
                ->options(fn (): array => collect(Employee::MOTHER_TONGUES)->mapWithKeys(fn (string $code): array => [$code => __("erp.employees.languages.{$code}")])->all())
                ->columnSpan(2),
            Select::make('education_level')
                ->label(__('erp.employees.education_level'))
                ->options(EducationLevel::class)
                ->columnSpan(2),
            TextInput::make('bank_account')
                ->label(__('erp.employees.bank_account'))
                ->placeholder('BE68 5390 0754 7034')
                ->maxLength(42)
                ->formatStateUsing(fn (?string $state): ?string => Employee::formatIban($state))
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! Employee::isValidIban((string) $value)) {
                        $fail(__('erp.employees.bank_account_invalid'));
                    }
                })
                ->extraInputAttributes(['style' => 'font-family: var(--erp-mono); text-transform: uppercase;'])
                ->hidden(fn (Get $get): bool => self::isInterim($get))
                ->columnSpan(2),
        ];
    }

    /**
     * "start" when the statute is being set to "arbeidsongeschikt", "end" when it changes back from it,
     * else null.
     */
    public static function incapacityChange(mixed $selected, ?Employee $record): ?string
    {
        $selected = $selected instanceof EmploymentCategory ? $selected : EmploymentCategory::tryFrom((string) $selected);
        $current = $record?->employment_category;

        return match (true) {
            $selected === EmploymentCategory::Incapacitated && $current !== EmploymentCategory::Incapacitated => 'start',
            $current === EmploymentCategory::Incapacitated && $selected !== null && $selected !== EmploymentCategory::Incapacitated => 'end',
            default => null,
        };
    }

    /**
     * @return list<mixed>
     */
    protected static function employment(): array
    {
        return [
            DatePicker::make('employment_date')
                ->label(__('erp.employees.employment_date'))
                ->native(false)
                ->displayFormat('d/m/Y')
                ->default(today())
                ->required(),
            Select::make('employment_category')
                ->label(__('erp.employees.employment_category'))
                ->options(EmploymentCategory::class)
                ->required()
                ->live(),
            // Set to or back from "arbeidsongeschikt": the date for the incapacity log (not stored on the employee).
            DatePicker::make('incapacity_date')
                ->label(fn (Get $get, ?Employee $record): string => self::incapacityChange($get('employment_category'), $record) === 'start'
                    ? __('erp.employees.incapacity.start_date')
                    : __('erp.employees.incapacity.end_date'))
                ->helperText(fn (Get $get, ?Employee $record): string => self::incapacityChange($get('employment_category'), $record) === 'start'
                    ? __('erp.employees.incapacity.start_help')
                    : __('erp.employees.incapacity.end_help'))
                ->native(false)
                ->displayFormat('d/m/Y')
                ->default(null)
                ->afterOrEqual(fn (?Employee $record): ?string => $record?->incapacityPeriods()->whereNull('end_date')->value('start_date'))
                ->required(fn (Get $get, ?Employee $record): bool => self::incapacityChange($get('employment_category'), $record) !== null)
                ->visible(fn (Get $get, ?Employee $record): bool => self::incapacityChange($get('employment_category'), $record) !== null),
            Select::make('contract_term')
                ->label(__('erp.employees.contract_term'))
                ->options(ContractTerm::class)
                ->required(),
            Toggle::make('is_interim')
                ->label(__('erp.employees.is_interim'))
                ->helperText(__('erp.employees.is_interim_help'))
                ->live()
                ->inline(false),
            TextInput::make('interim_agency')
                ->label(__('erp.employees.interim_agency'))
                ->placeholder('Randstad, Adecco, Start People…')
                ->maxLength(150)
                ->visible(fn (Get $get): bool => (bool) $get('is_interim')),
            Toggle::make('is_mentor')
                ->label(__('erp.employees.is_mentor'))
                ->helperText(__('erp.employees.is_mentor_help'))
                ->inline(false),
            TextInput::make('job_title')
                ->label(__('erp.fields.job_title'))
                ->maxLength(100)
                ->visibleOn('create'),
            Textarea::make('notes')
                ->label(__('erp.fields.notes'))
                ->rows(3)
                ->maxLength(5000)
                ->columnSpanFull(),
        ];
    }

    /**
     * The first emergency contact is required; the second only becomes required once any
     * of its fields is filled in, so it is complete or empty.
     */
    public static function emergencyContact(int $number, bool $required): Section
    {
        $fields = ['first_name', 'last_name', 'phone', 'relation'];
        $isRequired = fn (Get $get): bool => $required || collect($fields)->contains(fn (string $field): bool => filled($get("emergency{$number}_{$field}")));

        return Section::make(__("erp.employees.emergency_contact_{$number}"))
            ->description($required ? null : __('erp.employees.optional'))
            ->columns(4)
            ->schema([
                TextInput::make("emergency{$number}_first_name")
                    ->label(__('erp.fields.first_name'))
                    ->required($isRequired)
                    ->live(onBlur: true)
                    ->maxLength(100),
                TextInput::make("emergency{$number}_last_name")
                    ->label(__('erp.fields.last_name'))
                    ->required($isRequired)
                    ->live(onBlur: true)
                    ->maxLength(100),
                TextInput::make("emergency{$number}_phone")
                    ->label(__('erp.fields.phone'))
                    ->tel()
                    ->required($isRequired)
                    ->live(onBlur: true)
                    ->maxLength(50),
                TextInput::make("emergency{$number}_relation")
                    ->label(__('erp.employees.relation'))
                    ->placeholder(__('erp.employees.relation_placeholder'))
                    ->required($isRequired)
                    ->live(onBlur: true)
                    ->maxLength(100),
            ]);
    }

    /**
     * @return list<mixed>
     */
    public static function clothing(): array
    {
        $sizes = fn (array $values): array => array_combine($values, $values);

        return [
            TextInput::make('size_pants')
                ->label(__('erp.employees.size_pants'))
                ->placeholder('50 / W32-L34')
                ->maxLength(20),
            Select::make('size_shirt')
                ->label(__('erp.employees.size_shirt'))
                ->options($sizes(self::SHIRT_SIZES)),
            Select::make('size_sweater')
                ->label(__('erp.employees.size_sweater'))
                ->options($sizes(self::SHIRT_SIZES)),
            Select::make('size_shoes')
                ->label(__('erp.employees.size_shoes'))
                ->options($sizes(array_map('strval', range(35, 50)))),
        ];
    }

    /**
     * Only when adding an employee: a new login account (with welcome mail), or an existing one.
     *
     * @return list<mixed>
     */
    protected static function account(): array
    {
        $linksExisting = fn (Get $get): bool => (bool) $get('link_existing');

        return [
            Toggle::make('link_existing')
                ->label(__('erp.employees.link_existing'))
                ->helperText(__('erp.employees.link_existing_help'))
                ->live()
                ->dehydrated(false)
                ->columnSpanFull(),
            Select::make('existing_user_id')
                ->label(__('erp.employees.existing_account'))
                ->options(fn (): array => User::query()->whereDoesntHave('employee')->orderBy('name')->get()->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->email})"])->all())
                ->searchable()
                ->required($linksExisting)
                ->visible($linksExisting)
                ->columnSpanFull(),
            TextInput::make('login_email')
                ->label(__('erp.employees.login_email'))
                ->helperText(__('erp.employees.login_email_help'))
                ->email()
                ->maxLength(255)
                // Empty = the private e-mail address is used to log in.
                ->required(fn (Get $get): bool => ! $get('link_existing') && blank($get('private_email')))
                ->rule(fn (Get $get) => $get('link_existing') ? null : Rule::unique('users', 'email'))
                ->placeholder(fn (Get $get): ?string => $get('private_email'))
                ->hidden($linksExisting),
            Select::make('locale')
                ->label(__('erp.fields.language'))
                ->helperText(__('erp.employees.locale_help'))
                ->options(config('app.locales'))
                ->default(config('app.locale'))
                ->selectablePlaceholder(false)
                ->hidden($linksExisting),
            Select::make('role_id')
                ->label(__('erp.resources.role.singular'))
                ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn (): ?int => Role::query()->where('name', 'Medewerker')->value('id') ?? Role::guest()?->id)
                ->required(fn (Get $get): bool => ! $get('link_existing'))
                ->hidden($linksExisting),
            Select::make('teams')
                ->label(__('erp.resources.team.plural'))
                ->options(fn (): array => Team::query()->orderBy('name')->pluck('name', 'id')->all())
                ->multiple()
                ->visible(fn (): bool => modules()->teams()),
        ];
    }
}
