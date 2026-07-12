import ApexCharts from 'apexcharts';

export default function init() {
    const data = window.adminOrderAnalytics || {};
    const motion  = data.motion  || { labels: [], new: [], cancel: [] };
    const typeMix = data.typeMix || { labels: ['Renewal','Add-on','Cancel'], series: [0,0,0] };

    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const grid     = { borderColor: '#EFEBE0', strokeDashArray: 4 };
    const label    = { colors: '#6B807C', fontSize: '11px' };

    const motionEl = document.querySelector('#chart-motion');
    if (motionEl) {
        new ApexCharts(motionEl, {
            chart: { type: 'bar', height: 280, toolbar: { show: false }, ...baseFont, stacked: true },
            series: [
                { name: 'New',    data: motion.new },
                // Show cancels as negative bars (downward direction) so they read like churn.
                { name: 'Cancel', data: (motion.cancel || []).map((v) => -Math.abs(v)) },
            ],
            colors: ['#075E54', '#E87A5D'],
            plotOptions: { bar: { columnWidth: '60%', borderRadius: 4 } },
            dataLabels: { enabled: false },
            grid,
            xaxis: {
                categories: motion.labels,
                tickAmount: Math.min(14, motion.labels.length),
                labels: { style: label, rotate: -30, hideOverlappingLabels: true, trim: true, maxHeight: 60 },
            },
            yaxis: { labels: { style: label } },
            legend: { show: false },
        }).render();
    }

    const typeEl = document.querySelector('#chart-type');
    if (typeEl) {
        const series = (typeMix.series || []).map(Number);
        new ApexCharts(typeEl, {
            chart: { type: 'donut', height: 200, ...baseFont },
            labels: typeMix.labels.length ? typeMix.labels : ['No data'],
            series: series.length ? series : [1],
            colors: ['#075E54', '#13478A', '#E87A5D'],
            dataLabels: { enabled: false },
            legend: { show: false },
            stroke: { colors: ['#FBFAF6'], width: 2 },
        }).render();
    }
}
