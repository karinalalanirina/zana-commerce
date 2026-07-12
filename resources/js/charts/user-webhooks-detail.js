import ApexCharts from 'apexcharts';

/*
 * /webhooks/{id} detail page.
 *   - #chart-activity  data-series='[{date, success, failure}, …]'  (last 7d)
 *   - #chart-codes     data-codes='[{label, count, cls}, …]'         (filtered)
 *   - .del-row         carries data-* attrs for the payload pane swap
 */
export default function init() {
    /* ── Activity chart (success vs failure stacked area, last 7d) ─── */
    const actEl = document.getElementById('chart-activity');
    if (actEl) {
        let days = [];
        try { days = JSON.parse(actEl.dataset.series || '[]'); } catch (_) { days = []; }
        const labels  = days.map(d => {
            try {
                const [, m, day] = (d.date || '').split('-');
                return new Date(2000, +m - 1, +day).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
            } catch (_) { return d.date || ''; }
        });
        const success = days.map(d => +d.success || 0);
        const failure = days.map(d => +d.failure || 0);

        new ApexCharts(actEl, {
            chart:  { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans', stacked: true },
            series: [
                { name: 'Success', data: success.length ? success : Array(7).fill(0) },
                { name: 'Failed',  data: failure.length ? failure : Array(7).fill(0) },
            ],
            xaxis: {
                categories: labels.length ? labels : Array.from({ length: 7 }, (_, i) => `D${i+1}`),
                labels: { style: { colors: '#6B807C', fontSize: '10px', fontFamily: 'JetBrains Mono' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis:    { labels: { style: { colors: '#6B807C', fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            colors:   ['#075E54', '#E87A5D'],
            stroke:   { curve: 'smooth', width: 2 },
            fill:     { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
            grid:     { borderColor: '#EFEBE0', strokeDashArray: 3 },
            dataLabels: { enabled: false },
            legend:   { position: 'top', horizontalAlign: 'right', fontSize: '11px', fontFamily: 'JetBrains Mono', labels: { colors: '#3A5A55' } },
            noData:   { text: 'No deliveries in the last 7 days.', style: { color: '#6B807C', fontSize: '12px' } },
            tooltip:  { y: { formatter: v => v + ' fires' } },
        }).render();
    }

    /* ── Status codes donut ─────────────────────────────────────────── */
    const codeEl = document.getElementById('chart-codes');
    if (codeEl) {
        let codes = [];
        try { codes = JSON.parse(codeEl.dataset.codes || '[]'); } catch (_) { codes = []; }
        const labels = codes.map(c => c.label);
        const counts = codes.map(c => +c.count || 0);
        const colorMap = { '200 OK': '#075E54', '4xx': '#E5A04E', '5xx': '#E87A5D', 'other': '#D9CFB7' };
        const colors  = labels.map(l => colorMap[l] || '#075E54');
        const total   = counts.reduce((a, b) => a + b, 0);

        new ApexCharts(codeEl, {
            chart:  { type: 'donut', height: 200, fontFamily: 'Plus Jakarta Sans' },
            series: counts.length ? counts : [1],
            labels: counts.length ? labels : ['No data'],
            colors: counts.length ? colors : ['#EFEBE0'],
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: {
                                show: true, label: 'Total',
                                fontFamily: 'JetBrains Mono', fontSize: '11px', color: '#6B807C',
                                formatter: () => total.toString(),
                            },
                            value: { fontFamily: 'Fraunces', fontSize: '22px', color: '#0B1F1C' },
                        },
                    },
                },
            },
            stroke: { width: 2, colors: ['#FBFAF6'] },
        }).render();
    }

    /* ── Payload tabs (Body / Headers / Response) ───────────────────── */
    document.querySelectorAll('#p-tabs .p-tab').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#p-tabs .p-tab').forEach(x => {
                x.classList.remove('bg-wa-deep', 'text-paper-0');
                x.classList.add('text-ink-600', 'hover:bg-paper-100');
            });
            b.classList.add('bg-wa-deep', 'text-paper-0');
            b.classList.remove('text-ink-600', 'hover:bg-paper-100');
            document.querySelectorAll('.p-pane').forEach(p => p.classList.add('hidden'));
            document.getElementById('p-' + b.dataset.pane)?.classList.remove('hidden');
        });
    });

    /* ── Delivery row → update inspector pane (real data) ───────────── */
    const apply = (tr) => {
        if (!tr) return;
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v ?? '—'; };
        set('p-event',   tr.dataset.event);
        set('p-code',    tr.dataset.codeLabel);
        set('p-latency', tr.dataset.latency);
        set('p-attempt', tr.dataset.attempt);
        set('p-time',    tr.dataset.time);

        const body = document.getElementById('p-body');
        const resp = document.getElementById('p-response');
        if (body) body.textContent = tr.dataset.payload  || '(no payload recorded)';
        if (resp) resp.textContent = tr.dataset.response || tr.dataset.error || '(no response captured)';

        document.querySelectorAll('.del-row').forEach(r => r.classList.remove('bg-wa-mint/30'));
        tr.classList.add('bg-wa-mint/30');
    };
    const rows = document.querySelectorAll('.del-row');
    rows.forEach(tr => tr.addEventListener('click', () => apply(tr)));
    if (rows.length) apply(rows[0]); // first row pre-selected
}
