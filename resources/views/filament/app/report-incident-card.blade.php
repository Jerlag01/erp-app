{{-- Highlighted on purpose: reporting should be one tap away. Inline styles, see theme.blade.php. --}}
<x-filament-widgets::widget>
    <a href="{{ \App\Filament\App\Pages\ReportIncident::getUrl(panel: 'app') }}" data-report-incident
       style="display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.5rem; padding: 1.25rem 1.5rem; border-radius: .75rem; background: linear-gradient(135deg, var(--erp-navy) 0%, var(--erp-navy-dark) 100%); color: #fff; border-left: 6px solid var(--erp-accent); text-decoration: none; box-shadow: 0 10px 30px -12px rgb(0 0 0 / .35);">
        <span style="display: inline-flex; align-items: center; justify-content: center; width: 3rem; height: 3rem; border-radius: 999px; background: var(--erp-accent); flex-shrink: 0;">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 1.75rem; height: 1.75rem; color: #fff;" />
        </span>
        <span style="flex: 1; min-width: 12rem;">
            <span style="display: block; font-size: 1.125rem; font-weight: 800;">{{ __('erp.incidents.card_title') }}</span>
            <span style="display: block; font-size: .875rem; opacity: .8;">{{ __('erp.incidents.card_text') }}</span>
        </span>
        <span style="display: inline-flex; align-items: center; gap: .5rem; padding: .625rem 1.25rem; border-radius: .5rem; background: var(--erp-accent); font-weight: 700;">
            {{ __('erp.incidents.report') }}
            <x-filament::icon icon="heroicon-m-arrow-right" style="width: 1rem; height: 1rem;" />
        </span>
    </a>
</x-filament-widgets::widget>
