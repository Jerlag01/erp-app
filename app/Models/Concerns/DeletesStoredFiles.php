<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Removes uploaded files from the public disk when they are replaced or when
 * the model is deleted. Models list their file attributes in storedFiles().
 */
trait DeletesStoredFiles
{
    public static function bootDeletesStoredFiles(): void
    {
        static::updated(function (self $model): void {
            foreach ($model->storedFiles() as $attribute) {
                if ($model->wasChanged($attribute)) {
                    $old = (array) $model->castOriginalFile($attribute);
                    $new = (array) $model->getAttribute($attribute);
                    static::deleteFiles(array_diff($old, $new));
                }
            }
        });

        static::deleted(function (self $model): void {
            foreach ($model->storedFiles() as $attribute) {
                static::deleteFiles((array) $model->getAttribute($attribute));
            }
        });
    }

    /**
     * @return list<string>
     */
    abstract public function storedFiles(): array;

    protected function castOriginalFile(string $attribute): mixed
    {
        $original = $this->getRawOriginal($attribute);

        return $this->hasCast($attribute, ['array', 'json']) && is_string($original)
            ? json_decode($original, true)
            : $original;
    }

    /**
     * @param  array<int, mixed>  $paths
     */
    protected static function deleteFiles(array $paths): void
    {
        $paths = array_filter($paths, fn (mixed $path): bool => is_string($path) && $path !== '');

        if ($paths !== []) {
            Storage::disk('public')->delete(array_values($paths));
        }
    }
}
