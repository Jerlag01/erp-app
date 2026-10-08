<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;

/**
 * The photo upload zone shared by the incident form and the idea/complaint box: any common
 * picture format (also iPhone HEIC), up to 20 photos of 12 MB, stored on the private disk.
 * SVG is left out on purpose: it can carry script.
 */
class PrivatePhotos
{
    public const DISK = 'local';

    public const TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif', 'image/avif', 'image/bmp', 'image/tiff'];

    /**
     * 12 MB, the limit of Livewire's temporary uploads.
     */
    public const MAX_KILOBYTES = 12288;

    public const MAX_FILES = 20;

    public static function upload(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->multiple()
            ->maxFiles(self::MAX_FILES)
            ->acceptedFileTypes(self::TYPES)
            ->maxSize(self::MAX_KILOBYTES)
            ->disk(self::DISK)
            ->directory(fn (): string => $directory.'/'.now()->format('Y/m'))
            ->visibility('private')
            ->panelLayout('grid');
    }
}
