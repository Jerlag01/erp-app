<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Draws a written signature from the pen strokes of the mobile app as a PNG (dark ink on a transparent
 * background, like the signature pad of the web pages), as a data URL.
 */
class SignatureImage
{
    public const MAX_POINTS = 6000;

    /** Pixels per point of the drawing area on the phone, for a sharp image. */
    public const SCALE = 2;

    /**
     * @param  list<list<array{0: float|int, 1: float|int}>>  $strokes  per stroke the points [x, y] in the drawing area
     */
    public static function fromStrokes(array $strokes, int $width, int $height): string
    {
        $width = max(50, min(1200, $width));
        $height = max(30, min(600, $height));
        $points = array_sum(array_map('count', $strokes));

        if ($points < 2 || $points > self::MAX_POINTS) {
            throw new InvalidArgumentException('A signature needs between 2 and '.self::MAX_POINTS.' points.');
        }

        $image = imagecreatetruecolor($width * self::SCALE, $height * self::SCALE);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagealphablending($image, true);
        $ink = imagecolorallocate($image, 15, 23, 42);
        $pen = 3 * self::SCALE;
        imagesetthickness($image, $pen);

        $point = fn (mixed $point): array => [
            (int) round(max(0, min($width, (float) ($point[0] ?? 0))) * self::SCALE),
            (int) round(max(0, min($height, (float) ($point[1] ?? 0))) * self::SCALE),
        ];

        foreach ($strokes as $stroke) {
            $previous = null;

            foreach ((array) $stroke as $raw) {
                [$x, $y] = $point($raw);
                // Round ends and joints.
                imagefilledellipse($image, $x, $y, $pen, $pen, $ink);

                if ($previous) {
                    imageline($image, $previous[0], $previous[1], $x, $y, $ink);
                }

                $previous = [$x, $y];
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
