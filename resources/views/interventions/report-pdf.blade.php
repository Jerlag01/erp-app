@php
    $company = settings()->get('finance.company_name') ?: settings()->get('general.company_name') ?: settings()->siteName();
    $used = $report->materialsIn(\App\Enums\MaterialDirection::Used);
    $freed = $report->materialsIn(\App\Enums\MaterialDirection::Freed);
    $primary = settings()->color('primary');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $project->reference }}</title>
    <style>
        @page { margin: 14mm 14mm 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1e293b; }
        .top { width: 100%; border-collapse: collapse; }
        .top td { vertical-align: top; }
        .brand { font-size: 14pt; font-weight: bold; color: {{ $primary }}; }
        .title { font-size: 17pt; font-weight: bold; color: {{ $primary }}; text-align: right; }
        .accent { border-bottom: 3px solid {{ settings()->color('accent') }}; margin: 6px 0 12px; }
        .muted { color: #64748b; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 3px; font-weight: bold; color: #fff; }
        .status-pending { background: #d97706; } .status-approved { background: #15803d; } .status-rejected { background: #b91c1c; }
        table.facts { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.facts td { padding: 3px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.facts td.k { width: 26%; color: #64748b; }
        h2 { font-size: 10pt; color: {{ $primary }}; margin: 14px 0 4px; border-bottom: 1px solid {{ $primary }}; padding-bottom: 2px; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { background: {{ $primary }}; color: #fff; text-align: left; padding: 4px 6px; font-size: 7.5pt; }
        table.list td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .box { border: 1px solid #e2e8f0; padding: 6px 8px; white-space: pre-line; }
        .sign { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .sign td { width: 50%; vertical-align: top; padding: 6px 8px; border: 1px solid #e2e8f0; height: 52px; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 3px; }
    </style>
</head>
<body>
    <div class="footer">{{ $company }} · {{ $project->reference }} · {{ __('erp.interventions.pdf.generated', ['date' => now()->format('d/m/Y H:i')]) }}</div>

    <table class="top">
        <tr>
            <td style="width: 55%;">
                @if (($logo = settings()->get('appearance.logo')) && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo))
                    <img src="data:{{ \Illuminate\Support\Facades\Storage::disk('public')->mimeType($logo) }};base64,{{ base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($logo)) }}" style="max-height: 44px; max-width: 200px;"><br>
                @endif
                <div class="brand">{{ $company }}</div>
            </td>
            <td>
                <div class="title">{{ __('erp.interventions.pdf.title') }}</div>
                <div style="text-align: right; margin-top: 4px;">
                    <span class="mono" style="font-size: 11pt; font-weight: bold;">{{ $project->reference }}</span><br>
                    <span class="status status-{{ $report->status->value }}">{{ $report->status->getLabel() }}</span>
                </div>
            </td>
        </tr>
    </table>
    <div class="accent"></div>

    <table class="facts">
        <tr><td class="k">{{ __('erp.interventions.wo_number') }}</td><td class="mono"><strong>{{ $project->work_order_number ?: '—' }}</strong></td>
            <td class="k">{{ __('erp.interventions.po_number') }}</td><td class="mono"><strong>{{ $report->po_number ?: '—' }}</strong></td></tr>
        <tr><td class="k">{{ __('erp.interventions.cow_code') }}</td><td class="mono">{{ $report->cow_code ?: '—' }}</td>
            <td class="k">{{ __('erp.interventions.room') }}</td><td>{{ $report->room ?: '—' }}</td></tr>
        <tr><td class="k">{{ __('erp.fields.address') }}</td><td colspan="3">{{ $report->workSite?->addressLine() ?? $project->workSite?->addressLine() ?? '—' }}</td></tr>
        <tr><td class="k">{{ __('erp.interventions.categories') }}</td><td colspan="3"><strong>{{ implode(', ', $report->categoryNames()) ?: '—' }}</strong></td></tr>
        @if ($project->interventionSubcategoryLabel())
            <tr><td class="k">{{ __('erp.interventions.subcategory') }}</td><td colspan="3">{{ $project->interventionSubcategoryLabel() }}</td></tr>
        @endif
        @if ($project->short_description)
            <tr><td class="k">{{ __('erp.projects.short_description') }}</td><td colspan="3">{{ $project->short_description }}</td></tr>
        @endif
        <tr><td class="k">{{ __('erp.interventions.executed_at') }}</td><td>{{ $report->executed_at?->format('d/m/Y H:i') }}</td>
            <td class="k">{{ __('erp.interventions.hours_worked') }}</td><td>{{ $report->hoursLabel() }} {{ __('erp.interventions.hours_suffix') }}</td></tr>
        <tr><td class="k">{{ __('erp.interventions.executed_by') }}</td><td colspan="3">{{ implode(', ', $report->workerNames()) }}</td></tr>
    </table>

    @foreach ([[\App\Enums\MaterialDirection::Used, $used], [\App\Enums\MaterialDirection::Freed, $freed]] as [$direction, $materials])
        @if ($materials)
            <h2>{{ __('erp.interventions.pdf.materials_'.$direction->value) }}</h2>
            <table class="list">
                <thead><tr><th>{{ __('erp.projects.parts.item') }}</th><th class="num" style="width: 22%;">{{ __('erp.projects.parts.quantity') }}</th></tr></thead>
                <tbody>
                    @foreach ($materials as $material)
                        <tr><td>{{ $material->description }}</td><td class="num">{{ $material->quantityLabel() }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
    @if (! $used && ! $freed)
        <h2>{{ __('erp.interventions.materials') }}</h2>
        <p class="muted">{{ __('erp.interventions.pdf.no_materials') }}</p>
    @endif

    <h2>{{ __('erp.interventions.positions') }}</h2>
    @if ($report->positions->isNotEmpty())
        <table class="list">
            <thead><tr><th>{{ __('erp.interventions.board') }}</th><th>AC/DC</th><th>{{ __('erp.interventions.device') }}</th><th>{{ __('erp.interventions.supply') }}</th></tr></thead>
            <tbody>
                @foreach ($report->positions as $position)
                    <tr>
                        <td>{{ $position->board_name }}</td>
                        <td>{{ $position->current }}</td>
                        <td class="mono"><strong>{{ $position->device }}{{ $position->number }}</strong> <span class="muted">({{ __('erp.interventions.devices.'.$position->device) }})</span></td>
                        <td>{{ __('erp.interventions.supply_side', ['side' => $position->supply]) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">—</p>
    @endif

    <h2>{{ __('erp.interventions.modified_boards') }}</h2>
    <p>{{ implode(', ', $report->boardNames()) ?: '—' }}</p>

    @if (filled($report->remarks))
        <h2>{{ __('erp.interventions.remarks') }}</h2>
        <div class="box">{{ $report->remarks }}</div>
    @endif

    @if ($report->reviewed_at)
        <h2>{{ __('erp.interventions.pdf.review') }}</h2>
        <p>{{ $report->status->getLabel() }} · {{ $report->reviewer?->name }} · {{ $report->reviewed_at->format('d/m/Y H:i') }}</p>
        @if (filled($report->review_note))
            <div class="box">{{ $report->review_note }}</div>
        @endif
    @endif

    <table class="sign">
        <tr>
            <td><span class="muted">{{ __('erp.interventions.pdf.closed_by') }}</span><br>{{ $report->closer?->name }} · {{ $report->executed_at?->format('d/m/Y') }}</td>
            <td><span class="muted">{{ __('erp.interventions.pdf.approved_by') }}</span><br>@if ($report->status === \App\Enums\InterventionReportStatus::Approved){{ $report->reviewer?->name }} · {{ $report->reviewed_at?->format('d/m/Y') }}@endif</td>
        </tr>
    </table>
</body>
</html>
