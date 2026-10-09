@extends('batteries.pdf.layout')

@section('title', 'Meetrapport')

@php
    $cells = max(1, (int) $dossier->cells_per_battery);
    $density = $dossier->new_technology?->hasDensity();
    $date = $dossier->measured_on ?? $dossier->execution_date;
    $groups = $dossier->batteries->chunk(3);
    $volt = fn (?float $value) => $value === null ? '' : number_format($value, 3, ',', '');
@endphp

@section('styles')
    .cells td, .cells th { border: 1px solid #94a3b8; padding: 1px 4px; font-size: 7.5pt; height: 11pt; }
    .cells th { background: #e2e8f0; }
@endsection

@section('content')
    @foreach ($groups as $group)
        <h1>Meetrapport</h1>
        <div class="muted">vóór de indienstname van de batterij</div>

        <table class="w plain" style="margin-top: 6px;">
            <tr>
                <td style="width: 55%;"><strong>STATIONAIRE LOODBATTERIJEN {{ $dossier->new_technology?->value }}</strong></td>
                <td>Op Datum Van : <strong>{{ $date?->locale('nl')->translatedFormat('d F Y') }}</strong></td>
            </tr>
            <tr><td>Montage uitgevoerd door: {{ $dossier->supplier_location }}</td><td>Dossier: <strong>{{ $dossier->reference() }}</strong></td></tr>
            <tr><td>Type: {{ $dossier->batteryModel?->name }}</td><td>Cow Code: <strong>{{ $dossier->cowCode() }}</strong></td></tr>
            <tr><td>Aantal Elementen {{ $dossier->new_count }} X {{ $cells }}</td><td>Gemiddelde temperatuur: {{ $dossier->average_temperature !== null ? number_format((float) $dossier->average_temperature, 1, ',', '').' °C' : '…… °C' }}</td></tr>
        </table>

        <table class="w" style="margin-top: 8px;">
            <tr>
                @foreach ($group as $battery)
                    <td style="width: 33%; vertical-align: top; padding-right: 6px;">
                        <table class="w cells">
                            <tr><th class="center">{{ $battery->label() }}.</th><th class="center">V / V</th>@if ($density)<th class="center">d kg/l</th>@endif</tr>
                            @for ($cell = 1; $cell <= $cells; $cell++)
                                <tr>
                                    <td class="center">{{ $cell }}</td>
                                    <td class="num">{{ $volt($battery->voltage($cell)) }}</td>
                                    @if ($density)<td class="num">{{ $battery->density($cell) !== null ? number_format($battery->density($cell), 3, ',', '') : '' }}</td>@endif
                                </tr>
                            @endfor
                            <tr><td colspan="{{ $density ? 3 : 2 }}"><strong>Eindspanning {{ $battery->label() }}.: {{ $battery->endVoltage($cells) !== null ? number_format($battery->endVoltage($cells), 2, ',', '').' V' : '' }}</strong></td></tr>
                        </table>
                        @if ($battery->id_number)<div class="muted" style="font-size: 7pt;">ID {{ $battery->id_number }}</div>@endif
                    </td>
                @endforeach
                @for ($empty = $group->count(); $empty < 3; $empty++)<td style="width: 33%;"></td>@endfor
            </tr>
        </table>

        <table class="w nobreak" style="margin-top: 10px;">
            <tr>
                <td style="width: 48%; padding-right: 10px;"><span class="label">Meting uitgevoerd op :</span><div class="box" style="height: 34px;">{{ $date?->format('d/m/Y') }}</div></td>
                <td><span class="label">Voor {{ $dossier->supplier_location }}</span><div class="box" style="height: 34px;"></div></td>
            </tr>
        </table>

        @if (! $loop->last)<div class="page-break"></div>@endif
    @endforeach
@endsection
