<?php

namespace Database\Factories;

use App\Enums\VatRate;
use App\Models\Client;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'short_name' => strtoupper(fake()->unique()->lexify('??????')),
            'company_name' => fake()->company().' NV',
            'is_private' => false,
            'emails' => [fake()->unique()->safeEmail()],
            'accounting_email' => fake()->unique()->safeEmail(),
            'street' => 'Industrieweg',
            'house_number' => (string) fake()->numberBetween(1, 200),
            'postal_code' => '2800',
            'city' => 'Mechelen',
            'country_id' => fn () => Country::belgiumId(),
            // A valid Belgian VAT number (mod 97).
            'vat_number' => fn (): string => self::belgianVat(),
            'default_vat_rate' => VatRate::TwentyOne,
            'payment_term_days' => 30,
            'locale' => 'nl',
        ];
    }

    public static function belgianVat(): string
    {
        $base = fake()->numberBetween(20000000, 99999999);

        return 'BE0'.str_pad((string) $base, 7, '0', STR_PAD_LEFT).'X';
    }

    public function configure(): static
    {
        // Give the placeholder VAT number a correct check digit pair.
        return $this->afterMaking(function (Client $client): void {
            if (str_ends_with((string) $client->vat_number, 'X')) {
                $digits = substr((string) $client->vat_number, 2, 8);
                $client->vat_number = 'BE'.$digits.str_pad((string) (97 - ((int) $digits % 97)), 2, '0', STR_PAD_LEFT);
            }
        });
    }

    public function private(): static
    {
        return $this->state(['is_private' => true, 'vat_number' => null, 'company_name' => fake()->name()]);
    }
}
