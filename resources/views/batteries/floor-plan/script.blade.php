{{-- The floor plan editor (Alpine), shared by the dossier page and the mobile app's editor page. --}}
<script>
        window.erpFloorPlan = function (config) {
            return {
                shapes: [],
                state: config.state,
                png: config.png,
                tool: 'pen',
                color: '#0f172a',
                width: 3,
                fill: false,
                grid: true,
                selected: null,
                draft: null,
                drag: null,
                history: [],
                W: {{ \App\Filament\Forms\FloorPlanEditor::WIDTH }},
                H: {{ \App\Filament\Forms\FloorPlanEditor::HEIGHT }},
                init() {
                    let initial = this.state;
                    if (typeof initial === 'string') { try { initial = JSON.parse(initial); } catch (e) { initial = []; } }
                    this.shapes = Array.isArray(initial) ? initial : [];
                    this.render();
                },
                point(event) {
                    const svg = this.$refs.svg;
                    const pt = svg.createSVGPoint();
                    pt.x = event.clientX; pt.y = event.clientY;
                    const p = pt.matrixTransform(svg.getScreenCTM().inverse());
                    return { x: Math.round(p.x * 10) / 10, y: Math.round(p.y * 10) / 10 };
                },
                remember() {
                    this.history.push(JSON.stringify(this.shapes));
                    if (this.history.length > 60) this.history.shift();
                },
                undo() {
                    if (! this.history.length) return;
                    this.shapes = JSON.parse(this.history.pop());
                    this.selected = null;
                    this.commit();
                },
                clearAll() {
                    if (! this.shapes.length || ! confirm(@js(__('erp.batteries.plan.clear_confirm')))) return;
                    this.remember();
                    this.shapes = [];
                    this.selected = null;
                    this.commit();
                },
                removeSelected() {
                    if (this.selected === null) return;
                    this.remember();
                    this.shapes.splice(this.selected, 1);
                    this.selected = null;
                    this.commit();
                },
                down(event) {
                    const p = this.point(event);
                    this.$refs.svg.setPointerCapture(event.pointerId);

                    if (this.tool === 'select') {
                        this.selected = this.hit(p);
                        if (this.selected !== null) { this.remember(); this.drag = { x: p.x, y: p.y }; }
                        this.render();
                        return;
                    }

                    if (this.tool === 'text') {
                        const text = prompt(@js(__('erp.batteries.plan.text_prompt')));
                        if (text) { this.remember(); this.shapes.push({ t: 'text', x: p.x, y: p.y, text, c: this.color, w: this.width, size: 14 + this.width * 3 }); this.commit(); }
                        return;
                    }

                    const base = { c: this.color, w: this.width };
                    if (this.tool === 'pen') this.draft = { ...base, t: 'pen', pts: [[p.x, p.y]] };
                    else if (['line', 'arrow', 'dim'].includes(this.tool)) this.draft = { ...base, t: this.tool, x1: p.x, y1: p.y, x2: p.x, y2: p.y, label: '' };
                    else this.draft = { ...base, t: this.tool, x: p.x, y: p.y, wd: 0, ht: 0, label: '', fill: this.fill, ox: p.x, oy: p.y };
                    this.render();
                },
                move(event) {
                    const p = this.point(event);

                    if (this.drag && this.selected !== null) {
                        this.translate(this.shapes[this.selected], p.x - this.drag.x, p.y - this.drag.y);
                        this.drag = { x: p.x, y: p.y };
                        this.render();
                        return;
                    }

                    if (! this.draft) return;
                    const d = this.draft;
                    if (d.t === 'pen') d.pts.push([p.x, p.y]);
                    else if (['line', 'arrow', 'dim'].includes(d.t)) { d.x2 = p.x; d.y2 = p.y; }
                    else { d.x = Math.min(d.ox, p.x); d.y = Math.min(d.oy, p.y); d.wd = Math.abs(p.x - d.ox); d.ht = Math.abs(p.y - d.oy); }
                    this.render();
                },
                up() {
                    if (this.drag) { this.drag = null; this.commit(); return; }
                    if (! this.draft) return;

                    const d = this.draft;
                    this.draft = null;
                    const tiny = d.t === 'pen' ? d.pts.length < 2 : (['line', 'arrow', 'dim'].includes(d.t) ? Math.hypot(d.x2 - d.x1, d.y2 - d.y1) < 4 : d.wd < 4 && d.ht < 4);
                    if (tiny) { this.render(); return; }

                    if (d.t === 'dim') d.label = prompt(@js(__('erp.batteries.plan.dim_prompt'))) || '';
                    delete d.ox; delete d.oy;
                    this.remember();
                    this.shapes.push(d);
                    this.commit();
                },
                edit(event) {
                    if (this.tool !== 'select') return;
                    const index = this.hit(this.point(event));
                    if (index === null) return;
                    const s = this.shapes[index];
                    const key = s.t === 'text' ? 'text' : 'label';
                    if (! ['text', 'dim', 'rect', 'ellipse', 'line', 'arrow'].includes(s.t)) return;
                    const value = prompt(@js(__('erp.batteries.plan.label_prompt')), s[key] || '');
                    if (value === null) return;
                    this.remember();
                    s[key] = value;
                    this.commit();
                },
                translate(s, dx, dy) {
                    if (s.t === 'pen') s.pts = s.pts.map(([x, y]) => [x + dx, y + dy]);
                    else if (['line', 'arrow', 'dim'].includes(s.t)) { s.x1 += dx; s.y1 += dy; s.x2 += dx; s.y2 += dy; }
                    else { s.x += dx; s.y += dy; }
                },
                hit(p) {
                    const near = (x1, y1, x2, y2) => {
                        const l = (x2 - x1) ** 2 + (y2 - y1) ** 2;
                        const t = l ? Math.max(0, Math.min(1, ((p.x - x1) * (x2 - x1) + (p.y - y1) * (y2 - y1)) / l)) : 0;
                        return Math.hypot(p.x - (x1 + t * (x2 - x1)), p.y - (y1 + t * (y2 - y1))) < 10;
                    };
                    for (let i = this.shapes.length - 1; i >= 0; i--) {
                        const s = this.shapes[i];
                        if (s.t === 'pen' && s.pts.some((q, k) => k > 0 && near(s.pts[k - 1][0], s.pts[k - 1][1], q[0], q[1]))) return i;
                        if (['line', 'arrow', 'dim'].includes(s.t) && near(s.x1, s.y1, s.x2, s.y2)) return i;
                        if (['rect', 'ellipse'].includes(s.t) && p.x >= s.x - 8 && p.x <= s.x + s.wd + 8 && p.y >= s.y - 8 && p.y <= s.y + s.ht + 8) return i;
                        if (s.t === 'text' && p.x >= s.x - 4 && p.x <= s.x + s.text.length * s.size * 0.6 && p.y >= s.y - s.size && p.y <= s.y + 6) return i;
                    }
                    return null;
                },
                esc(text) {
                    return String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                },
                head(x1, y1, x2, y2, s) {
                    const a = Math.atan2(y2 - y1, x2 - x1);
                    const size = 10 + s.w * 2;
                    const p1 = [x2 - size * Math.cos(a - 0.45), y2 - size * Math.sin(a - 0.45)];
                    const p2 = [x2 - size * Math.cos(a + 0.45), y2 - size * Math.sin(a + 0.45)];
                    return `<polygon points="${x2},${y2} ${p1[0]},${p1[1]} ${p2[0]},${p2[1]}" fill="${s.c}"/>`;
                },
                shapeSvg(s) {
                    const stroke = `stroke="${s.c}" stroke-width="${s.w}" stroke-linecap="round" stroke-linejoin="round"`;
                    if (s.t === 'pen') return `<polyline points="${s.pts.map((q) => q.join(',')).join(' ')}" fill="none" ${stroke}/>`;
                    if (s.t === 'line' || s.t === 'arrow') {
                        let out = `<line x1="${s.x1}" y1="${s.y1}" x2="${s.x2}" y2="${s.y2}" ${stroke}/>`;
                        if (s.t === 'arrow') out += this.head(s.x1, s.y1, s.x2, s.y2, s);
                        if (s.label) out += this.labelSvg(s);
                        return out;
                    }
                    if (s.t === 'dim') {
                        const a = Math.atan2(s.y2 - s.y1, s.x2 - s.x1);
                        const nx = -Math.sin(a) * 9, ny = Math.cos(a) * 9;
                        let out = `<line x1="${s.x1}" y1="${s.y1}" x2="${s.x2}" y2="${s.y2}" ${stroke}/>`;
                        out += `<line x1="${s.x1 + nx}" y1="${s.y1 + ny}" x2="${s.x1 - nx}" y2="${s.y1 - ny}" ${stroke}/>`;
                        out += `<line x1="${s.x2 + nx}" y1="${s.y2 + ny}" x2="${s.x2 - nx}" y2="${s.y2 - ny}" ${stroke}/>`;
                        out += this.head(s.x1, s.y1, s.x2, s.y2, s) + this.head(s.x2, s.y2, s.x1, s.y1, s);
                        return out + this.labelSvg(s);
                    }
                    if (s.t === 'rect' || s.t === 'ellipse') {
                        const fill = s.fill ? `${s.c}26` : 'none';
                        let out = s.t === 'rect'
                            ? `<rect x="${s.x}" y="${s.y}" width="${s.wd}" height="${s.ht}" fill="${fill}" ${stroke}/>`
                            : `<ellipse cx="${s.x + s.wd / 2}" cy="${s.y + s.ht / 2}" rx="${s.wd / 2}" ry="${s.ht / 2}" fill="${fill}" ${stroke}/>`;
                        if (s.label) out += `<text x="${s.x + s.wd / 2}" y="${s.y + s.ht / 2 + 6}" font-size="18" font-family="Arial, sans-serif" text-anchor="middle" fill="${s.c}">${this.esc(s.label)}</text>`;
                        return out;
                    }
                    if (s.t === 'text') return `<text x="${s.x}" y="${s.y}" font-size="${s.size}" font-family="Arial, sans-serif" fill="${s.c}">${this.esc(s.text)}</text>`;
                    return '';
                },
                labelSvg(s) {
                    if (! s.label) return '';
                    let a = Math.atan2(s.y2 - s.y1, s.x2 - s.x1) * 180 / Math.PI;
                    if (a > 90 || a < -90) a += 180;
                    const mx = (s.x1 + s.x2) / 2, my = (s.y1 + s.y2) / 2;
                    return `<g transform="translate(${mx} ${my}) rotate(${a})"><rect x="${-s.label.length * 5.5 - 6}" y="-30" width="${s.label.length * 11 + 12}" height="22" fill="#ffffff" opacity="0.85"/><text x="0" y="-13" font-size="18" font-family="Arial, sans-serif" text-anchor="middle" fill="${s.c}">${this.esc(s.label)}</text></g>`;
                },
                bbox(s) {
                    if (s.t === 'pen') { const xs = s.pts.map((q) => q[0]), ys = s.pts.map((q) => q[1]); return [Math.min(...xs), Math.min(...ys), Math.max(...xs), Math.max(...ys)]; }
                    if (['line', 'arrow', 'dim'].includes(s.t)) return [Math.min(s.x1, s.x2), Math.min(s.y1, s.y2), Math.max(s.x1, s.x2), Math.max(s.y1, s.y2)];
                    if (s.t === 'text') return [s.x, s.y - s.size, s.x + s.text.length * s.size * 0.6, s.y + 4];
                    return [s.x, s.y, s.x + s.wd, s.y + s.ht];
                },
                markup(withSelection) {
                    let out = this.shapes.map((s) => this.shapeSvg(s)).join('');
                    if (this.draft) out += this.shapeSvg(this.draft);
                    if (withSelection && this.selected !== null && this.shapes[this.selected]) {
                        const [x1, y1, x2, y2] = this.bbox(this.shapes[this.selected]);
                        out += `<rect x="${x1 - 8}" y="${y1 - 8}" width="${x2 - x1 + 16}" height="${y2 - y1 + 16}" fill="none" stroke="#f97316" stroke-width="2" stroke-dasharray="6 4"/>`;
                    }
                    return out;
                },
                render() {
                    this.$refs.layer.innerHTML = this.markup(true);
                },
                commit() {
                    this.render();
                    this.state = JSON.parse(JSON.stringify(this.shapes));
                    this.exportPng();
                },
                exportPng() {
                    if (! this.shapes.length) { this.png = null; return Promise.resolve(null); }
                    const grid = this.grid ? `<defs><pattern id="g" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="#e2e8f0" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(#g)"/>` : '';
                    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${this.W}" height="${this.H}" viewBox="0 0 ${this.W} ${this.H}"><rect width="100%" height="100%" fill="#ffffff"/>${grid}${this.markup(false)}</svg>`;
                    return new Promise((resolve) => {
                        const img = new Image();
                        img.onload = () => {
                            const canvas = document.createElement('canvas');
                            canvas.width = this.W * 1.5; canvas.height = this.H * 1.5;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                            this.png = canvas.toDataURL('image/png');
                            resolve(this.png);
                        };
                        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
                    });
                },
            };
        };
    </script>
