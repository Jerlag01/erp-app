{{-- The toolbar and drawing area of the floor plan editor (inside an element with x-data="erpFloorPlan(...)"). --}}
@php
    $labels = [
        'select' => __('erp.batteries.plan.select'), 'pen' => __('erp.batteries.plan.pen'), 'line' => __('erp.batteries.plan.line'),
        'arrow' => __('erp.batteries.plan.arrow'), 'dim' => __('erp.batteries.plan.dim'), 'rect' => __('erp.batteries.plan.rect'),
        'ellipse' => __('erp.batteries.plan.ellipse'), 'text' => __('erp.batteries.plan.text'),
    ];
@endphp
<style>
    .erp-plan-editor { display: flex; flex-direction: column; gap: .5rem; }
    .erp-plan-tools { display: flex; flex-wrap: wrap; gap: .375rem; align-items: center; }
    .erp-plan-tools button { padding: .3rem .6rem; font-size: .8125rem; border: 1px solid rgb(148 163 184 / .5); border-radius: .375rem; background: transparent; cursor: pointer; }
    .erp-plan-tools button.is-on { background: var(--erp-accent, #f7941d); color: #fff; border-color: transparent; }
    .erp-plan-tools .sep { width: 1px; height: 1.5rem; background: rgb(148 163 184 / .5); margin: 0 .25rem; }
    .erp-plan-swatch { width: 1.5rem; height: 1.5rem; border-radius: 999px; border: 2px solid transparent; cursor: pointer; }
    .erp-plan-swatch.is-on { border-color: var(--erp-accent, #f7941d); box-shadow: 0 0 0 2px #fff inset; }
    .erp-plan-canvas { width: 100%; aspect-ratio: {{ \App\Filament\Forms\FloorPlanEditor::WIDTH }} / {{ \App\Filament\Forms\FloorPlanEditor::HEIGHT }}; background: #fff; border: 1px solid rgb(148 163 184 / .6); border-radius: .5rem; touch-action: none; }
</style>

<div class="erp-plan-tools" role="toolbar" aria-label="{{ __('erp.batteries.plan.tools') }}">
    @foreach ($labels as $tool => $label)
        <button type="button" x-on:click="tool = @js($tool); selected = null; render()" :class="{ 'is-on': tool === @js($tool) }" :aria-pressed="tool === @js($tool)">{{ $label }}</button>
    @endforeach
    <span class="sep"></span>
    @foreach (['#0f172a', '#dc2626', '#2563eb', '#16a34a', '#ea580c'] as $swatch)
        <button type="button" class="erp-plan-swatch" style="background: {{ $swatch }};" x-on:click="color = @js($swatch); if (selected !== null) { remember(); shapes[selected].c = color; commit(); }" :class="{ 'is-on': color === @js($swatch) }" aria-label="{{ $swatch }}"></button>
    @endforeach
    <span class="sep"></span>
    @foreach ([2 => 'S', 4 => 'M', 7 => 'L'] as $size => $label)
        <button type="button" x-on:click="width = {{ $size }}" :class="{ 'is-on': width === {{ $size }} }" title="{{ __('erp.batteries.plan.width') }}">{{ $label }}</button>
    @endforeach
    <button type="button" x-on:click="fill = ! fill" :class="{ 'is-on': fill }">{{ __('erp.batteries.plan.fill') }}</button>
    <button type="button" x-on:click="grid = ! grid; exportPng()" :class="{ 'is-on': grid }">{{ __('erp.batteries.plan.grid') }}</button>
    <span class="sep"></span>
    <button type="button" x-on:click="removeSelected()" :disabled="selected === null">{{ __('erp.batteries.plan.delete') }}</button>
    <button type="button" x-on:click="undo()">{{ __('erp.batteries.plan.undo') }}</button>
    <button type="button" x-on:click="clearAll()">{{ __('erp.batteries.plan.clear') }}</button>
</div>

<svg
    x-ref="svg"
    class="erp-plan-canvas"
    viewBox="0 0 {{ \App\Filament\Forms\FloorPlanEditor::WIDTH }} {{ \App\Filament\Forms\FloorPlanEditor::HEIGHT }}"
    x-on:pointerdown.prevent="down($event)"
    x-on:pointermove.prevent="move($event)"
    x-on:pointerup="up()"
    x-on:dblclick="edit($event)"
    :style="{ cursor: tool === 'select' ? 'default' : 'crosshair' }"
>
    <defs>
        <pattern id="erp-plan-grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="#e2e8f0" stroke-width="1"/></pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#erp-plan-grid)" x-show="grid"></rect>
    <g x-ref="layer"></g>
</svg>

<p style="font-size: .75rem; opacity: .7;">{{ __('erp.batteries.plan.help') }}</p>
