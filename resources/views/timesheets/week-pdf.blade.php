{{--
    The week of a prestation sheet, placed cell for cell like the printed Excel (A4 landscape, 75 %,
    black and white). Positions come from App\Actions\Timesheets\TimesheetPdf::sheet().
--}}
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Prestatiefiche</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #000; }
        .t { position: absolute; white-space: nowrap; overflow: visible; }
        .l { position: absolute; background: #000; }
        .l.dot { background: none; border-top: 0.4pt dotted #000; }
        .print-date { position: absolute; bottom: 22pt; right: 17pt; font-size: 7.5pt; }
    </style>
</head>
<body>
    @foreach ($lines as $line)
        <div @class(['l', 'dot' => $line['dotted']]) style="left: {{ round($line['x'], 2) }}pt; top: {{ round($line['y'], 2) }}pt; width: {{ round($line['w'], 2) }}pt; height: {{ $line['dotted'] ? 0 : round($line['h'], 2) }}pt;"></div>
    @endforeach
    @foreach ($texts as $cell)
        @php
            $lineHeight = $cell['size'] * 1.15;
            $offset = match ($cell['valign']) {
                'center' => ($cell['h'] - $lineHeight) / 2,
                'top' => 1,
                default => $cell['h'] - $lineHeight - 1,
            };
        @endphp
        <div class="t" style="left: {{ round($cell['x'] + 2 + $cell['indent'], 2) }}pt; top: {{ round($cell['y'] + $offset, 2) }}pt; width: {{ round($cell['w'] - 4 - $cell['indent'], 2) }}pt; font-size: {{ round($cell['size'], 2) }}pt; line-height: {{ round($lineHeight, 2) }}pt; text-align: {{ $cell['align'] }}; {{ $cell['bold'] ? 'font-weight: bold;' : '' }}">{{ $cell['text'] }}</div>
    @endforeach
    <div class="print-date">{{ today()->format('d/m/Y') }}</div>
</body>
</html>
