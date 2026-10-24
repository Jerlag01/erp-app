{{--
    The floor plan editor for the mobile app's web view: the same editor as on the dossier page.
    "Save" hands the shapes and the PNG to the app (window.ReactNativeWebView), which saves them.
--}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ __('erp.batteries.tabs.floor_plan') }} · {{ $dossier->reference() }}</title>
    <style>
        :root { --erp-accent: {{ settings()->color('accent') }}; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: .75rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #0f172a; -webkit-user-select: none; user-select: none; }
        .erp-plan-tools button { min-height: 2.25rem; color: inherit; }
        .erp-plan-swatch { width: 2rem !important; height: 2rem !important; }
        .bar { position: sticky; bottom: 0; display: flex; gap: .5rem; padding: .75rem 0 .25rem; background: #f8fafc; }
        .bar button { flex: 1; padding: .8rem; font-size: 1rem; font-weight: 600; border-radius: .5rem; border: 1px solid rgb(148 163 184 / .6); background: #fff; color: #0f172a; }
        .bar button.primary { background: var(--erp-accent); border-color: transparent; color: #fff; }
        .bar button:disabled { opacity: .6; }
        .readonly .erp-plan-tools { display: none; }
        .readonly .erp-plan-canvas { pointer-events: none; }
    </style>
</head>
<body>
    @include('batteries.floor-plan.script')

    <div
        x-data="{ ...window.erpFloorPlan({ state: @js($dossier->floor_plan ?? []), png: null }), saving: false,
            async save() {
                this.saving = true;
                const png = await this.exportPng();
                window.ReactNativeWebView?.postMessage(JSON.stringify({ type: 'save', shapes: this.shapes, png }));
            },
            close() { window.ReactNativeWebView?.postMessage(JSON.stringify({ type: 'close' })); } }"
        class="erp-plan-editor @unless ($editable) readonly @endunless"
        data-mobile-floor-plan data-editable="{{ $editable ? 'yes' : 'no' }}"
    >
        @include('batteries.floor-plan.board')

        <div class="bar">
            <button type="button" x-on:click="close()">{{ $editable ? __('erp.mobile.cancel') : __('erp.mobile.close') }}</button>
            @if ($editable)
                <button type="button" class="primary" x-on:click="save()" :disabled="saving">{{ __('erp.mobile.save') }}</button>
            @endif
        </div>
    </div>

    @livewireScripts
</body>
</html>
