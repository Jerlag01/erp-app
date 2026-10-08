<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('erp.incidents.pdf_title') }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; }
        .incident { page-break-after: always; }
        .incident:last-child { page-break-after: auto; }
        .header { border-bottom: 3px solid {{ settings()->color('accent') }}; padding-bottom: 8px; margin-bottom: 14px; }
        .site { font-size: 9pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        h1 { font-size: 17pt; margin: 4px 0 0; color: {{ settings()->color('primary') }}; }
        .type { display: inline-block; padding: 3px 9px; border-radius: 4px; font-weight: bold; color: #fff; background: {{ settings()->color('primary') }}; }
        .type.accident { background: #dc2626; }
        .type.near_miss { background: #d97706; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.meta th { text-align: left; width: 32%; padding: 5px 8px; background: #f1f5f9; font-weight: bold; vertical-align: top; }
        table.meta td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        h2 { font-size: 11pt; margin: 16px 0 6px; color: {{ settings()->color('primary') }}; }
        .description { white-space: pre-wrap; line-height: 1.45; border: 1px solid #e2e8f0; padding: 8px 10px; }
        .photo { margin: 0 0 10px; text-align: center; page-break-inside: avoid; }
        .photo img { max-width: 100%; max-height: 120mm; }
        .muted { color: #64748b; font-size: 9pt; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8pt; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">{{ settings()->siteName() }} · {{ __('erp.incidents.pdf_generated', ['date' => now()->format('d/m/Y H:i')]) }}</div>

    @foreach ($incidents as $incident)
        <div class="incident">
            <div class="header">
                <div class="site">{{ settings()->siteName() }} · {{ __('erp.incidents.pdf_title') }} #{{ $incident->id }}</div>
                <h1>{{ $incident->reporter_name }}</h1>
                <span class="type {{ $incident->type->value }}">{{ $incident->type->getLabel() }}</span>
            </div>

            <table class="meta">
                <tr><th>{{ __('erp.incidents.reported_at') }}</th><td>{{ $incident->reported_at->format('d/m/Y H:i') }}</td></tr>
                <tr><th>{{ __('erp.incidents.reporter') }}</th><td>{{ $incident->reporter_name }}@if ($incident->user?->email) · {{ $incident->user->email }}@endif</td></tr>
                <tr><th>{{ __('erp.incidents.place') }}</th><td>{{ $incident->placeName() ?? '—' }}@if ($incident->location_details)<br><span class="muted">{{ $incident->location_details }}</span>@endif</td></tr>
                <tr><th>{{ __('erp.incidents.other_victims') }}</th><td>{{ $incident->other_victims ? __('erp.incidents.yes') : __('erp.incidents.no') }}@if ($incident->other_victims_details)<br>{{ $incident->other_victims_details }}@endif</td></tr>
                <tr><th>{{ __('erp.incidents.material_damage') }}</th><td>{{ $incident->material_damage ? __('erp.incidents.yes') : __('erp.incidents.no') }}@if ($incident->material_damage_details)<br>{{ $incident->material_damage_details }}@endif</td></tr>
                <tr><th>{{ __('erp.fields.status') }}</th><td>{{ $incident->status->getLabel() }}@if ($incident->handler) · {{ $incident->handler->name }}@endif</td></tr>
            </table>

            <h2>{{ __('erp.incidents.description') }}</h2>
            <div class="description">{{ $incident->description }}</div>

            @if (filled($incident->follow_up))
                <h2>{{ __('erp.incidents.follow_up') }}</h2>
                <div class="description">{{ $incident->follow_up }}</div>
            @endif

            @if (filled($incident->photos))
                <h2>{{ __('erp.incidents.photos') }} ({{ count($incident->photos) }})</h2>
                @foreach ($incident->photos as $path)
                    @if ($dataUri = $incident->photoDataUri($path))
                        <div class="photo"><img src="{{ $dataUri }}" alt=""></div>
                    @else
                        <p class="muted">{{ __('erp.incidents.photo_not_in_pdf', ['name' => basename($path)]) }}</p>
                    @endif
                @endforeach
            @endif
        </div>
    @endforeach
</body>
</html>
