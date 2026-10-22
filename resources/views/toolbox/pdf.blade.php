{{-- The attendance list of a toolbox meeting, like the paper form, in the reader's language (A4 landscape). The subject is shown in Dutch and French, as entered. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm 16mm 12mm; }
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
        td.name { font-family: 'DejaVu Sans', sans-serif; font-weight: bold; font-size: 9pt; width: 17%; }
        td.sign { width: 16.33%; height: 11mm; text-align: center; }
        td.sign img { max-height: 10mm; max-width: 38mm; }
        .lines p { margin: 6mm 0 0; }
    </style>
</head>
<body>
    {{-- Small capitals like the paper form (dompdf has no font-variant: small-caps). --}}
    <h1><span class="cap">T</span>OOLBOX <span class="cap">M</span>EETING</h1>
    <p class="title">{{ $meeting->title }}</p>
    <hr>

    <p><span class="label">{{ __('erp.pdf.toolbox.site') }}:</span><br>{{ $meeting->location ?: \App\Models\ToolboxMeeting::companyName() }}</p>

    <p><span class="label">{{ __('erp.pdf.toolbox.date') }}:</span>&nbsp; {{ $meeting->monthLabel(app()->getLocale()) }}</p>

    <p><span class="label">{{ __('erp.pdf.toolbox.instructions_by') }}:</span><br>{{ $meeting->instructorNames() }}</p>

    <p class="label" style="margin-bottom: 1mm;">{{ __('erp.pdf.toolbox.subject') }}:</p>
    <p class="subject">NL {{ $meeting->subject_nl }}</p>
    <p class="subject" style="margin-bottom: 5mm;">FR {{ $meeting->subject_fr }}</p>

    <p class="label" style="margin-bottom: 0;">{{ __('erp.pdf.toolbox.participants') }}</p>
    <table>
        <thead>
            <tr>
                @foreach ([0, 1, 2] as $column)
                    <th>{{ mb_strtoupper(__('erp.pdf.toolbox.name')) }}</th>
                    <th>{{ __('erp.pdf.toolbox.initials') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $pair)
                <tr>
                    @foreach ([0, 1, 2] as $column)
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
        <p class="label" style="margin-top: 7mm;">{{ __('erp.pdf.toolbox.remarks') }}</p>
        <p>1.________________________________________________________________________________________________</p>
        <p>2.________________________________________________________________________________________________</p>
        <p>3.________________________________________________________________________________________________</p>
    </div>
</body>
</html>
