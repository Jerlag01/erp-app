<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The images on the battery documents: the letterhead used as full-page background of the
 * delivery note, measurement report and shipping note, and the client header of the visual
 * inspection report. An uploaded image in Settings > Batteries replaces the default.
 */
class BatteryTemplates
{
    public const DEFAULT_BACKGROUND = 'pdf/battery-background.jpg';

    public const DEFAULT_INSPECTION_HEADER = 'pdf/battery-inspection-header.png';

    public static function backgroundDataUri(): ?string
    {
        return self::dataUri(settings()->get('batteries.background'), self::DEFAULT_BACKGROUND);
    }

    public static function inspectionHeaderDataUri(): ?string
    {
        return self::dataUri(settings()->get('batteries.inspection_header'), self::DEFAULT_INSPECTION_HEADER);
    }

    protected static function dataUri(?string $uploaded, string $default): ?string
    {
        if (filled($uploaded) && Storage::disk('public')->exists($uploaded)) {
            return 'data:'.Storage::disk('public')->mimeType($uploaded).';base64,'.base64_encode(Storage::disk('public')->get($uploaded));
        }

        $path = resource_path($default);

        return is_file($path) ? 'data:'.mime_content_type($path).';base64,'.base64_encode((string) file_get_contents($path)) : null;
    }

    /**
     * A multi-line settings text as lines.
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text) ?: []), 'filled'));
    }
}
