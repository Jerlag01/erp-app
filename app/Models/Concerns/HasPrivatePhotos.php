<?php

namespace App\Models\Concerns;

use App\Support\PrivatePhotos;
use Illuminate\Support\Facades\Storage;

/**
 * Photos in a "photos" JSON column on the private disk: removed with the record,
 * and embeddable in a PDF as data URI.
 */
trait HasPrivatePhotos
{
    public static function bootHasPrivatePhotos(): void
    {
        static::deleted(function (self $model): void {
            Storage::disk(PrivatePhotos::DISK)->delete(array_values(array_filter((array) $model->photos)));
        });
    }

    /**
     * A photo as a data URI for the PDF; formats dompdf cannot draw (e.g. HEIC) return null.
     */
    public function photoDataUri(string $path): ?string
    {
        $disk = Storage::disk(PrivatePhotos::DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path);

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    public function photoPath(int $index): ?string
    {
        return array_values((array) $this->photos)[$index] ?? null;
    }
}
