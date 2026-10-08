<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('erp.waste.totals') }}</title>
    @include('waste.pdf-style')
</head>
<body>
    <div class="footer">{{ settings()->siteName() }} · {{ __('erp.incidents.pdf_generated', ['date' => now()->format('d/m/Y H:i')]) }}</div>

    <div class="header">
        <div class="site">{{ settings()->siteName() }} · {{ __('erp.waste.title') }}</div>
        <h1>{{ __('erp.waste.totals') }}</h1>
        <div class="period">{{ __('erp.waste.period') }}: {{ $period }}</div>
    </div>

    @include('waste.totals-table', ['totals' => $totals])
</body>
</html>
