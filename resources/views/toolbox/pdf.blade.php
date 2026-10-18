{{-- The attendance list of a toolbox meeting, like the paper form (bilingual Dutch / French). --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 18mm 16mm; }
        body { font-family: 'DejaVu Serif', serif; font-size: 11pt; color: #000; }
        h1 { text-align: center; font-size: 13pt; font-style: italic; font-weight: bold; margin: 0 0 2mm; letter-spacing: .3pt; }
        h1 .cap { font-size: 17pt; }
        .title { text-align: center; font-size: 12pt; font-weight: bold; margin: 0 0 3mm; }
        hr { border: 0; border-top: 1px solid #000; margin: 0 0 6mm; }
        p { margin: 0 0 4mm; }
        .label { text-transform: uppercase; }
        .subject { font-weight: bold; text-decoration: underline; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 3mm; }
        th, td { border: 1px solid #000; padding: 1.2mm 2mm; vertical-align: middle; }
        th { font-size: 11pt; text-align: center; }
        td.name { font-family: 'DejaVu Sans', sans-serif; font-weight: bold; font-size: 9pt; width: 27%; }
        td.sign { width: 23%; height: 11mm; text-align: center; }
        td.sign img { max-height: 10mm; max-width: 38mm; }
        .lines p { margin: 6mm 0 0; }
    </style>
</head>
<body>
    {{-- Small capitals like the paper form (dompdf has no font-variant: small-caps). --}}
    <h1><span class="cap">T</span>OOLBOX <span class="cap">M</span>EETING</h1>
    <p class="title">{{ $meeting->title }}</p>
    <hr>

    <p><span class="label">Werf / Atelier / Bureel - Chantier / Atelier / Bureau:</span><br>{{ $meeting->location ?: \App\Models\ToolboxMeeting::companyName() }}</p>

    <p><span class="label">Datum / Date :</span>&nbsp; {{ $meeting->monthLabel('nl') }}&nbsp; {{ $meeting->monthLabel('fr') }}</p>

    <p><span class="label">Instructies door / Instructions par :</span><br>{{ $meeting->instructorNames() }}</p>

    <p class="subject">NL {{ $meeting->subject_nl }}</p>
    <p class="subject" style="margin-bottom: 5mm;">FR {{ $meeting->subject_fr }}</p>

    <p class="label" style="margin-bottom: 0;">Deelnemers meeting / Participants meeting</p>
    <table>
        <thead>
            <tr>
                <th>NAAM/NOM</th>
                <th>Paraaf/Parafe</th>
                <th>NAAM/NOM</th>
                <th>Paraaf/Parafe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $pair)
                <tr>
                    @foreach ([0, 1] as $column)
                        <td class="name">{{ $pair[$column]['name'] ?? '' }}</td>
                        <td class="sign">
                            @if (! empty($pair[$column]['signature']))
                                <img src="{{ $pair[$column]['signature'] }}" alt="">
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="lines">
        <p class="label" style="margin-top: 7mm;">Opmerkingen werknemers - Remarques travailleurs</p>
        <p>1.______________________________________________________________</p>
        <p>2.______________________________________________________________</p>
        <p>3.______________________________________________________________</p>
    </div>
</body>
</html>
