'use strict';

/* Pemilih tema: auto / terang / gelap, tersimpan di localStorage. */
(() => {
  const KEY = 'aduan-dashboard-theme';
  const control = document.getElementById('theme-select');
  const system = matchMedia('(prefers-color-scheme: dark)');
  let preference = 'auto';
  try { preference = localStorage.getItem(KEY) || 'auto'; } catch {}

  const apply = () => {
    document.documentElement.dataset.theme = preference === 'auto' ? (system.matches ? 'dark' : 'light') : preference;
    control.value = preference;
  };
  control.addEventListener('change', () => {
    preference = control.value;
    try { localStorage.setItem(KEY, preference); } catch {}
    apply();
  });
  system.addEventListener('change', () => { if (preference === 'auto') apply(); });
  apply();
})();

/* Konfirmasi sebelum arsip, dan kirim otomatis filter yang berubah. */
document.querySelectorAll('[data-confirm]').forEach(form =>
  form.addEventListener('submit', e => { if (!confirm(form.dataset.confirm)) e.preventDefault(); }));
document.querySelectorAll('[data-autosubmit]').forEach(el =>
  el.addEventListener('change', () => el.form.submit()));

/* ---------- Utilitas bersama ---------- */
const $ = id => document.getElementById(id);
const nfmt = new Intl.NumberFormat('id-ID');
const fmt = n => nfmt.format(n);
const pct = (n, total) => total ? `${(n / total * 100).toFixed(1).replace('.', ',')}%` : '0,0%';
const escapeHtml = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function options(values, current) { return values.map(([value, label]) => `<option value="${escapeHtml(value)}" ${value === current ? 'selected' : ''}>${escapeHtml(label)}</option>`).join(''); }

/* ---------- Dialog detail (aduan, kamera) ---------- */
const dialog = $('detail-dialog');
function showDialog(title, html) {
  $('dialog-title').textContent = title;
  $('dialog-content').innerHTML = html;
  if (!dialog.open) dialog.showModal();
}
$('close-dialog')?.addEventListener('click', () => dialog.close());
dialog?.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
dialog?.addEventListener('close', () => $('dialog-content').querySelectorAll('video').forEach(v => v.pause()));
document.addEventListener('click', e => {
  const report = e.target.closest('[data-report]');
  if (report) {
    const tpl = document.getElementById('detail-' + report.dataset.report);
    if (tpl) showDialog(tpl.dataset.title, tpl.innerHTML);
    return;
  }
  const cam = e.target.closest('[data-camera]');
  if (cam) {
    const tpl = document.getElementById('camera-' + cam.dataset.camera);
    if (tpl) showDialog(tpl.dataset.title, tpl.innerHTML);
    return;
  }
  const profile = e.target.closest('[data-profile]');
  if (profile) {
    const tpl = document.getElementById('profile-' + profile.dataset.profile);
    if (tpl) showDialog(tpl.dataset.title, tpl.innerHTML);
  }
});
/* Hanya satu video CCTV yang diputar pada satu waktu. */
document.addEventListener('play', e => {
  if (!e.target.matches?.('video[data-cctv]')) return;
  document.querySelectorAll('video[data-cctv]').forEach(v => { if (v !== e.target) v.pause(); });
}, true);

/* ---------- Grafik tren (diport dari dashboard asli; data dari PHP) ---------- */
if (window.TREND_CONFIG && $('trend-panel')) (() => {
  const CFG = window.TREND_CONFIG;
  const currentView = CFG.view;
  const DATA = { cameras: { length: CFG.cameraTotal || 0 } };
  const chartPreferences = new Map();
  let currentChart = null;
function chartPreference(){if(!chartPreferences.has(currentView))chartPreferences.set(currentView,{month:'all',mode:'daily',hidden:[],type:['distribusi','cctv'].includes(currentView)?'stacked':['ringkasan','kepuasan','progress-laporan'].includes(currentView)?'area':'columns'});return chartPreferences.get(currentView);}
function dateLabel(date,short=false){return new Intl.DateTimeFormat('id-ID',{day:'numeric',month:'short',...(!short?{year:'numeric'}:{ }),timeZone:'UTC'}).format(new Date(date+'T00:00:00Z'));}
function lineValue(value,unit){return Number.isInteger(value)?fmt(value):value.toLocaleString('id-ID',{maximumFractionDigits:2});}
  function trendSource() {
    const getters = {
      total: r => r.total || 0, done: r => r.done || 0,
      progress: r => r.total ? r.progressSum / r.total : 0,
      online: r => r.online, offline: r => r.total - r.online,
      score: r => r.responses ? r.scoreSum / r.responses : 0,
    };
    return {
      rows: CFG.rows, title: CFG.title, description: CFG.description, unit: CFG.unit, max: CFG.max, cumulative: CFG.cumulative,
      series: CFG.series.map(s => ({ key: s.key, label: s.label, color: s.color, get: getters[s.getter] || (r => r.categories?.[s.key] || 0) })),
    };
  }
function prepareTrend(source,preference){
 const rows=source.rows.filter(r=>preference.month==='all'||r.date.startsWith(preference.month)).sort((a,b)=>a.date.localeCompare(b.date));
 if(!rows.length)return [];
 const start=Date.parse(rows[0].date+'T00:00:00Z'),end=Date.parse(rows[rows.length-1].date+'T00:00:00Z'),dayMs=86400000;
 // Bound SVG complexity even if an edited report has a distant date.
 const step=Math.max(1,Math.ceil(((end-start)/dayMs+1)/90));const bins=[];
 for(let timestamp=start;timestamp<=end;timestamp+=step*dayMs){const date=new Date(timestamp).toISOString().slice(0,10);bins.push({date,end:new Date(Math.min(end,timestamp+(step-1)*dayMs)).toISOString().slice(0,10),rows:[],values:[]});}
 for(const row of rows){const index=Math.min(bins.length-1,Math.floor((Date.parse(row.date+'T00:00:00Z')-start)/dayMs/step));bins[index].rows.push(row);}
 for(const bin of bins)bin.values=source.series.map(series=>{
   if(source.unit==='skor / 5'){const count=bin.rows.reduce((s,r)=>s+r.responses,0);return count?bin.rows.reduce((s,r)=>s+r.scoreSum,0)/count:null;}
   if(source.unit==='%'){const count=bin.rows.reduce((s,r)=>s+r.total,0);return count?bin.rows.reduce((s,r)=>s+r.progressSum,0)/count:null;}
   if(source.unit==='kamera')return bin.rows.length?bin.rows.reduce((s,r)=>s+series.get(r),0)/bin.rows.length:null;
   return bin.rows.reduce((s,r)=>s+series.get(r),0);
 });
 if(source.cumulative&&preference.mode==='cumulative'){const sums=source.series.map(()=>0);for(const bin of bins)bin.values=bin.values.map((value,index)=>(sums[index]+=value||0));}
 return bins;
}
function chartMetrics(source,bins){
 if(source.unit==='skor / 5'){const rows=bins.flatMap(b=>b.rows);const responses=rows.reduce((s,r)=>s+r.responses,0),score=rows.reduce((s,r)=>s+r.scoreSum,0);const peak=bins.filter(b=>b.values[0]!==null).reduce((best,b)=>!best||b.values[0]>best.values[0]?b:best,null);return [{label:'Rata-rata periode',value:responses?lineValue(score/responses)+' / 5':'—',color:'purple'},{label:'Respons periode',value:fmt(responses),color:'blue'},{label:'Skor harian tertinggi',value:peak?lineValue(peak.values[0])+' / 5':'—',color:'green'}];}
 if(source.unit==='kamera'){const online=bins.filter(b=>b.values[0]!==null),average=online.length?online.reduce((s,b)=>s+b.values[0],0)/online.length:0;return [{label:'Rata-rata kamera online',value:lineValue(average)+' / '+fmt(DATA.cameras.length),color:'blue'},{label:'Ketersediaan rata-rata',value:pct(average,DATA.cameras.length),color:'green'},{label:'Hari simulasi',value:fmt(bins.length),color:'purple'}];}
 const rows=bins.flatMap(b=>b.rows),total=rows.reduce((s,r)=>s+r.total,0),done=rows.reduce((s,r)=>s+r.done,0);const peak=rows.reduce((best,r)=>!best||r.total>best.total?r:best,null);
 if(source.unit==='%')return [{label:'Progress rata-rata',value:total?lineValue(rows.reduce((s,r)=>s+r.progressSum,0)/total)+'%':'—',color:'green'},{label:'Aduan selesai',value:pct(done,total),color:'blue'},{label:'Aduan periode',value:fmt(total),color:'purple'}];
 return [{label:'Aduan periode',value:fmt(total),color:'blue'},{label:'Rasio selesai',value:pct(done,total),color:'green'},{label:'Hari terpadat',value:peak?fmt(peak.total)+' · '+dateLabel(peak.date,true):'—',color:'purple'}];
}
function renderTrendCharts(){
 const root=$('trend-panel');root.hidden=currentView==='kelola-pelapor';if(root.hidden){root.innerHTML='';return;}
 const source=trendSource(),preference=chartPreference();const months=Array.from(new Set(source.rows.map(r=>r.date.slice(0,7)))).sort();
 if(preference.month!=='all'&&!months.includes(preference.month))preference.month='all';
 const bins=prepareTrend(source,preference);currentChart={source,bins,preference};
 root.innerHTML=`<div class="trend-heading"><div><span class="trend-eyebrow">INSIGHT & TREN</span><h3>${escapeHtml(source.title)}</h3><p class="small muted">${escapeHtml(source.description)}</p></div><div class="trend-controls"><div class="field"><label for="chart-type">Jenis grafik</label><select id="chart-type">${options([['area','Area Gradasi'],['columns','Batang Berkelompok'],...(['distribusi','cctv'].includes(currentView)?[['stacked','Batang Bertumpuk']]:[])],preference.type)}</select></div><div class="field"><label for="chart-month">Periode grafik</label><select id="chart-month">${options([['all','Semua periode'],...months.map(month=>[month,new Intl.DateTimeFormat('id-ID',{month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(month+'-01T00:00:00Z'))])],preference.month)}</select></div>${source.cumulative?`<div class="field"><label for="chart-mode">Tampilan</label><select id="chart-mode">${options([['daily','Harian'],['cumulative','Kumulatif']],preference.mode)}</select></div>`:''}</div></div><div class="trend-metrics">${chartMetrics(source,bins).map(m=>`<div class="trend-metric ${m.color}"><span>${escapeHtml(m.label)}</span><strong>${escapeHtml(m.value)}</strong></div>`).join('')}</div><div class="trend-legend" aria-label="Seri grafik">${source.series.map(series=>`<button class="legend-toggle" type="button" data-series="${series.key}" aria-pressed="${!preference.hidden.includes(series.key)}"><span class="legend-dot" style="background:${series.color}" aria-hidden="true"></span>${escapeHtml(series.label)}</button>`).join('')}</div><div class="chart-scroll"><div id="line-chart"></div></div><div id="chart-readout" class="chart-readout" role="status" aria-live="polite">Arahkan kursor, sentuh, atau pilih titik grafik dengan keyboard untuk melihat nilainya.</div><details class="chart-table"><summary>Lihat data grafik dalam tabel</summary><div class="table-wrap"><table><caption class="small muted" style="padding:10px;text-align:left">Angka yang digunakan untuk menggambar grafik</caption><thead><tr><th scope="col">Tanggal / interval</th>${source.series.map(s=>`<th scope="col">${escapeHtml(s.label)}</th>`).join('')}</tr></thead><tbody>${bins.map(bin=>`<tr><td>${escapeHtml(dateLabel(bin.date))}${bin.end!==bin.date?' – '+escapeHtml(dateLabel(bin.end)):''}</td>${bin.values.map(value=>`<td>${value===null?'Tidak ada data':lineValue(value)}</td>`).join('')}</tr>`).join('')||`<tr><td colspan="${source.series.length+1}" class="empty">Tidak ada data pada periode dan filter ini.</td></tr>`}</tbody></table></div></details>`;
 drawLineChart();
}
function drawLineChart(){
 const {source,bins,preference}=currentChart;const visible=source.series.map((series,index)=>({...series,index})).filter(series=>!preference.hidden.includes(series.key));
 if(!bins.length){$('line-chart').innerHTML='<div class="empty">Tidak ada data untuk ditampilkan. Ubah periode atau filter aduan.</div>';return;}
 if(!visible.length){$('line-chart').innerHTML='<div class="empty">Aktifkan minimal satu seri pada legenda untuk menampilkan grafik.</div>';return;}
 if(preference.type==='columns'||preference.type==='stacked'){drawBarChart(visible);return;}
 const width=940,height=290,pad={left:58,right:24,top:30,bottom:46},plotW=width-pad.left-pad.right,plotH=height-pad.top-pad.bottom;
 const values=bins.flatMap(b=>visible.map(s=>b.values[s.index])).filter(v=>v!==null);const max=source.max||Math.max(5,Math.ceil(Math.max(0,...values)/5)*5);
 const x=index=>pad.left+(bins.length===1?plotW/2:index/(bins.length-1)*plotW),y=value=>pad.top+plotH-(value/max)*plotH;
 let svg=`<svg class="line-svg" viewBox="0 0 ${width} ${height}" role="group" aria-label="${escapeHtml(source.title)}"><title>${escapeHtml(source.title)}</title><desc>${escapeHtml(source.description)} Nilai dapat dilihat pada tabel di bawah grafik.</desc><defs>${visible.map(s=>`<linearGradient id="trend-area-${s.key}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="${s.color}" stop-opacity=".18"/><stop offset="100%" stop-color="${s.color}" stop-opacity=".015"/></linearGradient>`).join('')}</defs><text x="${pad.left}" y="15" fill="#64748b" font-size="12">${escapeHtml(source.unit)}</text>`;
 for(let i=0;i<=4;i++){const value=max*i/4;svg+=`<line x1="${pad.left}" y1="${y(value)}" x2="${width-pad.right}" y2="${y(value)}" stroke="#e2e8f0" stroke-dasharray="4 5"/><text x="${pad.left-10}" y="${y(value)+4}" text-anchor="end" fill="#64748b" font-size="12">${lineValue(value)}</text>`;}
 const tickStep=Math.max(1,Math.ceil((bins.length-1)/6));bins.forEach((b,i)=>{if(i%tickStep===0||i===bins.length-1)svg+=`<text x="${x(i)}" y="${height-13}" text-anchor="middle" fill="#64748b" font-size="12">${escapeHtml(dateLabel(b.date,true))}</text>`;});
 for(const series of visible){let segment=[];const drawSegment=()=>{if(!segment.length)return;const path=segment.map(([px,py],i)=>`${i?'L':'M'}${px.toFixed(2)},${py.toFixed(2)}`).join(' ');const first=segment[0],last=segment[segment.length-1];svg+=`<path d="${path} L${last[0]},${y(0)} L${first[0]},${y(0)} Z" fill="url(#trend-area-${series.key})"/><path d="${path}" fill="none" stroke="${series.color}" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>`;segment=[];};bins.forEach((bin,i)=>{const value=bin.values[series.index];if(value===null)drawSegment();else segment.push([x(i),y(value)]);});drawSegment();}
 // One focusable hit target per date, shared across series for touch and keyboard.
 bins.forEach((bin,i)=>{const valid=visible.filter(s=>bin.values[s.index]!==null);if(!valid.length)return;const label=dateLabel(bin.date)+': '+valid.map(s=>s.label+' '+lineValue(bin.values[s.index])+' '+source.unit).join(', ');svg+=`<g class="chart-point" tabindex="0" role="button" aria-label="${escapeHtml(label)}" data-point="${i}"><rect x="${x(i)-Math.min(16,plotW/Math.max(1,bins.length)/2)}" y="${pad.top}" width="${Math.min(32,plotW/Math.max(1,bins.length))}" height="${plotH}" fill="transparent"/>${valid.map(s=>`<circle cx="${x(i)}" cy="${y(bin.values[s.index])}" r="${bins.length===1?5:3.5}" fill="white" stroke="${s.color}" stroke-width="2"/>`).join('')}<title>${escapeHtml(label)}</title></g>`;});
 svg+='</svg>';$('line-chart').innerHTML=svg;
 if(bins.some(bin=>bin.end!==bin.date))$('chart-readout').textContent='Periode panjang dikelompokkan ke dalam interval agar grafik tetap mudah dibaca.';
}
function drawBarChart(visible){
 const {source,bins,preference}=currentChart;const stacked=preference.type==='stacked';
 const width=940,height=300,pad={left:58,right:24,top:30,bottom:46},plotW=width-pad.left-pad.right,plotH=height-pad.top-pad.bottom;
 const peak=Math.max(0,...bins.map(bin=>stacked?visible.reduce((sum,series)=>sum+(bin.values[series.index]||0),0):Math.max(0,...visible.map(series=>bin.values[series.index]||0))));
 const maximum=source.max||Math.max(5,Math.ceil(peak/5)*5),y=value=>pad.top+plotH-value/maximum*plotH;
 const cell=plotW/bins.length,groupWidth=Math.min(cell*.76,70),barWidth=stacked?groupWidth:Math.max(1,groupWidth/visible.length-1.5);
 let svg=`<svg class="line-svg" viewBox="0 0 ${width} ${height}" role="group" aria-label="${escapeHtml(source.title)}"><title>${escapeHtml(source.title)} · ${stacked?'Batang Bertumpuk':'Batang Berkelompok'}</title><desc>${escapeHtml(source.description)} Nilai tersedia pada tabel di bawah grafik.</desc><defs>${visible.map(series=>`<linearGradient id="bar-fill-${series.key}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="${series.color}"/><stop offset="100%" stop-color="${series.color}" stop-opacity=".65"/></linearGradient>`).join('')}</defs><text x="${pad.left}" y="15" fill="#64748b" font-size="12">${escapeHtml(source.unit)}</text>`;
 for(let i=0;i<=4;i++){const value=maximum*i/4;svg+=`<line x1="${pad.left}" y1="${y(value)}" x2="${width-pad.right}" y2="${y(value)}" stroke="#e2e8f0" stroke-dasharray="4 5"/><text x="${pad.left-10}" y="${y(value)+4}" text-anchor="end" font-size="12" fill="#64748b">${lineValue(value)}</text>`;}
 const tickStep=Math.max(1,Math.ceil(bins.length/7));
 bins.forEach((bin,i)=>{const center=pad.left+cell*(i+.5);let sum=0;const descriptions=visible.map(series=>series.label+': '+(bin.values[series.index]===null?'Tidak ada data':lineValue(bin.values[series.index])+' '+source.unit)).join(', ');
 svg+=`<g class="chart-point" role="button" tabindex="0" data-point="${i}" aria-label="${escapeHtml(dateLabel(bin.date)+': '+descriptions)}"><rect x="${center-cell/2}" y="${pad.top}" width="${cell}" height="${plotH}" fill="transparent"/>`;
 visible.forEach((series,j)=>{const value=bin.values[series.index];if(value===null)return;const x=stacked?center-groupWidth/2:center-groupWidth/2+j*groupWidth/visible.length;const base=stacked?sum:0;const top=y(base+value),bottom=y(base);svg+=`<rect class="bar-column" x="${x}" y="${top}" width="${barWidth}" height="${Math.max(0,bottom-top)}" rx="${stacked?0:Math.min(4,barWidth/3)}" fill="url(#bar-fill-${series.key})"><title>${escapeHtml(series.label+': '+lineValue(value)+' '+source.unit)}</title></rect>`;sum+=value;});svg+='</g>';
 if(i%tickStep===0||i===bins.length-1)svg+=`<text x="${center}" y="${height-13}" text-anchor="middle" fill="#64748b" font-size="12">${escapeHtml(dateLabel(bin.date,true))}</text>`;
 });svg+='</svg>';$('line-chart').innerHTML=svg;
}

function showChartPoint(index){if(!currentChart?.bins[index])return;const {source,bins,preference}=currentChart,bin=bins[index];$('chart-readout').innerHTML=`<strong>${escapeHtml(dateLabel(bin.date))}${bin.end!==bin.date?' – '+escapeHtml(dateLabel(bin.end)):''}</strong>`+source.series.map((series,i)=>preference.hidden.includes(series.key)?'':`<span><i class="legend-dot" style="background:${series.color}" aria-hidden="true"></i>${escapeHtml(series.label)} : <b>${bin.values[i]===null?'Tidak ada data':lineValue(bin.values[i])+' '+escapeHtml(source.unit)}</b></span>`).join('');}
$('trend-panel').addEventListener('change',event=>{const preference=chartPreference();if(event.target.id==='chart-type')preference.type=event.target.value;else if(event.target.id==='chart-month')preference.month=event.target.value;else if(event.target.id==='chart-mode')preference.mode=event.target.value;else return;renderTrendCharts();});
$('trend-panel').addEventListener('click',event=>{const button=event.target.closest('[data-series]');if(button){const preference=chartPreference(),key=button.dataset.series;preference.hidden=preference.hidden.includes(key)?preference.hidden.filter(s=>s!==key):[...preference.hidden,key];renderTrendCharts();return;}const point=event.target.closest('[data-point]');if(point)showChartPoint(Number(point.dataset.point));});
['pointerover','focusin'].forEach(type=>$('trend-panel').addEventListener(type,event=>{const point=event.target.closest('[data-point]');if(point)showChartPoint(Number(point.dataset.point));}));
$('trend-panel').addEventListener('keydown',event=>{const point=event.target.closest('[data-point]');if(point&&(event.key==='Enter'||event.key===' ')){event.preventDefault();showChartPoint(Number(point.dataset.point));}});
  renderTrendCharts();
})();
