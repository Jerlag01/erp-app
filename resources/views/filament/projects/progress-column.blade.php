{{-- Progress bar of a 610–650 project in the project list; empty for other projects. --}}
@php
    $progress = $getState();
    $percentage = $progress ? (int) round($progress['done'] / max(1, $progress['total']) * 100) : 0;
@endphp
@if ($progress)
    <div style="display: flex; align-items: center; gap: .5rem; min-width: 9rem; padding: 0 .75rem;" title="{{ $progress['done'] }} / {{ $progress['total'] }}">
        <div style="flex: 1; height: .45rem; border-radius: 999px; background: rgb(148 163 184 / .25); overflow: hidden;">
            <div style="height: 100%; width: {{ $percentage }}%; background: {{ $percentage === 100 ? 'rgb(34 197 94)' : 'var(--erp-accent, rgb(59 130 246))' }};"></div>
        </div>
        <span style="font-family: var(--erp-mono); font-size: .75rem; white-space: nowrap;">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
    </div>
@endif
