{{-- Progress bar of a 610–650 project: the steps as tiles, ticked by hand or automatically. --}}
@php
    $progress = $getState();
@endphp
<div style="display: flex; flex-direction: column; gap: .75rem;">
    <div style="height: .5rem; border-radius: 999px; background: rgb(148 163 184 / .25); overflow: hidden;">
        <div style="height: 100%; width: {{ $progress['percentage'] }}%; background: var(--erp-accent, rgb(59 130 246)); transition: width .3s;"></div>
    </div>
    <div style="display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr));">
        @foreach ($progress['steps'] as $step)
            <label wire:key="step-{{ $step['number'] }}"
                style="display: flex; gap: .6rem; align-items: flex-start; padding: .6rem .75rem; border-radius: .5rem; border: 1px solid {{ $step['done'] ? 'rgb(34 197 94 / .45)' : 'rgb(148 163 184 / .3)' }}; background: {{ $step['done'] ? 'rgb(34 197 94 / .08)' : 'transparent' }}; {{ $progress['editable'] ? 'cursor: pointer;' : '' }}">
                <input type="checkbox" @checked($step['done']) @disabled(! $progress['editable'])
                    wire:click="toggleStep({{ $step['number'] }})" wire:loading.attr="disabled"
                    style="margin-top: .2rem; accent-color: var(--erp-accent, rgb(59 130 246));">
                <span style="display: flex; flex-direction: column; gap: .15rem;">
                    <span style="font-size: .875rem;"><strong style="font-family: var(--erp-mono);">{{ $step['number'] }}.</strong> {{ $step['label'] }}</span>
                    @if ($step['done'])
                        <span style="font-family: var(--erp-mono); font-size: .75rem; opacity: .7;">{{ $step['date'] ?? __('erp.project_progress.date_unknown') }}@if ($step['automatic']) · {{ __('erp.project_progress.automatic') }}@endif</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</div>
