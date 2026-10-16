{{--
    Signature pad. The canvas gets its pixel size when it is actually visible (a modal is still hidden
    when the form loads, which left a 0×0 canvas: the strokes were not shown) and again whenever its size
    changes; what was drawn is kept.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            drawing: false,
            ctx: null,
            ratio: 1,
            observer: null,
            init() {
                this.$nextTick(() => this.resize());
                this.observer = new ResizeObserver(() => this.resize());
                this.observer.observe(this.$refs.canvas);
            },
            destroy() {
                this.observer?.disconnect();
            },
            resize() {
                const canvas = this.$refs.canvas;
                const width = canvas.clientWidth;
                const height = canvas.clientHeight;

                if (! width || ! height) return;

                this.ratio = Math.max(window.devicePixelRatio || 1, 1);

                if (canvas.width === Math.round(width * this.ratio) && canvas.height === Math.round(height * this.ratio) && this.ctx) return;

                canvas.width = Math.round(width * this.ratio);
                canvas.height = Math.round(height * this.ratio);
                this.ctx = canvas.getContext('2d');
                this.ctx.setTransform(this.ratio, 0, 0, this.ratio, 0, 0);
                this.ctx.lineWidth = 2.6;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#0f172a';
                this.ctx.fillStyle = '#0f172a';

                // Redraw what was signed before the resize.
                if (this.state) {
                    const image = new Image();
                    image.onload = () => this.ctx.drawImage(image, 0, 0, width, height);
                    image.src = this.state;
                }
            },
            point(event) {
                const rect = this.$refs.canvas.getBoundingClientRect();
                return { x: event.clientX - rect.left, y: event.clientY - rect.top };
            },
            start(event) {
                if (! this.ctx || this.$refs.canvas.width === 0) this.resize();
                if (! this.ctx) return;

                this.drawing = true;
                this.$refs.canvas.setPointerCapture(event.pointerId);
                const p = this.point(event);
                this.ctx.beginPath();
                this.ctx.moveTo(p.x, p.y);
                // A dot for a single tap.
                this.ctx.arc(p.x, p.y, this.ctx.lineWidth / 2, 0, Math.PI * 2);
                this.ctx.fill();
                this.ctx.beginPath();
                this.ctx.moveTo(p.x, p.y);
            },
            move(event) {
                if (! this.drawing) return;
                const p = this.point(event);
                this.ctx.lineTo(p.x, p.y);
                this.ctx.stroke();
                this.ctx.beginPath();
                this.ctx.moveTo(p.x, p.y);
            },
            end() {
                if (! this.drawing) return;
                this.drawing = false;
                this.state = this.$refs.canvas.toDataURL('image/png');
            },
            clear() {
                const canvas = this.$refs.canvas;
                this.ctx?.clearRect(0, 0, canvas.width, canvas.height);
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
            x-on:pointercancel="end()"
            x-on:pointerleave="end()"
            style="display: block; width: 100%; height: 11rem; touch-action: none; background: #ffffff; border: 1px dashed rgb(148 163 184); border-radius: .5rem; cursor: crosshair;"
        ></canvas>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: .8125rem; opacity: .75;">
            <span x-text="state ? @js(__('erp.finance.expenses.signature_done')) : @js(__('erp.finance.expenses.signature_hint'))"></span>
            <button type="button" x-on:click="clear()" style="text-decoration: underline;">{{ __('erp.finance.expenses.signature_clear') }}</button>
        </div>
    </div>
</x-dynamic-component>
