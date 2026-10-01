/* ==========================================================
   PCMS charts — plain Canvas 2D, zero external dependencies.
   Works fully offline (no CDN needed). Replaces Chart.js.
   ========================================================== */
const PCMS_COLORS = {
    healthy: '#2E8B57',
    at_risk: '#C98A00',
    critical: '#C13A50',
    under_repair: '#5B5FC7',
    retired: '#8A94A0',
    primary: '#0E7C86',
};
const STATUS_LABELS = { healthy: 'Healthy', at_risk: 'At Risk', critical: 'Critical', under_repair: 'Under Repair', retired: 'Retired' };
const CATEGORY_COLORS = ['#0E7C86', '#3FD1C4', '#C98A00', '#C13A50', '#5B5FC7', '#2E8B57', '#8A94A0', '#E0662B'];

async function fetchStat(type) {
    try {
        const res = await fetch(`api/stats.php?type=${type}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return await res.json();
    } catch (err) {
        console.error('PCMS chart fetch failed for', type, err);
        return null;
    }
}

// ---------- low-level canvas helpers ----------
function initCanvas(canvas) {
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.parentElement.getBoundingClientRect();
    const w = Math.max(rect.width, 100);
    const h = Math.max(rect.height, 100);
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    const ctx = canvas.getContext('2d');
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, w, h);
    return { ctx, width: w, height: h };
}

function roundRect(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

function drawEmptyMessage(ctx, width, height, msg = 'No data yet') {
    ctx.fillStyle = '#9AA5B1';
    ctx.font = '13px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(msg, width / 2, height / 2);
}

function drawLegend(ctx, labels, colors, areaX, areaY, areaWidth) {
    ctx.font = '11px Inter, sans-serif';
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'left';
    let x = areaX, y = areaY + 14;
    labels.forEach((label, i) => {
        const textWidth = ctx.measureText(label).width;
        const itemWidth = 16 + textWidth + 20;
        if (x + itemWidth > areaX + areaWidth && x !== areaX) { x = areaX; y += 20; }
        ctx.fillStyle = colors[i % colors.length];
        ctx.beginPath();
        ctx.arc(x + 5, y, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#57626D';
        ctx.fillText(label, x + 14, y + 1);
        x += itemWidth;
    });
    return y + 16;
}

// ---------- chart types ----------
function drawDoughnut(canvasId, labels, values, colors) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const render = () => {
        const { ctx, width, height } = initCanvas(canvas);
        const total = values.reduce((a, b) => a + b, 0);
        const legendRows = Math.ceil(labels.join('').length > 40 ? labels.length / 2 : labels.length / 3);
        const legendHeight = 24 + legendRows * 20;
        const chartH = height - legendHeight;

        if (total === 0) {
            drawEmptyMessage(ctx, width, chartH);
        } else {
            const cx = width / 2, cy = chartH / 2;
            const radius = Math.max(Math.min(cx, cy) - 8, 10);
            const inner = radius * 0.62;
            let start = -Math.PI / 2;
            labels.forEach((label, i) => {
                const val = values[i];
                if (!val) return;
                const angle = (val / total) * Math.PI * 2;
                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.arc(cx, cy, radius, start, start + angle);
                ctx.closePath();
                ctx.fillStyle = colors[i % colors.length];
                ctx.fill();
                start += angle;
            });
            ctx.beginPath();
            ctx.arc(cx, cy, inner, 0, Math.PI * 2);
            ctx.fillStyle = '#FFFFFF';
            ctx.fill();
            ctx.fillStyle = '#1B232C';
            ctx.font = '600 18px "JetBrains Mono", monospace';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(total, cx, cy);
        }
        drawLegend(ctx, labels, colors, 6, chartH, width - 12);
    };
    render();
    window.addEventListener('resize', debounce(render, 150));
}

function drawBarH(canvasId, labels, values, color) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const render = () => {
        const { ctx, width, height } = initCanvas(canvas);
        if (!values.length || values.every(v => v === 0)) { drawEmptyMessage(ctx, width, height); return; }
        const max = Math.max(...values) * 1.15 || 1;
        const leftPad = Math.min(150, width * 0.4);
        const rightPad = 34;
        const topPad = 6;
        const barAreaWidth = Math.max(width - leftPad - rightPad, 20);
        const rowHeight = (height - topPad * 2) / labels.length;
        const barHeight = Math.min(20, rowHeight * 0.55);
        ctx.font = '11px Inter, sans-serif';
        ctx.textBaseline = 'middle';
        labels.forEach((label, i) => {
            const y = topPad + rowHeight * i + rowHeight / 2;
            const barW = (values[i] / max) * barAreaWidth;
            ctx.fillStyle = '#3A4450';
            ctx.textAlign = 'right';
            const text = label.length > 22 ? label.slice(0, 20) + '…' : label;
            ctx.fillText(text, leftPad - 10, y);
            ctx.fillStyle = color;
            roundRect(ctx, leftPad, y - barHeight / 2, Math.max(barW, 2), barHeight, 4);
            ctx.fill();
            ctx.fillStyle = '#67727D';
            ctx.textAlign = 'left';
            ctx.fillText(values[i], leftPad + barW + 8, y);
        });
    };
    render();
    window.addEventListener('resize', debounce(render, 150));
}

function drawLine(canvasId, labels, values, color) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const render = () => {
        const { ctx, width, height } = initCanvas(canvas);
        if (!values.length) { drawEmptyMessage(ctx, width, height); return; }
        const topPad = 16, rightPad = 16, bottomPad = 26, leftPad = 30;
        const chartW = width - leftPad - rightPad;
        const chartH = height - topPad - bottomPad;
        const max = Math.max(...values, 1) * 1.2;

        ctx.strokeStyle = '#EEF1F3';
        ctx.fillStyle = '#9AA5B1';
        ctx.font = '10.5px Inter, sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        const steps = 4;
        for (let s = 0; s <= steps; s++) {
            const y = topPad + chartH - (s / steps) * chartH;
            ctx.beginPath(); ctx.moveTo(leftPad, y); ctx.lineTo(width - rightPad, y); ctx.stroke();
            ctx.fillText(Math.round((s / steps) * max), leftPad - 6, y);
        }

        const stepX = labels.length > 1 ? chartW / (labels.length - 1) : 0;
        const points = values.map((v, i) => ({
            x: leftPad + stepX * i,
            y: topPad + chartH - (v / max) * chartH,
        }));

        ctx.beginPath();
        ctx.moveTo(points[0].x, topPad + chartH);
        points.forEach(p => ctx.lineTo(p.x, p.y));
        ctx.lineTo(points[points.length - 1].x, topPad + chartH);
        ctx.closePath();
        ctx.fillStyle = color + '1A';
        ctx.fill();

        ctx.beginPath();
        points.forEach((p, i) => i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y));
        ctx.strokeStyle = color;
        ctx.lineWidth = 2.2;
        ctx.stroke();

        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        points.forEach((p, i) => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, 3.2, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.fill();
            ctx.fillStyle = '#9AA5B1';
            ctx.font = '10px Inter, sans-serif';
            ctx.fillText(labels[i], p.x, topPad + chartH + 8);
        });
    };
    render();
    window.addEventListener('resize', debounce(render, 150));
}

function drawPie(canvasId, labels, values, colors) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const render = () => {
        const { ctx, width, height } = initCanvas(canvas);
        const total = values.reduce((a, b) => a + b, 0);
        const legendRows = labels.length;
        const legendWidth = Math.min(150, width * 0.4);
        const chartW = width - legendWidth;

        if (total === 0) { drawEmptyMessage(ctx, width, height); return; }

        const cx = chartW / 2, cy = height / 2;
        const radius = Math.max(Math.min(cx, cy) - 10, 10);
        let start = -Math.PI / 2;
        labels.forEach((label, i) => {
            const val = values[i];
            if (!val) return;
            const angle = (val / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, start, start + angle);
            ctx.closePath();
            ctx.fillStyle = colors[i % colors.length];
            ctx.fill();
            start += angle;
        });

        ctx.font = '11px Inter, sans-serif';
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        const startY = height / 2 - (legendRows * 20) / 2 + 10;
        labels.forEach((label, i) => {
            const y = startY + i * 20;
            ctx.fillStyle = colors[i % colors.length];
            ctx.beginPath();
            ctx.arc(chartW + 12, y, 5, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#57626D';
            const text = label.length > 16 ? label.slice(0, 14) + '…' : label;
            ctx.fillText(text, chartW + 22, y + 1);
        });
    };
    render();
    window.addEventListener('resize', debounce(render, 150));
}

function debounce(fn, wait) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}

// ---------- wire up each chart present on the page ----------
(async function initStatusChart() {
    const el = document.getElementById('statusChart');
    if (!el) return;
    const data = await fetchStat('status_distribution');
    if (!data) { drawEmptyMessage(el.getContext('2d'), el.parentElement.clientWidth, el.parentElement.clientHeight, 'Could not load data'); return; }
    const order = ['healthy', 'at_risk', 'critical', 'under_repair', 'retired'];
    const rows = order.map(s => data.find(d => d.status === s) || { status: s, c: 0 });
    drawDoughnut('statusChart', rows.map(r => STATUS_LABELS[r.status]), rows.map(r => Number(r.c)), rows.map(r => PCMS_COLORS[r.status]));
})();

(async function initCausesChart() {
    const el = document.getElementById('causesChart');
    if (!el) return;
    const data = await fetchStat('top_causes');
    if (!data) return;
    drawBarH('causesChart', data.map(d => d.top_prediction), data.map(d => Number(d.c)), PCMS_COLORS.primary);
})();

(async function initCategoryChart() {
    const el = document.getElementById('categoryChart');
    if (!el) return;
    const data = await fetchStat('symptom_categories');
    if (!data) return;
    drawPie('categoryChart', data.map(d => d.category), data.map(d => Number(d.c)), CATEGORY_COLORS);
})();

(async function initTrendChart() {
    const el = document.getElementById('trendChart');
    if (!el) return;
    const data = await fetchStat('monthly_trend');
    if (!data) return;
    drawLine('trendChart', data.map(d => d.ym), data.map(d => Number(d.c)), PCMS_COLORS.primary);
})();

(async function initComplianceChart() {
    const el = document.getElementById('complianceChart');
    if (!el) return;
    const data = await fetchStat('maintenance_compliance');
    if (!data) return;
    drawDoughnut('complianceChart', ['On schedule', 'Overdue'], [Number(data.on_time), Number(data.overdue)], [PCMS_COLORS.healthy, PCMS_COLORS.critical]);
})();
