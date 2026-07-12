import ApexCharts from 'apexcharts';

export default function init() {
    // Volume chart — daily over 7 days, sent + failed stacked
      new ApexCharts(document.querySelector('#chart-volume'), {
        chart: { type:'bar', height:260, toolbar:{show:false}, fontFamily:'Plus Jakarta Sans', stacked:true },
        series: [
          { name:'Sent', data:[2840, 3128, 2942, 3284, 3412, 3158, 3120] },
          { name:'Failed', data:[18, 22, 16, 28, 24, 14, 10] }
        ],
        xaxis: { categories:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], labels:{style:{colors:'#6B807C',fontSize:'10px',fontFamily:'JetBrains Mono'}}, axisBorder:{show:false}, axisTicks:{show:false} },
        yaxis: { labels:{style:{colors:'#6B807C',fontSize:'10px',fontFamily:'JetBrains Mono'}} },
        colors: ['#075E54','#E87A5D'],
        plotOptions: { bar: { borderRadius:6, columnWidth:'48%' } },
        dataLabels: { enabled:false },
        grid: { borderColor:'#EFEBE0', strokeDashArray:3 },
        legend: { position:'top', horizontalAlign:'right', fontSize:'11px', fontFamily:'JetBrains Mono', labels:{colors:'#3A5A55'} },
        tooltip:{ y:{formatter:v=>v.toLocaleString()+' msg'} }
      }).render();

      // Status donut
      new ApexCharts(document.querySelector('#chart-status'), {
        chart: { type:'donut', height:200, fontFamily:'Plus Jakarta Sans' },
        series: [14839, 6913, 0, 132],
        labels: ['Read','Delivered','Pending','Failed'],
        colors: ['#075E54','#128C7E','#E5A04E','#E87A5D'],
        legend: { show:false },
        dataLabels: { enabled:false },
        plotOptions: { pie: { donut: { size:'68%', labels:{ show:true, total:{show:true, label:'Total', fontFamily:'JetBrains Mono', fontSize:'11px', color:'#6B807C', formatter:()=>'21,884'}, value:{fontFamily:'Fraunces', fontSize:'22px', color:'#0B1F1C'} } } } },
        stroke: { width:2, colors:['#FBFAF6'] }
      }).render();

      // Heatmap (24h × 7d)
      const days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
      const heatSeries = days.map(d => ({
        name: d,
        data: Array.from({length:24},(_,h)=>{
          const peak = h>=9 && h<=20 ? 1 : 0.25;
          const wknd = (d==='Sat'||d==='Sun') ? 0.55 : 1;
          return { x: String(h).padStart(2,'0'), y: Math.round(Math.random()*60*peak*wknd) };
        })
      }));
      new ApexCharts(document.querySelector('#chart-heatmap'), {
        chart: { type:'heatmap', height:260, toolbar:{show:false}, fontFamily:'Plus Jakarta Sans' },
        series: heatSeries,
        colors: ['#075E54'],
        plotOptions: { heatmap: { radius:3, colorScale:{ ranges:[
          { from:0, to:8, color:'#F5F3EC', name:'idle' },
          { from:9, to:20, color:'#DCF8C6', name:'low' },
          { from:21, to:35, color:'#7FCDB9', name:'mid' },
          { from:36, to:60, color:'#075E54', name:'high' }
        ] } } },
        dataLabels: { enabled:false },
        xaxis: { labels:{style:{colors:'#6B807C',fontSize:'9px',fontFamily:'JetBrains Mono'}}, axisBorder:{show:false}, axisTicks:{show:false} },
        yaxis: { labels:{style:{colors:'#6B807C',fontSize:'10px',fontFamily:'JetBrains Mono'}} },
        grid: { padding:{ top:0, right:0, bottom:0, left:0 } },
        legend: { show:false }
      }).render();
}
