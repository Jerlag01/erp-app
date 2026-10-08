<?php

namespace App\Models\Concerns;

/**
 * Street, postal code, city and country columns, shown as one line.
 */
trait HasAddress
{
    public function addressLine(): ?string
    {
        $place = trim(implode(' ', array_filter([$this->postal_code, $this->city])));

        return implode(', ', array_filter([$this->street, $place, $this->country])) ?: null;
    }

    public function mapsUrl(): ?string
    {
        $address = $this->addressLine();

        return $address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($address) : null;
    }
}
