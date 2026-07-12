import ApexCharts from 'apexcharts';

export default function init() {
    const sparkEl = document.querySelector('#kpi-spark');
    if (sparkEl) {
        new ApexCharts(sparkEl, {
            chart: { type: 'bar', height: 32, sparkline: { enabled: true }, animations: { enabled: true, speed: 600 } },
            series: [{ name: 'Sent/hr', data: [18, 22, 16, 26, 20, 24, 28, 22, 18, 26, 20, 30, 24, 18, 22, 26, 20, 28, 30, 32] }],
            plotOptions: {
                bar: {
                    columnWidth: '60%',
                    borderRadius: 2,
                    distributed: true,
                    colors: {
                        ranges: [
                            { from: 0, to: 27, color: '#128C7E' },
                            { from: 28, to: 99, color: '#25D366' },
                        ],
                    },
                },
            },
            dataLabels: { enabled: false },
            tooltip: { enabled: true, x: { show: false }, y: { formatter: (v) => v + 'k msg/h' } },
            states: { hover: { filter: { type: 'darken', value: 0.92 } } },
        }).render();
    }

    const readRateEl = document.querySelector('#kpi-readrate');
    if (readRateEl) {
        new ApexCharts(readRateEl, {
            chart: { type: 'radialBar', height: 64, width: 64, sparkline: { enabled: true } },
            colors: ['#075E54'],
            series: [82.6],
            plotOptions: {
                radialBar: {
                    hollow: { size: '58%' },
                    track: { background: 'rgba(11,31,28,0.06)', strokeWidth: '100%' },
                    dataLabels: {
                        name: { show: false },
                        value: {
                            show: true,
                            fontSize: '10px',
                            fontFamily: 'JetBrains Mono, monospace',
                            fontWeight: 600,
                            color: '#075E54',
                            offsetY: 4,
                            formatter: (v) => v.toFixed(1),
                        },
                    },
                },
            },
            stroke: { lineCap: 'round' },
        }).render();
    }

    const throughputEl = document.querySelector('#chart-throughput');
    if (throughputEl) {
        new ApexCharts(throughputEl, {
            chart: {
                type: 'bar',
                height: 220,
                fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif',
                toolbar: { show: false },
                animations: { enabled: true, speed: 500 },
            },
            colors: ['#075E54', '#25D366', '#E87A5D'],
            series: [
                { name: 'Sent',      data: [4500, 9800, 13500, 18421, 11500, 14500, 7800, 6500] },
                { name: 'Delivered', data: [4350, 9500, 13100, 17902, 11150, 14100, 7550, 6280] },
                { name: 'Failed',    data: [150, 300, 400, 519, 350, 400, 250, 220] },
            ],
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#E5DFD0', strokeDashArray: 3 },
            xaxis: {
                categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Mon'],
                labels: { style: { fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', colors: '#6B807C' } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    formatter: (v) => (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v),
                    style: { fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', colors: '#6B807C' },
                },
            },
            legend: { show: false },
            tooltip: { shared: true, intersect: false, y: { formatter: (v) => v.toLocaleString() + ' msg' } },
        }).render();
    }
}
