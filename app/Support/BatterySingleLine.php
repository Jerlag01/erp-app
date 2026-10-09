<?php

namespace App\Support;

/**
 * The single-line diagram ("Eendraadschema") of the new batteries as SVG: the power board on top
 * with, per battery, a positive and a negative cable through a fuse down to the battery, labelled
 * with cable type, section, length, cables per pole and fuse rating and type.
 */
class BatterySingleLine
{
    protected const COLUMN = 210;

    /**
     * @param  list<array{position: int, id_number?: ?string, cable_type?: ?string, cable_length_m?: mixed, cables_positive?: mixed, cables_negative?: mixed, cable_section?: ?string, fuse_amperage?: ?string, fuse_type?: ?string}>  $batteries
     */
    public static function svg(array $batteries, string $board = 'Krachtbord'): string
    {
        $count = max(1, count($batteries));
        $width = 40 + $count * self::COLUMN;
        $height = 440;
        $e = fn (mixed $text): string => htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $value = fn (mixed $text, string $suffix = ''): string => filled($text) ? self::trimDecimals((string) $text).$suffix : '…'.$suffix;

        $svg = [];
        $svg[] = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\" font-family=\"DejaVu Sans, Arial, sans-serif\">";
        $svg[] = "<rect x=\"0\" y=\"0\" width=\"{$width}\" height=\"{$height}\" fill=\"#ffffff\"/>";
        $svg[] = '<rect x="20" y="20" width="'.($width - 40).'" height="46" fill="#e2e8f0" stroke="#0f172a" stroke-width="2"/>';
        $svg[] = '<text x="'.($width / 2).'" y="50" font-size="18" font-weight="bold" text-anchor="middle" fill="#0f172a">'.$e($board).'</text>';

        foreach (array_values($batteries) as $index => $battery) {
            $center = 20 + $index * self::COLUMN + self::COLUMN / 2;
            $plus = $center - 40;
            $minus = $center + 40;

            foreach ([[$plus, '#c62828', '+'], [$minus, '#1e3a8a', '−']] as [$x, $color, $sign]) {
                $svg[] = "<line x1=\"{$x}\" y1=\"66\" x2=\"{$x}\" y2=\"340\" stroke=\"{$color}\" stroke-width=\"3\"/>";
                // Fuse: a box with the conductor running through.
                $svg[] = '<rect x="'.($x - 9).'" y="92" width="18" height="40" fill="#ffffff" stroke="'.$color.'" stroke-width="2.5"/>';
                $svg[] = "<line x1=\"{$x}\" y1=\"92\" x2=\"{$x}\" y2=\"132\" stroke=\"{$color}\" stroke-width=\"1.5\"/>";
                $svg[] = '<text x="'.($x + ($sign === '+' ? -14 : 14)).'" y="84" font-size="15" font-weight="bold" text-anchor="middle" fill="'.$color.'">'.$sign.'</text>';
            }

            $fuse = trim($value($battery['fuse_amperage'] ?? null, ' A').' '.($battery['fuse_type'] ?? ''));
            $svg[] = '<text x="'.$center.'" y="117" font-size="11" text-anchor="middle" fill="#0f172a">'.$e($fuse).'</text>';

            $lines = [
                $battery['cable_type'] ?? null ? (string) $battery['cable_type'] : '…',
                $value($battery['cable_section'] ?? null, ' mm²'),
                $value($battery['cable_length_m'] ?? null, ' m'),
                '+'.($battery['cables_positive'] ?? '…').' / −'.($battery['cables_negative'] ?? '…'),
            ];

            foreach ($lines as $row => $text) {
                $svg[] = '<text x="'.$center.'" y="'.(196 + $row * 18).'" font-size="12" text-anchor="middle" fill="#0f172a">'.$e($text).'</text>';
            }

            $svg[] = '<rect x="'.($center - 70).'" y="340" width="140" height="64" rx="4" fill="#f8fafc" stroke="#0f172a" stroke-width="2"/>';
            $svg[] = '<text x="'.$center.'" y="368" font-size="15" font-weight="bold" text-anchor="middle" fill="#0f172a">BATT '.(int) $battery['position'].'</text>';

            if (filled($battery['id_number'] ?? null)) {
                $svg[] = '<text x="'.$center.'" y="390" font-size="11" text-anchor="middle" fill="#475569">ID '.$e($battery['id_number']).'</text>';
            }
        }

        $svg[] = '</svg>';

        return implode('', $svg);
    }

    /**
     * "2.50" → "2.5", "3.00" → "3", "70" stays "70".
     */
    public static function trimDecimals(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    public static function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
