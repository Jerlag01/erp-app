{{--
    Floor plan editor. Shapes are kept as data and drawn into the SVG layer as markup (Alpine's
    x-for does not create SVG elements reliably). After every change the drawing is also exported
    as PNG for the PDF.
--}}
@php
    $statePath = $getStatePath();
    $pngPath = $field->getPngStatePath();
@endphp

@once
    @include('batteries.floor-plan.script')
@endonce

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="window.erpFloorPlan({ state: $wire.$entangle(@js($statePath)), png: $wire.$entangle(@js($pngPath)) })"
        x-on:keydown.window="if (($event.key === 'Delete' || $event.key === 'Backspace') && selected !== null && ! ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) { $event.preventDefault(); removeSelected(); }"
        class="erp-plan-editor"
        data-floor-plan-editor
    >
        @include('batteries.floor-plan.board')
    </div>
</x-dynamic-component>
