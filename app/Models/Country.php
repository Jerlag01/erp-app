<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A country (ISO 3166-1 alpha-2 code) with its name in Dutch, French and English.
 */
#[Fillable(['code', 'name'])]
class Country extends Model
{
    public function localName(?string $locale = null): string
    {
        $names = (array) $this->name;

        return $names[$locale ?? app()->getLocale()] ?? $names['en'] ?? $this->code;
    }

    /**
     * Every country by id, named in the current language and sorted by that name, for selects.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::query()->get()
            ->mapWithKeys(fn (self $country): array => [$country->id => $country->localName()])
            ->sort(fn (string $a, string $b): int => strcoll($a, $b))
            ->all();
    }

    public static function belgiumId(): ?int
    {
        return once(fn (): ?int => static::query()->where('code', 'BE')->value('id'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
        ];
    }
}
