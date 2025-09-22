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

  // Build a generic sortable/paginated table controller
  function createTopTable(opts) {
    // opts: { rootEl, title, entity: 'projects'|'tokens', i18n, fetcher, defaults }
    const state = {
      page: 1,
      perPage: (opts.defaults && opts.defaults.perPage) || 10,
      sort: 'views',
      order: 'desc',
      search: ''
    };

    const root = opts.rootEl;
    root.innerHTML = '';

    // Card + header
    const card = document.createElement('div'); card.className = 'nmkr-table-card';
    const head = document.createElement('div'); head.className = 'nmkr-table-head';
    const hTitle = document.createElement('div'); hTitle.className = 'nmkr-table-title'; hTitle.textContent = opts.title;
    const controls = document.createElement('div'); controls.className = 'nmkr-table-search';

    const searchInput = document.createElement('input');
    searchInput.type = 'text'; searchInput.placeholder = opts.i18n.searchUid || 'Search UID prefix';
    searchInput.setAttribute('aria-label', opts.i18n.searchUid || 'Search UID prefix');
    const searchBtn = document.createElement('button'); searchBtn.className = 'nmkr-btn'; searchBtn.type='button'; searchBtn.textContent = opts.i18n.search || 'Search';
    const resetBtn  = document.createElement('button'); resetBtn.className = 'nmkr-btn'; resetBtn.type='button'; resetBtn.textContent = opts.i18n.reset || 'Reset';

    controls.append(searchInput, searchBtn, resetBtn);
    head.append(hTitle, controls);
    card.append(head);

    // Table
    const table = document.createElement('table'); table.className = 'nmkr-table'; table.setAttribute('role','table');
    const thead = document.createElement('thead');
    const trh = document.createElement('tr');

    function th(label, key, sortable) {
      const th = document.createElement('th');
      th.textContent = label;
      th.scope = 'col';
      if (sortable) {
        th.className = 'sortable';
        th.dataset.key = key;
        const arrow = document.createElement('span'); arrow.className = 'arrow'; arrow.textContent = '↕';
        th.appendChild(arrow);
        th.title = (opts.i18n.sort || 'Sort');
        th.setAttribute('aria-sort', 'none');
      }
      return th;
    }

    const uidLabel = opts.i18n.uid || 'UID';
    trh.append(
      th(uidLabel, opts.entity === 'projects' ? 'project_uid' : 'token_uid', true),
      th(opts.i18n.views || 'Views',  'views',  true),
      th(opts.i18n.clicks || 'Clicks','clicks', true),
      th(opts.i18n.ctr || 'CTR',      'ctr',    true),
    );
    thead.append(trh);
    table.append(thead);
    const tbody = document.createElement('tbody');
    table.append(tbody);

    // Footer/pager
    const footer = document.createElement('div'); footer.className = 'nmkr-table-footer';
    const pager  = document.createElement('div'); pager.className = 'nmkr-pager';
    const prev = document.createElement('button'); prev.className = 'nmkr-btn'; prev.textContent = opts.i18n.prev || 'Prev'; prev.type='button'; prev.setAttribute('aria-label', opts.i18n.prev || 'Prev');
    const next = document.createElement('button'); next.className = 'nmkr-btn'; next.textContent = opts.i18n.next || 'Next'; next.type='button'; next.setAttribute('aria-label', opts.i18n.next || 'Next');
    const pageInfo = document.createElement('span'); pageInfo.className = 'nmkr-muted'; pageInfo.setAttribute('aria-live','polite');

    pager.append(prev, next);
    footer.append(pager, pageInfo);

    card.append(table, footer);
    root.append(card);

    let inflight = null;
    let lastFilters = null;

    function setLoading(on) {
      const tds = tbody.querySelectorAll('td');
      if (on) {
        tbody.innerHTML = `<tr><td class="nmkr-empty" colspan="4">${(opts.i18n.loading||'Loading…')}</td></tr>`;
      } else if (!tds.length) {
        // no-op
      }
      prev.disabled = on; next.disabled = on;
      Array.from(thead.querySelectorAll('th.sortable')).forEach(th => th.style.pointerEvents = on ? 'none' : '');
    }

    function applySortIndicators() {
      thead.querySelectorAll('th.sortable').forEach(th => {
        const key = th.dataset.key;
        const arrow = th.querySelector('.arrow');
        if (!arrow) return;
        if (state.sort === key) {
          arrow.textContent = state.order === 'asc' ? '↑' : '↓';
          arrow.style.color = '#111';
          th.setAttribute('aria-sort', state.order === 'asc' ? 'ascending' : 'descending');
        } else {
          arrow.textContent = '↕';
          arrow.style.color = '#888';
          th.setAttribute('aria-sort', 'none');
        }
      });
    }

    async function refresh(filters) {
      lastFilters = filters;
      setLoading(true);
      inflight && inflight.abort && inflight.abort();
      const controller = new AbortController(); inflight = controller;

      try {
        const payload = Object.assign({}, filters, {
          page: state.page,
          per_page: state.perPage,
          sort: state.sort,
          order: state.order,
          search: state.search
        });
        const action = opts.entity === 'projects' ? 'nmkr_analytics_top_projects' : 'nmkr_analytics_top_tokens';
        const data = await opts.fetcher(action, payload, { signal: controller.signal });
        renderRows(data);
      } catch (e) {
        // Ignore aborted requests to avoid flashing errors during rapid interactions
        if (e && (e.name === 'AbortError' || e.message === 'AbortError')) return;
        tbody.innerHTML = `<tr><td class="nmkr-empty" colspan="4">${(opts.i18n.error||'Something went wrong.')}</td></tr>`;
      } finally {
        setLoading(false);
        inflight = null;
      }
    }

    function renderRows(data) {
      const rows = Array.isArray(data.rows) ? data.rows : [];
      tbody.innerHTML = '';

      if (!rows.length) {
        tbody.innerHTML = `<tr><td class="nmkr-empty" colspan="4">${(opts.i18n.noResults||'No results found.')}</td></tr>`;
      } else {
        rows.forEach(r => {
          const tr = document.createElement('tr');
          const uid = opts.entity === 'projects' ? r.project_uid : r.token_uid;
          const tdUid = document.createElement('td'); tdUid.textContent = uid || '';
          tdUid.title = (uid || '');
          tdUid.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace';
          tdUid.style.fontSize = '12px';
          const tdV = document.createElement('td'); tdV.textContent = (r.views||0).toLocaleString();
          const tdC = document.createElement('td'); tdC.textContent = (r.clicks||0).toLocaleString();
          const tdR = document.createElement('td'); tdR.textContent = ((typeof r.ctr==='number'? r.ctr:0).toFixed(2)) + '%';
          tr.append(tdUid, tdV, tdC, tdR);
          tbody.append(tr);
        });
      }

      // Pager info
      const total = typeof data.total === 'number' ? data.total : 0;
      const pages = Math.max(1, Math.ceil(total / state.perPage));
      pageInfo.textContent = `${opts.i18n.page||'Page'} ${state.page} ${(opts.i18n.of||'of')} ${pages}`;
      prev.disabled = state.page <= 1;
      next.disabled = state.page >= pages;

      applySortIndicators();
    }

    // Events
    thead.addEventListener('click', (e) => {
      const th = e.target.closest('th.sortable');
      if (!th) return;
      const key = th.dataset.key;
      if (state.sort === key) {
        state.order = (state.order === 'asc') ? 'desc' : 'asc';
      } else {
        state.sort = key;
        state.order = (key === 'uid' || key === 'project_uid' || key === 'token_uid') ? 'asc' : 'desc';
      }
      state.page = 1;
      lastFilters && refresh(lastFilters);
    });

    const debouncedSearch = (function(){
      let t; return function(){
        clearTimeout(t);
        t = setTimeout(() => {
          state.search = (searchInput.value || '').trim();
          state.page = 1;
          lastFilters && refresh(lastFilters);
        }, 300);
      };
    })();
    searchInput.addEventListener('input', debouncedSearch);
    searchBtn.addEventListener('click', () => {
      state.search = (searchInput.value || '').trim();
      state.page = 1;
      lastFilters && refresh(lastFilters);
    });
    resetBtn.addEventListener('click', () => {
      searchInput.value = '';
      state.search = '';
      state.page = 1;
      lastFilters && refresh(lastFilters);
    });

    prev.addEventListener('click', () => {
      if (state.page > 1) { state.page--; lastFilters && refresh(lastFilters); }
    });
    next.addEventListener('click', () => {
      state.page++; lastFilters && refresh(lastFilters);
    });

    return {
      refresh,
      resetPage: () => { state.page = 1; },
      state
    };
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
  async function postAjax(action, body, fetchOpts) {
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
      signal: fetchOpts && fetchOpts.signal ? fetchOpts.signal : undefined,
      credentials: 'same-origin',
    });
    const json = await res.json();
    if (!json || json.success !== true) {
      const msg = json && json.data && json.data.message ? json.data.message : 'Request failed';
      throw new Error(msg);
    }
    return json.data;
  }

  // Download export (CSV/JSON) via admin-ajax
  async function downloadExport(action, body, filenameHint, expectedType) {
    const cfg = window.nmkrAnalyticsDashboard;
    const data = new URLSearchParams();
    data.set('action', action);
    data.set('nonce', cfg.nonce);
    Object.entries(body||{}).forEach(([k,v]) => { if (v !== undefined && v !== null) data.set(k, String(v)); });
    data.set('_', String(Date.now()));

    const res = await fetch(cfg.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: data.toString(),
      credentials: 'same-origin',
    });

    if (!res.ok) throw new Error('Export failed');

    const blob = await res.blob();
    const ext = (expectedType === 'json') ? '.json' : '.csv';
    const fname = (filenameHint || 'nmkr-analytics-export') + ext;

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fname;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(()=> URL.revokeObjectURL(url), 1000);
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
    const tablesWrap = $('#nmkr-analytics-tables');

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

    // === Tables section ===
    tablesWrap.classList.add('nmkr-tables');
    const projRoot  = document.createElement('div'); projRoot.id = 'nmkr-top-projects-root';
    const tokenRoot = document.createElement('div'); tokenRoot.id = 'nmkr-top-tokens-root';
    tablesWrap.innerHTML = '';
    tablesWrap.append(projRoot, tokenRoot);

    const topProjects = createTopTable({
      rootEl: projRoot,
      title: (i18n.topProjects || 'Top Projects'),
      entity: 'projects',
      i18n, defaults: (cfg.defaults || {}),
      fetcher: postAjax
    });
    const topTokens = createTopTable({
      rootEl: tokenRoot,
      title: (i18n.topTokens || 'Top Tokens'),
      entity: 'tokens',
      i18n, defaults: (cfg.defaults || {}),
      fetcher: postAjax
    });

    // === Export Bar ===
    const expWrap = $('#nmkr-analytics-exports');
    if (expWrap) {
      expWrap.innerHTML = '';
      expWrap.classList.add('nmkr-analytics-section');

      const title = document.createElement('div');
      title.style.marginBottom = '6px';
      title.style.fontWeight = '600';
      title.textContent = i18n.exports || 'Exports';
      expWrap.append(title);

      const bar = document.createElement('div');
      bar.style.display = 'flex';
      bar.style.flexWrap = 'wrap';
      bar.style.gap = '8px';
      bar.style.alignItems = 'end';

      // What to export (entity)
      const fldWhat = document.createElement('div');
      fldWhat.className = 'field';
      const lblWhat = document.createElement('label'); lblWhat.textContent = i18n.exportWhat || 'Data';
      const selWhat = document.createElement('select');
      const selWhatId = 'nmkr-exp-what';
      selWhat.id = selWhatId;
      lblWhat.htmlFor = selWhatId;
      [
        {v:'timeseries',    l:(i18n.timeseries   || 'Timeseries')},
        {v:'top_projects',  l:(i18n.topProjects || 'Top Projects')},
        {v:'top_tokens',    l:(i18n.topTokens   || 'Top Tokens')},
        {v:'breakdown',     l:(i18n.breakdown   || 'Shortcode Breakdown')},
      ].forEach(o => { const opt = document.createElement('option'); opt.value = o.v; opt.textContent = o.l; selWhat.append(opt); });
      fldWhat.append(lblWhat, selWhat);

      // Format
      const fldFmt = document.createElement('div');
      fldFmt.className = 'field';
      const lblFmt = document.createElement('label'); lblFmt.textContent = i18n.exportFormat || 'Format';
      const selFmt = document.createElement('select');
      const selFmtId = 'nmkr-exp-fmt';
      selFmt.id = selFmtId;
      lblFmt.htmlFor = selFmtId;
      [
        {v:'csv',  l:(i18n.csv  || 'CSV')},
        {v:'json', l:(i18n.json || 'JSON')},
      ].forEach(o => { const opt = document.createElement('option'); opt.value = o.v; opt.textContent = o.l; selFmt.append(opt); });
      fldFmt.append(lblFmt, selFmt);

      // Note
      const note = document.createElement('div');
      note.className = 'nmkr-muted';
      note.textContent = i18n.noteExport || 'Exports reflect current filters; top lists export the current page.';

      // Download button
      const btn = document.createElement('button');
      btn.className = 'nmkr-btn';
      btn.type = 'button';
      btn.textContent = i18n.download || 'Download';
      btn.setAttribute('aria-label', (i18n.download || 'Download') + ' ' + (i18n.exports || 'Exports'));

      // aria-live status region
      const status = document.createElement('div');
      status.id = 'nmkr-exp-status';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.className = 'nmkr-visually-hidden';

      bar.append(fldWhat, fldFmt, btn);
      expWrap.append(bar, note, status);

      btn.addEventListener('click', async () => {
        const f = getFilters();
        const entity = selWhat.value;
        const format = selFmt.value;

        let extras = {};
        if (entity === 'top_projects') {
          extras = {
            page: topProjects.state.page,
            per_page: topProjects.state.perPage,
            sort: topProjects.state.sort,
            order: topProjects.state.order,
            search: topProjects.state.search
          };
        } else if (entity === 'top_tokens') {
          extras = {
            page: topTokens.state.page,
            per_page: topTokens.state.perPage,
            sort: topTokens.state.sort,
            order: topTokens.state.order,
            search: topTokens.state.search
          };
        }

        try {
          btn.disabled = true;
          await downloadExport('nmkr_analytics_export', Object.assign({}, f, extras, { entity, format }), `nmkr-${entity}-${Date.now()}`, (format === 'json' ? 'json' : 'csv'));
          status.textContent = (i18n.downloadStarted || 'Download started');
        } catch (e) {
          const err = document.createElement('div');
          err.className = 'nmkr-warn';
          err.textContent = (i18n.error || 'Something went wrong.');
          expWrap.append(err);
          setTimeout(()=> err.remove(), 3000);
          status.textContent = (i18n.error || 'Something went wrong.');
        } finally {
          btn.disabled = false;
          btn.focus();
        }
      });
    }

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

      // After KPIs & charts, refresh tables using the same filters
      topProjects.refresh(f);
      topTokens.refresh(f);
    }

    const debouncedRefresh = debounce(refreshData, 300);

    // Wire events
    function resetTablesToFirstPage() { topProjects.resetPage(); topTokens.resetPage(); }
    selRange.addEventListener('change', () => { resetTablesToFirstPage(); debouncedRefresh(); });
    selType.addEventListener('change', () => { resetTablesToFirstPage(); debouncedRefresh(); });
    inputFrom.addEventListener('change', () => { resetTablesToFirstPage(); debouncedRefresh(); });
    inputTo.addEventListener('change', () => { resetTablesToFirstPage(); debouncedRefresh(); });
    $('#nmkr-f-apply').addEventListener('click', refreshData);

    // Initial load
    refreshData();
  }

  document.addEventListener('DOMContentLoaded', init);
})();