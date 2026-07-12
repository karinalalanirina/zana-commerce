import ApexCharts from 'apexcharts';

export default function init() {
    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const grid = { borderColor: '#EFEBE0', strokeDashArray: 4 };
    const label = { colors: '#6B807C', fontSize: '11px' };

    const data = window.adminFinancial || {};
    const revenue  = data.revenueDaily || { labels: [], series: [] };
    const refunds  = data.refundsDaily || { labels: [], series: [] };
    const gateways = data.gateways     || { labels: [], series: [] };
    const status   = data.statusMix    || { labels: [], series: [] };

    const revEl = document.querySelector('#financial-revenue-chart');
    if (revEl) {
        new ApexCharts(revEl, {
            chart: { type: 'area', height: 280, toolbar: { show: false }, ...baseFont },
            series: [
                { name: 'Revenue', data: revenue.series },
                { name: 'Refunds', data: refunds.series },
            ],
            colors: ['#075E54', '#E5A04E'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid,
            xaxis: { categories: revenue.labels, labels: { style: label } },
            yaxis: { labels: { style: label, formatter: (v) => (window.WA_CURRENCY || '$') + Number(v).toLocaleString() } },
            legend: { position: 'top', fontSize: '11px' },
        }).render();
    }

    const gwEl = document.querySelector('#financial-gateway-chart');
    if (gwEl) {
        const series = (gateways.series || []).map(Number);
        new ApexCharts(gwEl, {
            chart: { type: 'donut', height: 260, ...baseFont },
            labels: gateways.labels.length ? gateways.labels : ['No data'],
            series: series.length ? series : [1],
            colors: ['#075E54', '#D9E5F2', '#E5A04E', '#F3E9FF', '#FFF4E0'],
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '11px' },
            stroke: { colors: ['#FBFAF6'] },
        }).render();
    }

    const stEl = document.querySelector('#financial-status-chart');
    if (stEl) {
        const series = (status.series || []).map(Number);
        new ApexCharts(stEl, {
            chart: { type: 'donut', height: 260, ...baseFont },
            labels: status.labels.length ? status.labels : ['No data'],
            series: series.length ? series : [1],
            colors: ['#075E54', '#E5A04E', '#E76A6A', '#5B3D8A', '#A0AEC0'],
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '11px' },
            stroke: { colors: ['#FBFAF6'] },
        }).render();
    }
}
