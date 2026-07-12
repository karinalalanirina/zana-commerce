import ApexCharts from 'apexcharts';
import { wireTimezonePicker } from '../lib/tz-picker.js';

export default function init() {
    // Edit-panel toggle — every [data-edit-toggle] flips visibility on #edit-panel.
    const panel = document.getElementById('edit-panel');
    document.querySelectorAll('[data-edit-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!panel) return;
            panel.classList.toggle('hidden');
            if (!panel.classList.contains('hidden')) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Searchable timezone picker (same pattern as /account?tab=profile + workspace create).
    wireTimezonePicker('#ws-edit-tz');

    const data = window.adminWorkspaceDetail || { volume: { labels: [], sent: [], delivered: [] } };
    const v = data.volume;
    const el = document.getElementById('chart-volume');
    if (el) {
        new ApexCharts(el, {
            chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' },
            series: [
                { name: 'Sent', data: v.sent },
                { name: 'Delivered', data: v.delivered },
            ],
            colors: ['#075E54', '#0F8C7B'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#EFEBE0', strokeDashArray: 4 },
            xaxis: { categories: v.labels, labels: { style: { colors: '#6B807C', fontSize: '11px' } } },
            yaxis: { labels: { style: { colors: '#6B807C', fontSize: '11px' }, formatter: (n) => Number(n).toLocaleString() } },
            legend: { show: false },
        }).render();
    }
}
