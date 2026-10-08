<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * One tab per language for text stored as JSON ({"nl": ..., "fr": ..., "en": ...}), as in support-app.
 * The default language is required.
 */
class TranslatableField
{
    /**
     * @param  Closure(string $statePath): Field  $makeField
     */
    public static function make(string $name, string $label, Closure $makeField, bool $required = true): Tabs
    {
        return Tabs::make($label)
            ->columnSpanFull()
            ->tabs(collect(config('app.locales'))
                ->map(fn (string $language, string $locale): Tab => Tab::make($language)
                    ->schema([
                        $makeField("{$name}.{$locale}")
                            ->label("{$label} ({$locale})")
                            ->required($required && $locale === config('app.locale')),
                    ]))
                ->values()
                ->all());
    }
}
