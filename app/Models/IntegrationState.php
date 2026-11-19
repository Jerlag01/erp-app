<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a connection to an outside service has to remember between runs (tokens, the last mail
 * read, the last document fetched). Kept apart from the settings, which the settings form
 * rewrites as a whole; the value is encrypted.
 */
#[Fillable(['key', 'value'])]
class IntegrationState extends Model
{
    public static function read(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->first()?->value ?? $default;
    }

    public static function write(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'encrypted:array'];
    }
}
