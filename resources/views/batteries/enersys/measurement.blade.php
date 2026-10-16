@extends('batteries.pdf.layout')

@section('title', 'Meetrapport')

{{--
    The measurement report of an Enersys dossier, like the sheet "Meetrapport" of "12V enersys": two batteries
    per page, per battery the float voltage and every block disconnected ("Afgeschakeld") and floating
    ("In vlotting"), with the totals. With more than 6 blocks per battery both columns share one table, so
    two batteries still fit on a page.
--}}
@php
    $site = $dossier->project?->workSite;
    $blocks = max(1, (int) $dossier->cells_per_battery);
    $date = $dossier->measured_on ?? $dossier->execution_date;
    $groups = $dossier->batteries->chunk(2);
    $compact = $blocks > 6;
    $volt = fn ($value) => is_numeric($value) ? number_format((float) $value, 2, ',', '') : '';
    $total = function (array $values) use ($blocks): string {
        $numbers = array_filter(array_slice(array_values($values), 0, $blocks), 'is_numeric');

        return $numbers === [] ? '' : number_format(array_sum($numbers), 2, ',', '').' V';
    };
@endphp

@section('styles')
    .blocks td { border: 1px solid #94a3b8; padding: 1px 5px; font-size: 8pt; height: 11pt; }
    .blocks .head td { border: 0; padding: 2px 0; }
@endsection

@section('content')
    @foreach ($groups as $group)
        <h1>Meetrapport</h1>
        <div class="muted">vóór de indienstname van de batterij</div>

        <table class="w plain" style="margin-top: 6px;">
            <tr>
                <td style="width: 45%;"><strong>{{ $dossier->cowCode() }}</strong></td>
                <td>Dossier: <strong>{{ $dossier->reference() }}</strong></td>
                <td>Temp. {{ $dossier->average_temperature !== null ? number_format((float) $dossier->average_temperature, 1, ',', '') : '….' }} +/- °C</td>
            </tr>
            <tr>
                <td>{{ trim(($site?->street ?? '').', '.($site?->house_number ?? ''), ', ') }}</td>
                <td colspan="2">{{ trim(($site?->postal_code ?? '').' '.($site?->city ?? '')) }}</td>
            </tr>
            <tr><td colspan="3">Type: {{ $dossier->brand }} {{ $dossier->typeName() }} &nbsp; · &nbsp; {{ $dossier->new_count }} batterijen × {{ $blocks }} blokken</td></tr>
        </table>

        @if ($compact)
            <table class="w" style="margin-top: 10px;">
                <tr>
                    @foreach ($group as $battery)
                        <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                            <table class="w blocks">
                                <tr class="head"><td colspan="3"><strong>Nieuw</strong> · Batterij id: <strong>{{ $battery->id_number }}</strong> ({{ $battery->label() }})</td></tr>
                                <tr class="head"><td colspan="3">Vlotspanning = <strong>{{ $volt($battery->float_voltage) }}</strong> V</td></tr>
                                <tr><td class="center" style="width: 16%;"></td><td class="center">(Afgeschakeld)</td><td class="center">(In vlotting)</td></tr>
                                @for ($block = 1; $block <= $blocks; $block++)
                                    <tr><td class="center">{{ $block }}</td><td class="num">{{ $volt(((array) $battery->cell_voltages)[$block - 1] ?? null) }} V</td><td class="num">{{ $volt(((array) $battery->floating_voltages)[$block - 1] ?? null) }} V</td></tr>
                                @endfor
                                <tr><td class="center">Totaal</td><td class="num bold">{{ $total((array) $battery->cell_voltages) }}</td><td class="num bold">{{ $total((array) $battery->floating_voltages) }}</td></tr>
                            </table>
                        </td>
                    @endforeach
                    @if ($group->count() < 2)<td style="width: 50%;"></td>@endif
                </tr>
            </table>
        @else
        @foreach (['cell_voltages' => '(Afgeschakeld)', 'floating_voltages' => '(In vlotting)'] as $field => $title)
            <table class="w" style="margin-top: 10px;">
                <tr>
                    @foreach ($group as $battery)
                        <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                            <table class="w blocks">
                                <tr class="head"><td colspan="2"><strong>Nieuw</strong> <span class="muted">{{ $title }}</span></td></tr>
                                <tr class="head"><td colspan="2">Batterij id: <strong>{{ $battery->id_number }}</strong> ({{ $battery->label() }})</td></tr>
                                @if ($field === 'cell_voltages')
                                    <tr class="head"><td colspan="2">Vlotspanning = <strong>{{ $volt($battery->float_voltage) }}</strong> V</td></tr>
                                @endif
                                @for ($block = 1; $block <= $blocks; $block++)
                                    <tr><td style="width: 20%;" class="center">{{ $block }}</td><td class="num">{{ $volt(((array) $battery->{$field})[$block - 1] ?? null) }} V</td></tr>
                                @endfor
                                <tr class="head"><td colspan="2">Totale batterijspanning = <strong>{{ $total((array) $battery->{$field}) }}</strong></td></tr>
                            </table>
                        </td>
                    @endforeach
                    @if ($group->count() < 2)<td style="width: 50%;"></td>@endif
                </tr>
            </table>
        @endforeach
        @endif

        <table class="w nobreak" style="margin-top: 10px;">
            <tr>
                <td style="width: 48%; padding-right: 10px;"><span class="label">Meting uitgevoerd op :</span><div class="box" style="height: 34px;">{{ $date?->format('d/m/Y') }}</div></td>
                <td><span class="label">Voor {{ $dossier->supplier_location }}:</span><div class="box" style="height: auto; min-height: 34px;">@include('batteries.pdf.signature', ['document' => 'measurement', 'height' => 30])</div></td>
            </tr>
        </table>

        @if (! $loop->last)<div class="page-break"></div>@endif
    @endforeach
@endsection
