/**
 * /admin/packages/analytics/overview — area + donut.
 *
 * Both chart divs carry their series + categories as JSON in
 * data-* attributes written by the Blade. Empty data → "no data
 * yet" placeholder instead of misleading prototype numbers.
 */
export default async function init() {
    const A = await import('apexcharts').then((m) => m.default).catch(() => null);
    if (!A) return;

    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const grid     = { borderColor: '#EFEBE0', strokeDashArray: 4 };
    const label    = { colors: '#6B807C', fontSize: '11px' };
    const json     = (el, k) => {
        try { return JSON.parse(el.getAttribute('data-' + k) || '[]'); }
        catch (_) { return []; }
    };

    // ── MRR area chart ─────────────────────────────────────────────
    const mrrEl = document.querySelector('#chart-mrr');
    if (mrrEl) {
        const series     = json(mrrEl, 'series');
        const categories = json(mrrEl, 'categories');
        const hasData    = Array.isArray(series) && series.some((s) => (s.data || []).some((v) => v > 0));
        if (hasData) {
            new A(mrrEl, {
                chart:   { type: 'area', height: 260, toolbar: { show: false }, ...baseFont, stacked: true },
                series,
                colors:  ['#075E54', '#13478A', '#E5A04E'],
                stroke:  { curve: 'smooth', width: 2 },
                fill:    { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.30, opacityTo: 0.04 } },
                dataLabels: { enabled: false },
                grid,
                xaxis:   { categories, labels: { style: label } },
                yaxis:   { labels: { style: label, formatter: (v) => (window.WA_CURRENCY || '$') + Number(v).toFixed(0) + 'k' } },
                legend:  { show: false },
            }).render();
        } else {
            mrrEl.innerHTML = '<div class="h-full w-full flex items-center justify-center text-[12px] text-ink-500">No revenue in the selected window.</div>';
        }
    }

    // ── Plan-share donut ───────────────────────────────────────────
    const shareEl = document.querySelector('#chart-share');
    if (shareEl) {
        const labels = json(shareEl, 'labels');
        const series = json(shareEl, 'series');
        const hasData = Array.isArray(series) && series.some((n) => n > 0);
        if (hasData) {
            new A(shareEl, {
                chart:    { type: 'donut', height: 200, ...baseFont },
                labels, series,
                colors:   ['#075E54', '#13478A', '#E5A04E', '#D6CDB6', '#E87A5D'],
                dataLabels: { enabled: false },
                legend:   { show: false },
                stroke:   { colors: ['#FBFAF6'], width: 2 },
            }).render();
        } else {
            shareEl.innerHTML = '<div class="h-full w-full flex items-center justify-center text-[12px] text-ink-500">No subscribers yet.</div>';
        }
    }
}
