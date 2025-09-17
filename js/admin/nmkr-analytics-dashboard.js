(function() {
  'use strict';

  function $(sel, root) { return (root||document).querySelector(sel); }
  function el(tag, attrs) {
    const n = document.createElement(tag);
    if (attrs) for (const [k,v] of Object.entries(attrs)) {
      if (k === 'text') n.textContent = v;
      else if (k === 'html') n.innerHTML = v;
      else n.setAttribute(k, v);
    }
    return n;
  }

  function fmtInt(n){ return (n||0).toLocaleString(); }
  function fmtPct(n){ return (typeof n === 'number' ? n : 0).toFixed(2) + '%'; }

  function clampCustomRangeDays(fromDate, toDate, maxDays) {
    const ms = toDate - fromDate;
    const days = ms / (1000*60*60*24);
    return days <= maxDays;
  }

  function spanHours(fromDate, toDate) {
    return Math.max(0, (toDate - fromDate) / (1000*60*60));
  }

  function toDateValue(d){ // YYYY-MM-DD
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`;
  }

  function dateAtStart(d){ return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0,0,0,0); }
  function dateAtEnd(d){ return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23,59,59,999); }

  function deriveBucket(fromDate, toDate) {
    return spanHours(fromDate, toDate) <= 72 ? 'hour' : 'day';
  }

  function debounce(fn, ms) {
    let t; return function(...args){ clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), ms); };
  }

  // Minimal line chart (canvas 2D), no external libs
  function drawLineChart(canvas, series, label) {
    if (!canvas) return;
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const width = Math.max(320, rect.width|0);
    const height = Math.max(180, rect.height|0);
    canvas.width = width * dpr; canvas.height = height * dpr;
    canvas.style.width = width + 'px'; canvas.style.height = height + 'px';
    const ctx = canvas.getContext('2d'); ctx.setTransform(dpr,0,0,dpr,0,0);
    ctx.clearRect(0,0,width,height);

    // Axes & padding
    const pad = { l: 40, r: 10, t: 10, b: 24 };
    const plotW = width - pad.l - pad.r;
    const plotH = height - pad.t - pad.b;

    // Guard
    if (!series || !series.length) {
      ctx.fillStyle = '#666';
      const msg = (window.nmkrAnalyticsDashboard
               && window.nmkrAnalyticsDashboard.i18n
               && window.nmkrAnalyticsDashboard.i18n.noData)
               ? window.nmkrAnalyticsDashboard.i18n.noData
               : 'No data';
      ctx.fillText(msg, pad.l, pad.t + 14);
      return;
    }

    // X: use indexes; labels from buckets
    const xs = series.map((_,i)=>i);
    const ys = series.map(p => p.y || 0);
    const maxY = Math.max(1, Math.max.apply(null, ys));
    const minY = 0;

    function xPix(i){ return pad.l + (plotW * (i / Math.max(1,(xs.length-1)))); }
    function yPix(v){ const t = (v - minY) / (maxY - minY); return pad.t + (plotH * (1 - t)); }

    // Grid
    ctx.strokeStyle = '#eee'; ctx.lineWidth = 1;
    ctx.beginPath();
    for (let g=0; g<=4; g++){
      const gy = pad.t + (plotH * g/4);
      ctx.moveTo(pad.l, gy); ctx.lineTo(pad.l + plotW, gy);
    }
    ctx.stroke();

    // Axis labels (min/mid/max)
    ctx.fillStyle = '#444'; ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
    const first = series[0]?.x || '';
    const mid   = series[Math.floor(series.length/2)]?.x || '';
    const last  = series[series.length-1]?.x || '';
    ctx.fillText(first, pad.l, height-6);
    ctx.fillText(last,  pad.l+plotW-ctx.measureText(last).width, height-6);
    if (mid && mid !== first && mid !== last) {
      const mw = ctx.measureText(mid).width;
      ctx.fillText(mid, pad.l + (plotW/2 - mw/2), height-6);
    }
    // Y labels
    const yTicks = [0, Math.round(maxY/2), maxY];
    yTicks.forEach(v => {
      const s = (''+v); const y = yPix(v)+4;
      ctx.fillText(s, 6 + pad.l - ctx.measureText(s).width - 6, y);
    });

    // Line
    ctx.strokeStyle = '#2d6cdf'; ctx.lineWidth = 2;
    ctx.beginPath();
    series.forEach((p,i)=>{
      const xp = xPix(i), yp = yPix(p.y||0);
      if (i===0) ctx.moveTo(xp, yp); else ctx.lineTo(xp, yp);
    });
    ctx.stroke();

    // Dots
    ctx.fillStyle = '#2d6cdf';
    series.forEach((p,i)=>{
      const xp = xPix(i), yp = yPix(p.y||0);
      ctx.beginPath(); ctx.arc(xp, yp, 2.5, 0, Math.PI*2); ctx.fill();
    });
  }

  // POST to admin-ajax
  async function postAjax(action, body) {
    const cfg = window.nmkrAnalyticsDashboard;
    const data = new URLSearchParams();
    data.set('action', action);
    data.set('nonce', cfg.nonce);
    for (const [k,v] of Object.entries(body||{})) {
      if (v === undefined || v === null) continue;
      data.set(k, String(v));
    }
    data.set('_', String(Date.now())); // cache buster
    const res = await fetch(cfg.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: data.toString(),
      credentials: 'same-origin',
    });
    const json = await res.json();
    if (!json || json.success !== true) {
      const msg = json && json.data && json.data.message ? json.data.message : 'Request failed';
      throw new Error(msg);
    }
    return json.data;
  }

  function init() {
    if (typeof window.nmkrAnalyticsDashboard === 'undefined') return;

    const cfg     = window.nmkrAnalyticsDashboard || {};
    const i18n    = Object.assign({
      title:'Analytics', loading:'Loading…', noData:'No data yet for the selected range.',
      invalidRange:'Custom range must be ≤ 365 days.', apply:'Apply', from:'From', to:'To',
      range:'Range', shortcodeType:'Shortcode type', views:'Views', clicks:'Clicks', ctr:'CTR'
    }, cfg.i18n || {});
    const ranges  = cfg.ranges || [
      {value:'24h',label:'Last 24 hours'},
      {value:'7d', label:'Last 7 days'},
      {value:'30d',label:'Last 30 days'},
      {value:'custom',label:'Custom range'}
    ];
    const types   = cfg.shortcodeTypes || [{value:'',label:'All shortcodes'}];

    // Containers
    const wrap    = $('.nmkr-analytics-wrap'); if (!wrap) return;
    const filters = $('#nmkr-analytics-filters');
    const kpisEl  = $('#nmkr-analytics-kpis');
    const charts  = $('#nmkr-analytics-charts');

    // Build Filters UI
    filters.innerHTML = '';
    filters.classList.add('nmkr-filters');

    const fRange = el('div', {class:'field'});
    fRange.append(el('label', {for:'nmkr-f-range', text:i18n.range}));
    const selRange = el('select', {id:'nmkr-f-range'});
    ranges.forEach(r=> selRange.append(el('option', {value:r.value, text:r.label})));
    selRange.value = (cfg.defaults && cfg.defaults.range) || '7d';
    fRange.append(selRange);

    const fFrom = el('div', {class:'field'});
    fFrom.append(el('label', {for:'nmkr-f-from', text:i18n.from}));
    const inputFrom = el('input', {type:'date', id:'nmkr-f-from'});
    fFrom.append(inputFrom);

    const fTo = el('div', {class:'field'});
    fTo.append(el('label', {for:'nmkr-f-to', text:i18n.to}));
    const inputTo = el('input', {type:'date', id:'nmkr-f-to'});
    fTo.append(inputTo);

    const fType = el('div', {class:'field'});
    fType.append(el('label', {for:'nmkr-f-type', text:i18n.shortcodeType}));
    const selType = el('select', {id:'nmkr-f-type'});
    types.forEach(t=> selType.append(el('option', {value:t.value, text:t.label})));
    fType.append(selType);

    const warn = el('div', {class:'nmkr-warn', style:'display:none;', text:i18n.invalidRange});

    const applyBtn = el('button', {class:'nmkr-btn', id:'nmkr-f-apply', type:'button', text:i18n.apply});

    filters.append(fRange, fFrom, fTo, fType, applyBtn, warn);

    function setCustomVisibility() {
      const show = selRange.value === 'custom';
      fFrom.style.display = show ? '' : 'none';
      fTo.style.display   = show ? '' : 'none';
    }
    setCustomVisibility();
    selRange.addEventListener('change', setCustomVisibility);

    // Pre-fill dates when entering custom
    selRange.addEventListener('change', () => {
      if (selRange.value === 'custom') {
        const now = new Date();
        const weekAgo = new Date(now.getTime() - 7*24*3600*1000);
        inputFrom.value = toDateValue(weekAgo);
        inputTo.value   = toDateValue(now);
      }
    });

    // KPI Cards
    kpisEl.innerHTML = '';
    kpisEl.classList.add('nmkr-kpis');
    function kpiCard(title, id) {
      const card = el('div', {class:'nmkr-kpi'});
      card.append(el('h3', {text:title}));
      card.append(el('div', {class:'val', id}));
      return card;
    }
    const kpiViews  = kpiCard(i18n.views,  'nmkr-kpi-views');
    const kpiClicks = kpiCard(i18n.clicks, 'nmkr-kpi-clicks');
    const kpiCtr    = kpiCard(i18n.ctr,    'nmkr-kpi-ctr');
    kpisEl.append(kpiViews, kpiClicks, kpiCtr);

    // Charts (2)
    charts.innerHTML = '';
    charts.classList.add('nmkr-charts');

    function chartCard(title, canvasId) {
      const card = el('div', {class:'nmkr-chart-card'});
      card.append(el('div', {class:'nmkr-chart-title', text:title}));
      const c = el('canvas', {class:'nmkr-chart-canvas', id:canvasId, role:'img', 'aria-label': title});
      card.append(c);
      return {card, canvas: c};
    }
    const chartA = chartCard(i18n.views,  'nmkr-chart-views-cv');
    const chartB = chartCard(i18n.clicks, 'nmkr-chart-clicks-cv');
    charts.append(chartA.card, chartB.card);

    // State & fetch
    function getFilters() {
      const range = selRange.value;
      let from = '', to = '';
      if (range === 'custom') {
        if (inputFrom.value) from = inputFrom.value + ' 00:00:00';
        if (inputTo.value)   to   = inputTo.value   + ' 23:59:59';
      }
      // Derive bucket on client for better UX (server will also pick sensibly)
      let bucket = 'day';
      if (range === '24h') bucket = 'hour';
      if (range === 'custom' && inputFrom.value && inputTo.value) {
        const fd = new Date(inputFrom.value+'T00:00:00');
        const td = new Date(inputTo.value+'T23:59:59');
        bucket = deriveBucket(fd, td);
      } else if (range === '7d') {
        bucket = 'day';
      } else if (range === '30d') {
        bucket = 'day';
      }
      const shortcode_type = selType.value || '';
      return { range, from, to, bucket, shortcode_type };
    }

    function validateRangeOrWarn(filters) {
      warn.style.display = 'none';
      if (filters.range !== 'custom') return true;
      if (!inputFrom.value || !inputTo.value) return false;
      const fd = new Date(inputFrom.value+'T00:00:00');
      const td = new Date(inputTo.value+'T23:59:59');
      if (!clampCustomRangeDays(fd, td, 365)) {
        warn.style.display = '';
        return false;
      }
      return true;
    }

    function setKPIsLoading() {
      $('#nmkr-kpi-views').textContent  = i18n.loading;
      $('#nmkr-kpi-clicks').textContent = i18n.loading;
      $('#nmkr-kpi-ctr').textContent    = i18n.loading;
    }

    async function refreshData() {
      const f = getFilters();
      if (!validateRangeOrWarn(f)) return;

      try {
        setKPIsLoading();

        const [kpis, series] = await Promise.all([
          postAjax('nmkr_analytics_kpis', f),
          postAjax('nmkr_analytics_timeseries', f)
        ]);

        // KPIs
        $('#nmkr-kpi-views').textContent  = fmtInt(kpis.views||0);
        $('#nmkr-kpi-clicks').textContent = fmtInt(kpis.clicks||0);
        $('#nmkr-kpi-ctr').textContent    = fmtPct(kpis.ctr||0);

        // Series to canvas format
        const data = (series.series || []).map(p => ({
          x: p.bucket,
          v: { views: p.views||0, clicks: p.clicks||0 }
        }));

        // Build sequences (label slimming: show YYYY-MM-DD or HH:00)
        const seqViews  = data.map(p => ({ x: p.x, y: p.v.views }));
        const seqClicks = data.map(p => ({ x: p.x, y: p.v.clicks }));

        if (!seqViews.length) {
          const ctx = chartA.canvas.getContext('2d');
          ctx.clearRect(0,0,chartA.canvas.width, chartA.canvas.height);
          drawLineChart(chartA.canvas, [], i18n.views);
          drawLineChart(chartB.canvas, [], i18n.clicks);
        } else {
          // Normalize labels a bit
          const slim = (label) => {
            // Expect "YYYY-MM-DD HH:00:00" or "YYYY-MM-DD"
            return label.length > 10 ? label.slice(5,16) : label;
          };
          const seqV2 = seqViews.map(p => ({ x: slim(p.x), y: p.y }));
          const seqC2 = seqClicks.map(p => ({ x: slim(p.x), y: p.y }));
          drawLineChart(chartA.canvas, seqV2, i18n.views);
          drawLineChart(chartB.canvas, seqC2, i18n.clicks);
        }
      } catch (err) {
        // Basic error surfacing
        $('#nmkr-kpi-views').textContent  = '–';
        $('#nmkr-kpi-clicks').textContent = '–';
        $('#nmkr-kpi-ctr').textContent    = '–';
        const msg = (err && err.message) ? err.message : 'Error';
        const help = $('.nmkr-help') || el('div', {class:'nmkr-help'});
        help.textContent = msg;
        charts.append(help);
        // Don’t throw further; keep UI responsive
      }
    }

    const debouncedRefresh = debounce(refreshData, 300);

    // Wire events
    selRange.addEventListener('change', debouncedRefresh);
    selType.addEventListener('change', debouncedRefresh);
    inputFrom.addEventListener('change', debouncedRefresh);
    inputTo.addEventListener('change', debouncedRefresh);
    $('#nmkr-f-apply').addEventListener('click', refreshData);

    // Initial load
    refreshData();
  }

  document.addEventListener('DOMContentLoaded', init);
})();