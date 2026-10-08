<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('erp.suggestions.title') }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; }
        .entry { page-break-after: always; }
        .entry:last-child { page-break-after: auto; }
        .header { border-bottom: 3px solid {{ settings()->color('accent') }}; padding-bottom: 8px; margin-bottom: 14px; }
        .site { font-size: 9pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        h1 { font-size: 17pt; margin: 4px 0 0; color: {{ settings()->color('primary') }}; }
        .type { display: inline-block; padding: 3px 9px; border-radius: 4px; font-weight: bold; color: #fff; background: {{ settings()->color('primary') }}; }
        .type.idea { background: #16a34a; }
        .type.complaint { background: #dc2626; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.meta th { text-align: left; width: 32%; padding: 5px 8px; background: #f1f5f9; font-weight: bold; vertical-align: top; }
        table.meta td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        h2 { font-size: 11pt; margin: 16px 0 6px; color: {{ settings()->color('primary') }}; }
        .text { white-space: pre-wrap; line-height: 1.45; border: 1px solid #e2e8f0; padding: 8px 10px; }
        .photo { margin: 0 0 10px; text-align: center; page-break-inside: avoid; }
        .photo img { max-width: 100%; max-height: 120mm; }
        .muted { color: #64748b; font-size: 9pt; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8pt; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">{{ settings()->siteName() }} · {{ __('erp.incidents.pdf_generated', ['date' => now()->format('d/m/Y H:i')]) }}</div>

    @foreach ($suggestions as $suggestion)
        <div class="entry">
            <div class="header">
                <div class="site">{{ settings()->siteName() }} · {{ __('erp.suggestions.title') }} #{{ $suggestion->id }}</div>
                <h1>{{ trim($suggestion->first_name.' '.$suggestion->last_name) ?: $suggestion->submitter_name }}</h1>
                <span class="type {{ $suggestion->type->value }}">{{ $suggestion->type->getLabel() }}</span>
            </div>

            <table class="meta">
                <tr><th>{{ __('erp.suggestions.submitted_at') }}</th><td>{{ $suggestion->submitted_at->format('d/m/Y H:i') }}</td></tr>
                <tr><th>{{ __('erp.fields.first_name') }}</th><td>{{ $suggestion->first_name ?? '—' }}</td></tr>
                <tr><th>{{ __('erp.fields.last_name') }}</th><td>{{ $suggestion->last_name ?? '—' }}</td></tr>
                <tr><th>{{ __('erp.suggestions.may_be_public') }}</th><td>{{ $suggestion->may_be_public ? __('erp.incidents.yes') : __('erp.incidents.no') }}</td></tr>
                <tr><th>{{ __('erp.fields.status') }}</th><td>{{ $suggestion->status->getLabel() }}@if ($suggestion->handler) · {{ $suggestion->handler->name }}@endif</td></tr>
            </table>

            <h2>{{ __('erp.suggestions.description') }}</h2>
            <div class="text">{{ $suggestion->description }}</div>

            @if (filled($suggestion->response))
                <h2>{{ __('erp.suggestions.response') }}</h2>
                <div class="text">{{ $suggestion->response }}</div>
            @endif

            @if (filled($suggestion->photos))
                <h2>{{ __('erp.incidents.photos') }} ({{ count($suggestion->photos) }})</h2>
                @foreach ($suggestion->photos as $path)
                    @if ($dataUri = $suggestion->photoDataUri($path))
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
