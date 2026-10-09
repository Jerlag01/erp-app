@extends('batteries.pdf.layout', ['landscape' => true])

@section('title', 'Plattegrond')

@section('content')
    <table class="w plain">
        <tr>
            <td><h1>Plattegrond</h1></td>
            <td class="num">Dossier <strong>{{ $dossier->reference() }}</strong> · Cow code <strong>{{ $dossier->cowCode() }}</strong> · lokaal {{ $dossier->new_room }}</td>
        </tr>
    </table>

    @if ($image = $dossier->floorPlanDataUri())
        <div class="center" style="border: 1px solid #94a3b8;">
            <img src="{{ $image }}" style="max-width: 100%; max-height: 160mm;" alt="">
        </div>
    @else
        <p class="muted">Nog geen plattegrond getekend.</p>
    @endif
@endsection
