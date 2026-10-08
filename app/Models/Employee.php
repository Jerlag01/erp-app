<?php

namespace App\Models;

use App\Enums\ContractTerm;
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

/**
 * An employee in the employee register, linked to a login account (users). The national number
 * and bank account are encrypted in the database. When an employee leaves, the record stays and
 * only the login is switched off.
 */
#[Fillable([
    'user_id', 'first_name', 'last_name',
    'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id',
    'private_email', 'private_phone', 'national_number', 'bank_account',
    'place_of_birth', 'date_of_birth', 'employment_date', 'mother_tongue',
    'employment_category', 'contract_term', 'education_level',
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

    public const MOTHER_TONGUES = ['nl', 'fr', 'de', 'en', 'other'];

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
     * @return HasMany<EmployeeMedicalCheck, $this>
     */
    public function medicalChecks(): HasMany
    {
        return $this->hasMany(EmployeeMedicalCheck::class)->orderByDesc('checked_on')->orderByDesc('id');
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

    public static function formatIban(?string $iban): ?string
    {
        return filled($iban) ? trim(chunk_split(strtoupper(preg_replace('/\s+/', '', $iban)), 4, ' ')) : $iban;
    }

    protected static function booted(): void
    {
        // Delete through the models, so their PDFs are removed from the disk too.
        static::deleting(function (self $employee): void {
            $employee->certificates->each->delete();
            $employee->medicalChecks->each->delete();
        });

        static::saving(function (self $employee): void {
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
            'left_on' => 'date',
            'welcome_sent_at' => 'datetime',
            'employment_category' => EmploymentCategory::class,
            'contract_term' => ContractTerm::class,
            'education_level' => EducationLevel::class,
        ];
    }
}
