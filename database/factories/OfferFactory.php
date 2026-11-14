<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'offer_date' => '2026-10-30',
            'subject' => 'Omvormer Rodin 10 sites',
            'hourly_rate' => 68.64,
            'hours_per_day' => 8,
        ];
    }
}
