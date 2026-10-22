<?php

namespace App\Actions\Timesheets;

use App\Models\User;
use App\Support\Timesheets;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The week of a prestation sheet as PDF, laid out cell for cell like the printed Excel
 * (A4 landscape at 75 %, black and white, print date bottom right).
 */
class TimesheetPdf
{
    /**
     * Excel column widths of the print area (column E is hidden), in characters.
     */
    public const WIDTHS = ['A' => 15.5703125, 'B' => 15.140625, 'C' => 14.7109375, 'D' => 11.7109375, 'F' => 14.7109375, 'G' => 14.7109375, 'H' => 12.140625, 'I' => 14.42578125, 'J' => 70.28515625];

    /**
     * The print scale of the Excel.
     */
    public const SCALE = 0.75;

    public function __construct(protected Timesheets $timesheets) {}

    /**
     * The week as a PDF, in the given language (by default the current one: the reader's).
     */
    public function render(User $user, CarbonInterface $monday, ?string $locale = null): string
    {
        $previous = app()->getLocale();

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        try {
            $week = $this->timesheets->week($user, $monday);

            return Pdf::loadView('timesheets.week-pdf', $this->sheet($this->cells($user, $week)))
                ->setPaper('a4', 'landscape')
                ->setOption('isFontSubsettingEnabled', true)
                ->output();
        } finally {
            app()->setLocale($previous);
        }
    }

    public static function filename(User $user, CarbonInterface $monday): string
    {
        return Str::slug('prestatiefiche '.$user->name.' week '.$monday->isoWeek().' '.$monday->format('Y')).'.pdf';
    }

    /**
     * Column widths in points: Excel characters → pixels (7 px per character + 5) → points, at 75 %.
     *
     * @return array<string, float>
     */
    public static function widths(): array
    {
        return array_map(fn (float $characters): float => round(((int) round($characters * 7 + 5)) * 0.75 * self::SCALE, 2), self::WIDTHS);
    }

    /**
     * Positions every cell text and every border line on the A4 landscape page (842 × 595 pt),
     * centred like the Excel print. Shared edges take the heavier of the two border styles.
     *
     * @param  array<int, array<string, string>>  $cells
     * @return array{texts: list<array<string, mixed>>, lines: list<array<string, float>>}
     */
    public function sheet(array $cells): array
    {
        $layout = require resource_path('timesheets/layout.php');
        $widths = self::widths();
        $columns = array_keys($widths);
        $left = (842 - array_sum($widths)) / 2;
        $top = (595 - array_sum(array_map(fn (array $row): float => $row['height'] * self::SCALE, $layout))) / 2;
        $weight = ['' => 0, 'hair' => 1, 'dotted' => 1, 'thin' => 2, 'dashed' => 2, 'medium' => 3, 'mediumDashed' => 3, 'thick' => 4, 'double' => 4];
        $xs = [];
        $x = $left;
        foreach ($columns as $index => $column) {
            $xs[$index] = $x;
            $x += $widths[$column];
        }
        $xs[count($columns)] = $x;
        // Row 4: "Kilometerstand voertuig" and "Uren" run over three columns.
        $spans = [4 => ['B' => 3, 'F' => 3]];
        $texts = [];
        $horizontal = [];
        $vertical = [];
        $y = $top;

        foreach ($layout as $number => $row) {
            $height = $row['height'] * self::SCALE;

            foreach ($columns as $index => $column) {
                [$bold, $size, $align, $valign, $indent, $borders] = $row['cells'][$column];
                $edges = ['t' => [$y, $index], 'b' => [$y + $height, $index]];

                foreach (['t' => 0, 'b' => 2] as $side => $position) {
                    $key = round($edges[$side][0], 2).'|'.$index;
                    $current = $horizontal[$key] ?? '';
                    if ($weight[$borders[$position]] ?? 2 > ($weight[$current] ?? 0)) {
                        $horizontal[$key] = ($weight[$borders[$position]] ?? 2) > ($weight[$current] ?? 0) ? $borders[$position] : $current;
                    }
                }
                foreach (['l' => [3, $index], 'r' => [1, $index + 1]] as $side => [$position, $boundary]) {
                    $key = $number.'|'.$boundary;
                    $current = $vertical[$key] ?? '';
                    $vertical[$key] = ($weight[$borders[$position]] ?? 2) > ($weight[$current] ?? 0) ? $borders[$position] : $current;
                }

                $text = $cells[$number][$column] ?? '';
                if ($text === '') {
                    continue;
                }

                $span = $spans[$number][$column] ?? 1;
                $texts[] = [
                    'text' => $text,
                    'x' => $xs[$index],
                    'y' => $y,
                    'w' => $xs[$index + $span] - $xs[$index],
                    'h' => $height,
                    'size' => $size * self::SCALE,
                    'bold' => $bold,
                    'align' => $span > 1 ? 'center' : ($align ?: (preg_match('/^-?[\d.,]+$/', $text) ? 'right' : 'left')),
                    'valign' => $valign ?: 'bottom',
                    'indent' => $indent * 9 * self::SCALE,
                ];
            }

            $y += $height;
        }

        $thickness = fn (string $style): float => match ($style) {
            'medium', 'mediumDashed' => 1.5 * self::SCALE,
            'thick', 'double' => 2.25 * self::SCALE,
            'hair', 'dotted' => 0.5 * self::SCALE,
            default => 0.75 * self::SCALE,
        };
        $lines = [];

        foreach ($horizontal as $key => $style) {
            if ($style === '') {
                continue;
            }
            [$lineY, $index] = explode('|', $key);
            $lines[] = ['x' => $xs[$index], 'y' => (float) $lineY - $thickness($style) / 2, 'w' => $xs[$index + 1] - $xs[$index], 'h' => $thickness($style), 'dotted' => in_array($style, ['hair', 'dotted'], true)];
        }

        $rowTops = [];
        $y = $top;
        foreach ($layout as $number => $row) {
            $rowTops[$number] = [$y, $row['height'] * self::SCALE];
            $y += $row['height'] * self::SCALE;
        }

        foreach ($vertical as $key => $style) {
            if ($style === '') {
                continue;
            }
            [$number, $boundary] = explode('|', $key);
            [$rowY, $rowHeight] = $rowTops[(int) $number];
            $lines[] = ['x' => $xs[$boundary] - $thickness($style) / 2, 'y' => $rowY, 'w' => $thickness($style), 'h' => $rowHeight, 'dotted' => in_array($style, ['hair', 'dotted'], true)];
        }

        return ['texts' => $texts, 'lines' => $lines];
    }

    /**
     * The Excel has six lines per day: start, site, three extra sites and the end. More extra sites
     * share the third extra line (imputations and descriptions joined; their km and hours are not
     * counted anyway).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function sixRows(array $rows): array
    {
        $start = $rows[0];
        $end = $rows[count($rows) - 1];
        $extras = array_slice($rows, 2, count($rows) - 3);
        $empty = ['kind' => 'extra', 'km_departure' => null, 'km_arrival' => null, 'arrival' => null, 'departure' => null, 'project_id' => null, 'imputation' => null, 'description' => null];

        if (count($extras) > 3) {
            $rest = array_slice($extras, 2);
            $extras = [$extras[0], $extras[1], [
                ...$empty,
                'imputation' => implode(' / ', array_filter(array_column($rest, 'imputation'))) ?: null,
                'description' => implode(' / ', array_filter(array_column($rest, 'description'))) ?: null,
            ]];
        }

        return [$start, $rows[1], ...array_pad($extras, 3, $empty), $end];
    }

    /**
     * The text of every cell, by row and column, as the Excel shows it.
     *
     * @param  array<string, mixed>  $week
     * @return array<int, array<string, string>>
     */
    public function cells(User $user, array $week): array
    {
        $hours = fn (?float $value): string => number_format((float) $value, 2, ',', '');
        $km = fn (?int $value): string => number_format((int) $value, 0, ',', '.');
        $t = fn (string $key): string => __("erp.pdf.timesheet.{$key}");

        $cells = [
            2 => ['A' => $t('name'), 'D' => $t('plate'), 'F' => $t('week_from'), 'G' => $week['monday']->format('d/m/y'), 'H' => $t('week')],
            3 => ['A' => $user->name, 'D' => (string) $week['plate'], 'F' => $t('until'), 'G' => $week['friday']->format('d/m/y'), 'H' => (string) $week['number']],
            4 => ['B' => $t('odometer'), 'F' => $t('hours'), 'I' => $t('imputation'), 'J' => $t('description')],
            5 => ['A' => $t('date'), 'B' => $t('departure'), 'C' => $t('arrival'), 'D' => $t('total'), 'F' => $t('arrival'), 'G' => $t('departure'), 'H' => $t('total'), 'I' => $t('example'), 'J' => $t('site')],
            6 => ['D' => $t('km'), 'H' => $t('hours'), 'I' => '620/251', 'J' => $t('example_site')],
        ];

        foreach ($week['days'] as $index => $day) {
            $first = 7 + $index * 7;

            foreach (self::sixRows($day['rows']) as $offset => $row) {
                $hoursOfRow = Timesheets::hours($row['arrival'], $row['departure']);
                $cells[$first + $offset] = [
                    'B' => $row['km_departure'] !== null ? $km($row['km_departure']) : '',
                    'C' => $row['km_arrival'] !== null ? $km($row['km_arrival']) : '',
                    'D' => $km(Timesheets::rowKm($row)),
                    'F' => $row['arrival'] !== null ? $hours(Timesheets::decimal($row['arrival'])) : '',
                    'G' => $row['departure'] !== null ? $hours(Timesheets::decimal($row['departure'])) : '',
                    'H' => $hours($hoursOfRow),
                    'I' => (string) $row['imputation'],
                    'J' => (string) $row['description'],
                ];
            }

            // Vacation, absence, training or holiday: noted on the site row.
            if ($day['status'] && blank($cells[$first + 1]['J'])) {
                $cells[$first + 1]['J'] = $day['status']['note'];
            }

            $cells[$first + 3]['A'] = $day['date']->format('d/m/y');
            $cells[$first + 4]['A'] = $day['date']->locale(app()->getLocale())->translatedFormat('l');
            $cells[$first + 6] = [
                'D' => $km($day['km']),
                'F' => $t('lunch'),
                'G' => $day['lunch'] ? $t('yes') : $t('no'),
                'H' => $hours($day['total']),
            ];
        }

        $cells[42] = ['A' => $t('total_colon'), 'B' => $t('carried'), 'D' => $km($week['km']), 'F' => $t('carried'), 'H' => $hours($week['hours'])];
        $cells[43] = ['B' => $t('kilometres'), 'C' => $km($week['km_carried']), 'F' => $t('overtime'), 'G' => $hours($week['overtime_carried']), 'H' => $hours($week['expected'])];
        $cells[44] = ['B' => $t('now'), 'C' => $km($week['km']), 'F' => $t('now'), 'G' => $hours($week['overtime_now']), 'H' => $hours($week['overtime_now'])];
        $cells[45] = ['B' => $t('new'), 'C' => $km($week['km_new']), 'F' => $t('new'), 'G' => $hours($week['overtime_new'])];
        $cells[47] = ['A' => $t('remark')];

        // Five lines for remarks; more notes share the last line.
        $notes = $week['notes'];
        $lines = array_slice($notes, 0, 4);
        if (count($notes) > 4) {
            $lines[] = implode(' · ', array_slice($notes, 4));
        }
        foreach ($lines as $offset => $note) {
            $cells[47 + $offset]['B'] = $note;
        }

        return $cells;
    }
}
