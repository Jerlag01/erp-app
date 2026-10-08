<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            drawing: false,
            ctx: null,
            init() {
                const canvas = this.$refs.canvas;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                this.ctx = canvas.getContext('2d');
                this.ctx.scale(ratio, ratio);
                this.ctx.lineWidth = 2.2;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#0f172a';
            },
            point(event) {
                const rect = this.$refs.canvas.getBoundingClientRect();
                return { x: event.clientX - rect.left, y: event.clientY - rect.top };
            },
            start(event) {
                this.drawing = true;
                this.$refs.canvas.setPointerCapture(event.pointerId);
                const p = this.point(event);
                this.ctx.beginPath();
                this.ctx.moveTo(p.x, p.y);
            },
            move(event) {
                if (! this.drawing) return;
                const p = this.point(event);
                this.ctx.lineTo(p.x, p.y);
                this.ctx.stroke();
            },
            end() {
                if (! this.drawing) return;
                this.drawing = false;
                this.state = this.$refs.canvas.toDataURL('image/png');
            },
            clear() {
                this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                this.state = null;
            },
        }"
        style="display: flex; flex-direction: column; gap: .5rem;"
        data-signature-pad
    >
        <canvas
            x-ref="canvas"
            x-on:pointerdown.prevent="start($event)"
            x-on:pointermove.prevent="move($event)"
            x-on:pointerup="end()"
            x-on:pointerleave="end()"
            style="width: 100%; height: 11rem; touch-action: none; background: #fff; border: 1px dashed rgb(148 163 184); border-radius: .5rem; cursor: crosshair;"
        ></canvas>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: .8125rem; opacity: .75;">
            <span x-text="state ? @js(__('erp.finance.expenses.signature_done')) : @js(__('erp.finance.expenses.signature_hint'))"></span>
            <button type="button" x-on:click="clear()" style="text-decoration: underline;">{{ __('erp.finance.expenses.signature_clear') }}</button>
        </div>
    </div>
</x-dynamic-component>
