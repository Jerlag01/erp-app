{{-- The delivery report of an IT asset (A4 portrait), like the Word form, in the employee's language. --}}
@php
    $t = fn (string $key, array $replace = []) => __('erp.it.delivery_report.'.$key, $replace);
    $d = fn (string $key) => $report->detail($key);
    $name = $report->user?->employee?->fullName() ?: $report->user?->name;
    $logo = settings()->get('appearance.logo');
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    $wiped = $d('old_wiped');
    $condition = $d('condition');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 11mm 15mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #111; }
        .company { color: #1f4e79; font-weight: bold; font-size: 9pt; margin: 2mm 0 1mm; }
        hr { border: 0; border-top: 1px solid #1f4e79; margin: 0 0 3mm; }
        h1 { text-align: center; color: #1f4e79; font-size: 14pt; margin: 0; }
        .subtitle { text-align: center; margin: .5mm 0 2mm; }
        h2 { background: #dbe5f1; color: #1f4e79; font-size: 8.5pt; margin: 3mm 0 1.2mm; padding: .8mm 2mm; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: .8mm 2mm; vertical-align: top; text-align: left; }
        th { background: #2f5597; color: #fff; }
        td.label { font-weight: bold; width: 32%; }
        ol { margin: 0; padding-left: 6mm; }
        ol li { margin-bottom: .6mm; }
        .signatures td { border: 0; padding: 0 2mm 0 0; width: 50%; }
        .signature { height: 32mm; }
        .signature img { max-height: 28mm; max-width: 100%; }
        .hint { font-size: 7pt; font-style: italic; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 6.5pt; color: #999; }
    </style>
</head>
<body>
    @if ($logo && $disk->exists($logo))
        <img src="data:{{ $disk->mimeType($logo) }};base64,{{ base64_encode($disk->get($logo)) }}" style="max-height: 11mm; max-width: 45mm;">
    @endif
    <div class="company">{{ mb_strtoupper($company) }}</div>
    <hr>

    <h1>{{ mb_strtoupper($t('title')) }}</h1>
    <div class="subtitle">{{ $t('subtitle') }}</div>

    <h2>1. {{ $t('employee') }}</h2>
    <table>
        <tr><td class="label">{{ $t('name') }}</td><td>{{ $name }}</td></tr>
        <tr><td class="label">{{ $t('job_title') }}</td><td>{{ $d('job_title') }}</td></tr>
        <tr><td class="label">{{ $t('site') }}</td><td>{{ $d('site') }}</td></tr>
        <tr><td class="label">{{ $t('handed_over_on') }}</td><td>{{ $report->handed_over_on?->format('d/m/Y') }}</td></tr>
    </table>

    <h2>2. {{ $t('device_handed') }}</h2>
    <table>
        <tr><th style="width: 28%;">{{ $t('part') }}</th><th style="width: 36%;">{{ $t('description') }}</th><th>{{ $t('reference') }}</th></tr>
        <tr><td class="label" style="width: auto;">{{ $t('device') }}</td><td>{{ $d('device') }}</td><td>{{ collect([$report->asset?->asset_tag, $d('serial')])->filter()->implode(' / ') }}</td></tr>
        <tr><td class="label" style="width: auto;">{{ $t('imei') }}</td><td colspan="2">{{ $d('imei') }}</td></tr>
        <tr><td class="label" style="width: auto;">{{ $t('phone_number') }}</td><td colspan="2">{{ $d('phone_number') }}</td></tr>
        <tr><td class="label" style="width: auto;">{{ $t('sim') }}</td><td colspan="2">{{ $d('sim') }}</td></tr>
        <tr><td class="label" style="width: auto;">{{ $t('accessories') }}</td><td colspan="2">{{ $d('accessories') }}</td></tr>
        <tr><td class="label" style="width: auto;">{{ $t('condition') }}</td><td colspan="2">{{ $condition ? $t('conditions.'.$condition) : '' }}</td></tr>
    </table>

    <h2>3. {{ $t('terms_title') }}</h2>
    <ol>
        @foreach (range(1, 5) as $term)
            <li>{{ $t('terms.'.$term, ['company' => $company]) }}</li>
        @endforeach
    </ol>

    <h2>4. {{ $t('old_device_title') }}</h2>
    <table>
        <tr>
            <td class="label" style="width: 22%;">{{ $t('old_device') }}</td><td style="width: 28%;">{{ $d('old_device') }}</td>
            <td class="label" style="width: 22%;">{{ $t('imei') }}</td><td>{{ $d('old_imei') }}</td>
        </tr>
        <tr>
            <td class="label">{{ $t('old_condition') }}</td><td>{{ $d('old_condition') }}</td>
            <td class="label">{{ $t('old_wiped') }}</td><td>{{ $wiped === null ? '' : ($wiped ? $t('yes') : $t('no')) }}</td>
        </tr>
    </table>

    <h2>5. {{ $t('signatures') }}</h2>
    <table class="signatures">
        <tr>
            <td><strong>{{ $t('for_employee') }}</strong></td>
            <td><strong>{{ $t('for_company', ['company' => $company]) }}</strong></td>
        </tr>
        <tr>
            <td>{{ $t('sig_name') }}: {{ $report->signer_name }}</td>
            <td>{{ $t('sig_name') }}: {{ $report->company_signer_name }}</td>
        </tr>
        <tr>
            <td>{{ $t('date') }}: {{ $report->signed_at?->format('d/m/Y H:i') }}</td>
            <td>{{ $t('date') }}: {{ $report->handed_over_on?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="signature">
                @if ($report->isSigned())<div style="font-style: italic;">« {{ $t('read_approved_mark') }} »</div>@endif
                @if ($employeeSignature)<img src="{{ $employeeSignature }}" alt="">@endif
            </td>
            <td class="signature">@if ($companySignature)<img src="{{ $companySignature }}" alt="">@endif</td>
        </tr>
        <tr>
            <td class="hint">{{ $t('read_approved') }}</td>
            <td class="hint">{{ $t('signature') }}</td>
        </tr>
    </table>

    <div class="footer">{{ $t('footer', ['company' => $company]) }}</div>
</body>
</html>
