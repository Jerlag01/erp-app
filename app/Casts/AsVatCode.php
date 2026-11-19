<?php

namespace App\Casts;

use App\Models\VatCode;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A column holding a VAT code ("21", "reverse_charge", …), read as the VatCode; in arrays and forms
 * it stays the code.
 *
 * @implements CastsAttributes<VatCode|null, VatCode|string|null>
 */
class AsVatCode implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?VatCode
    {
        return VatCode::forCode($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof VatCode) {
            return $value->code;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof VatCode ? $value->code : ($value === null ? null : (string) $value);
    }
}
