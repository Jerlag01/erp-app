<?php

namespace App\Models;

use App\Enums\ContractTerm;
use App\Enums\DriverLicenseCategory;
use App\Enums\EducationLevel;
use App\Enums\EmploymentCategory;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An employee in the employee register, linked to a login account (users). The national number
 * and bank account are encrypted in the database. When an employee leaves, the record stays and
 * only the login is switched off.
 */
#[Fillable([
    'user_id', 'first_name', 'last_name',
    'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id',
    'private_email', 'private_phone', 'national_number', 'bank_account',
    'place_of_birth', 'date_of_birth', 'employment_date', 'notice_started_on', 'notice_ends_on', 'mother_tongue',
    'employment_category', 'contract_term', 'is_mentor', 'is_interim', 'interim_agency', 'education_level',
    'driver_license_categories', 'driver_license_number', 'driver_license_expires_on', 'driver_license_files', 'driver_license_file_names',
    'size_pants', 'size_shirt', 'size_sweater', 'size_shoes',
    'emergency1_first_name', 'emergency1_last_name', 'emergency1_phone', 'emergency1_relation',
    'emergency2_first_name', 'emergency2_last_name', 'emergency2_phone', 'emergency2_relation',
    'notes', 'left_on', 'leaving_reason', 'welcome_sent_at',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * The date for the incapacity log when the statute changes (from the form): the first day unfit for
     * work, or the last day of sickness when it changes back. Not stored on the employee itself.
     */
    public ?string $incapacityDate = null;

    /**
     * Private disk for certificate and medical PDFs.
     */
    public const DISK = 'local';

    /**
     * Certificates expiring within this many days are flagged.
     */
    public const EXPIRY_WARNING_DAYS = 60;

    /**
     * The occupational doctor sees every employee once a year.
     */
    public const MEDICAL_INTERVAL_MONTHS = 12;

    /**
     * Copies of the driver license: photos of the card (any picture type) or a PDF.
     */
    public const DRIVER_LICENSE_FILE_TYPES = ['image/*', 'application/pdf'];

    public const MOTHER_TONGUES = ['nl', 'fr', 'de', 'en', 'other'];

    /**
     * @return HasMany<IncapacityPeriod, $this>
     */
    public function incapacityPeriods(): HasMany
    {
        return $this->hasMany(IncapacityPeriod::class)->orderByDesc('start_date');
    }

    public function isIncapacitated(): bool
    {
        return $this->employment_category === EmploymentCategory::Incapacitated;
    }

    /**
     * Logs the statute "arbeidsongeschikt": a period opens when it is set (from today, or the date
     * given) and closes with the last day of sickness when the statute changes back (yesterday, or the
     * date given; never before the start).
     */
    public function logIncapacity(?EmploymentCategory $from, ?EmploymentCategory $to): void
    {
        if ($from === $to || ($from !== EmploymentCategory::Incapacitated && $to !== EmploymentCategory::Incapacitated)) {
            return;
        }

        $date = filled($this->incapacityDate) ? Carbon::parse($this->incapacityDate)->startOfDay() : null;

        if ($to === EmploymentCategory::Incapacitated) {
            $this->incapacityPeriods()->create([
                'start_date' => ($date ?? today())->toDateString(),
                'previous_category' => $from,
                'started_by' => auth()->id(),
            ]);

            return;
        }

        $open = $this->incapacityPeriods()->whereNull('end_date')->first();

        $open?->update([
            'end_date' => ($date ?? today()->subDay())->max($open->start_date)->toDateString(),
            'next_category' => $to,
            'ended_by' => auth()->id(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<EmployeeCertificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(EmployeeCertificate::class);
    }

    /**
     * Changes the employee made to their own details.
     *
     * @return HasMany<EmployeeChangeRequest, $this>
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(EmployeeChangeRequest::class);
    }

    /**
     * Certificates the employee added themselves.
     *
     * @return HasMany<EmployeeCertificateRequest, $this>
     */
    public function certificateRequests(): HasMany
    {
        return $this->hasMany(EmployeeCertificateRequest::class);
    }

    /**
     * @return HasMany<EmployeeMedicalCheck, $this>
     */
    public function medicalChecks(): HasMany
    {
        return $this->hasMany(EmployeeMedicalCheck::class)->orderByDesc('checked_on')->orderByDesc('id');
    }

    /**
     * In a notice period (opzeggingstermijn): still employed, with the end date set.
     */
    public function isInNotice(): bool
    {
        return $this->isEmployed() && $this->notice_ends_on !== null;
    }

    /**
     * @return HasMany<ParentalLeave, $this>
     */
    public function parentalLeaves(): HasMany
    {
        return $this->hasMany(ParentalLeave::class)->orderByDesc('starts_on');
    }

    /**
     * @return HasOne<EmployeeMedicalCheck, $this>
     */
    public function latestMedicalCheck(): HasOne
    {
        return $this->hasOne(EmployeeMedicalCheck::class)->latestOfMany('checked_on');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isEmployed(): bool
    {
        return $this->left_on === null;
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeEmployed(Builder $query): void
    {
        $query->whereNull('left_on');
    }

    /**
     * When the next visit to the occupational doctor is due: from the latest check, or straight
     * away when there has never been one.
     */
    public function nextMedicalDue(): ?Carbon
    {
        if (! $this->isEmployed()) {
            return null;
        }

        $latest = $this->latestMedicalCheck;

        if ($latest === null) {
            return ($this->employment_date ?? $this->created_at)?->copy()->startOfDay();
        }

        return $latest->next_due_on ?? $latest->checked_on->copy()->addMonths(self::MEDICAL_INTERVAL_MONTHS);
    }

    public function medicalOverdue(): bool
    {
        $due = $this->nextMedicalDue();

        return $due !== null && $due->lte(today());
    }

    public function addressLine(): ?string
    {
        $streetLine = trim(implode(' ', array_filter([$this->street, $this->house_number, $this->addition])));
        $place = trim(implode(' ', array_filter([$this->postal_code, $this->city])));

        return implode(', ', array_filter([$streetLine, $place, $this->country?->localName()])) ?: null;
    }

    /**
     * "Partner · 0470 12 34 56" lines for the emergency contacts that are filled in.
     *
     * @return list<array{name: string, phone: ?string, relation: ?string}>
     */
    public function emergencyContacts(): array
    {
        $contacts = [];

        foreach ([1, 2] as $number) {
            $name = trim($this->{"emergency{$number}_first_name"}.' '.$this->{"emergency{$number}_last_name"});

            if ($name !== '' || filled($this->{"emergency{$number}_phone"})) {
                $contacts[] = ['name' => $name, 'phone' => $this->{"emergency{$number}_phone"}, 'relation' => $this->{"emergency{$number}_relation"}];
            }
        }

        return $contacts;
    }

    /**
     * Belgian national number (rijksregisternummer) in its usual notation: 85.07.30-033.28.
     */
    public static function formatNationalNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);

        return strlen($digits) === 11
            ? substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 2).'-'.substr($digits, 6, 3).'.'.substr($digits, 9, 2)
            : $number;
    }

    /**
     * Checks the Belgian national number: 11 digits whose last two are the mod-97 check
     * (with a leading 2 for people born from 2000 on).
     */
    public static function isValidNationalNumber(string $number): bool
    {
        $digits = preg_replace('/\D/', '', $number);

        if (strlen($digits) !== 11) {
            return false;
        }

        $base = (int) substr($digits, 0, 9);
        $check = (int) substr($digits, 9, 2);

        return 97 - ($base % 97) === $check || 97 - ((2000000000 + $base) % 97) === $check;
    }

    /**
     * IBAN check (ISO 13616, mod 97), e.g. BE68 5390 0754 7034.
     */
    public static function isValidIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }

        $numeric = preg_replace_callback('/[A-Z]/', fn (array $match): string => (string) (ord($match[0]) - 55), substr($iban, 4).substr($iban, 0, 4));
        $remainder = 0;

        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }

    /**
     * The categories on the driver license, in the order of the license.
     *
     * @return list<DriverLicenseCategory>
     */
    public function driverLicenseCategories(): array
    {
        $held = array_map(fn (mixed $value): string => $value instanceof DriverLicenseCategory ? $value->value : (string) $value, (array) $this->driver_license_categories);

        return array_values(array_filter(DriverLicenseCategory::cases(), fn (DriverLicenseCategory $category): bool => in_array($category->value, $held, true)));
    }

    /**
     * The original name of an uploaded copy of the driver license.
     */
    public function driverLicenseFileName(string $path): string
    {
        return (string) (((array) $this->driver_license_file_names)[$path] ?? basename($path));
    }

    public function hasDriverLicense(): bool
    {
        return $this->driverLicenseCategories() !== [] || filled($this->driver_license_number) || filled($this->driver_license_files);
    }

    /**
     * The license is expired, or expires within the warning period of certificates.
     */
    public function driverLicenseExpiresSoon(): bool
    {
        return $this->driver_license_expires_on !== null && $this->driver_license_expires_on->lte(today()->addDays(self::EXPIRY_WARNING_DAYS));
    }

    public static function formatIban(?string $iban): ?string
    {
        return filled($iban) ? trim(chunk_split(strtoupper(preg_replace('/\s+/', '', $iban)), 4, ' ')) : $iban;
    }

    protected static function booted(): void
    {
        // Delete through the models, so their PDFs are removed from the disk too.
        static::deleting(function (self $employee): void {
            $employee->certificates->each->delete();
            $employee->certificateRequests->each->delete();
            $employee->medicalChecks->each->delete();
        });

        static::deleted(fn (self $employee) => Storage::disk(self::DISK)->delete((array) $employee->driver_license_files));

        // Copies of the driver license that were removed in the form are removed from the disk.
        static::updated(function (self $employee): void {
            if ($employee->wasChanged('driver_license_files')) {
                $old = (array) json_decode((string) $employee->getRawOriginal('driver_license_files'), true);
                Storage::disk(self::DISK)->delete(array_values(array_diff($old, (array) $employee->driver_license_files)));
            }
        });

        // The statute "arbeidsongeschikt" is logged per period (see logIncapacity).
        static::created(function (self $employee): void {
            $employee->logIncapacity(null, $employee->employment_category);

            // A login that was completed only for not being an employee (nothing signed) onboards once it is one.
            $user = $employee->user;

            if ($user && $user->onboarding_completed_at !== null && ! $user->isAdmin() && ! OnboardingSignature::query()->where('user_id', $user->id)->whereNotNull('signed_at')->exists()) {
                $user->forceFill(['onboarding_completed_at' => null])->saveQuietly();
            }
        });
        static::updated(function (self $employee): void {
            if ($employee->wasChanged('employment_category')) {
                $from = $employee->getOriginal('employment_category');
                $employee->logIncapacity($from instanceof EmploymentCategory ? $from : EmploymentCategory::tryFrom((string) $from), $employee->employment_category);
            }
        });

        static::saving(function (self $employee): void {
            // Only known categories, in the order of the license.
            $employee->driver_license_categories = array_values(array_map(
                fn (DriverLicenseCategory $category): string => $category->value,
                $employee->driverLicenseCategories(),
            )) ?: null;

            if (filled($employee->national_number)) {
                $employee->national_number = preg_replace('/\D/', '', $employee->national_number);
            }

            if (filled($employee->bank_account)) {
                $employee->bank_account = strtoupper(preg_replace('/\s+/', '', $employee->bank_account));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'national_number' => 'encrypted',
            'bank_account' => 'encrypted',
            'date_of_birth' => 'date',
            'employment_date' => 'date',
            'notice_started_on' => 'date',
            'notice_ends_on' => 'date',
            'left_on' => 'date',
            'welcome_sent_at' => 'datetime',
            'employment_category' => EmploymentCategory::class,
            'contract_term' => ContractTerm::class,
            'education_level' => EducationLevel::class,
            'is_mentor' => 'boolean',
            'is_interim' => 'boolean',
            'driver_license_categories' => 'array',
            'driver_license_number' => 'encrypted',
            'driver_license_expires_on' => 'date',
            'driver_license_files' => 'array',
            'driver_license_file_names' => 'array',
        ];
    }
}
