<?php

namespace Database\Factories;

use App\Models\CertificateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateType>
 */
class CertificateTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'name_nl' => $name,
            'name_fr' => $name,
            'name_en' => $name,
            'overview' => CertificateType::OVERVIEW_HELD,
            'shows_validity' => true,
            'validity_months' => null,
            'kind' => null,
            'sort' => 500,
            'is_active' => true,
        ];
    }
}
