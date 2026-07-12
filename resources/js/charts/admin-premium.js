import ApexCharts from 'apexcharts';

export default function init() {
    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const grid = { borderColor: '#EFEBE0', strokeDashArray: 4 };
    const label = { colors: '#6B807C', fontSize: '11px' };

    const data = window.adminPremium || {};
    const mix     = data.planMix       || { labels: [], series: [] };
    const revenue = data.planRevenue   || { labels: [], series: [] };
    const ups     = data.upgradesDaily || { labels: [], series: [] };

    const mixEl = document.querySelector('#premium-mix-chart');
    if (mixEl) {
        const series = (mix.series || []).map(Number);
        new ApexCharts(mixEl, {
            chart: { type: 'donut', height: 280, ...baseFont },
            labels: mix.labels.length ? mix.labels : ['No plans'],
            series: series.length ? series : [1],
            colors: ['#075E54', '#0F8C7B', '#D9E5F2', '#E5A04E', '#F3E9FF', '#FFF4E0'],
            dataLabels: { enabled: false },
            legend: { position: 'right', fontSize: '11px' },
            stroke: { colors: ['#FBFAF6'] },
        }).render();
    }

    const revEl = document.querySelector('#premium-revenue-chart');
    if (revEl) {
        new ApexCharts(revEl, {
            chart: { type: 'bar', height: 280, toolbar: { show: false }, ...baseFont },
            series: [{ name: 'Revenue', data: revenue.series }],
            colors: ['#075E54'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            dataLabels: { enabled: false },
            grid,
            xaxis: { categories: revenue.labels, labels: { style: label } },
            yaxis: { labels: { style: label, formatter: (v) => (window.WA_CURRENCY || '$') + Number(v).toLocaleString() } },
        }).render();
    }

    const upEl = document.querySelector('#premium-upgrades-chart');
    if (upEl) {
        new ApexCharts(upEl, {
            chart: { type: 'area', height: 260, toolbar: { show: false }, ...baseFont },
            series: [{ name: 'New paid signups', data: ups.series }],
            colors: ['#075E54'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid,
            xaxis: { categories: ups.labels, labels: { style: label } },
            yaxis: { labels: { style: label } },
        }).render();
    }
}
