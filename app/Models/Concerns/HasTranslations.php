<?php

namespace App\Models\Concerns;

/**
 * Text stored as JSON keyed by locale ({"nl": ..., "fr": ..., "en": ...}), as in support-app.
 */
trait HasTranslations
{
    public function translate(string $attribute, ?string $locale = null): string
    {
        $values = (array) $this->getAttribute($attribute);
        $locale ??= app()->getLocale();

        return $values[$locale]
            ?? $values[config('app.locale')]
            ?? collect($values)->first(fn (?string $value): bool => filled($value))
            ?? '';
    }
}
