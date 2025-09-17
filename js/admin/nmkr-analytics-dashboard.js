(function() {
  // PR-1: no network calls; ensure the localized config is present and basic layout hooks exist.
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.nmkrAnalyticsDashboard === 'undefined') return;

    const cfg = window.nmkrAnalyticsDashboard || {};
    const $ = window.jQuery || null;

    // Basic sanity checks (can be removed later)
    if (console && console.debug) {
      console.debug('[NMKR Analytics] init', {
        ajax_url: cfg.ajax_url,
        hasNonce: !!cfg.nonce,
        defaults: cfg.defaults
      });
    }

    // Placeholders: ensure containers exist (they are output by PHP template)
    var elFilters = document.getElementById('nmkr-analytics-filters');
    var elKpis    = document.getElementById('nmkr-analytics-kpis');
    var elCharts  = document.getElementById('nmkr-analytics-charts');
    var elTables  = document.getElementById('nmkr-analytics-tables');
    var elExports = document.getElementById('nmkr-analytics-exports');

    [elFilters, elKpis, elCharts, elTables, elExports].forEach(function(el){
      if (!el) return;
      el.setAttribute('data-ready', '1');
    });

    // Future PRs (for reference):
    // - PR-2: fetch KPIs & timeseries
    // - PR-3: fetch top projects/tokens & breakdown
    // - PR-4/5: render filters/KPIs/charts/tables
    // - PR-6: exports + polish
  });
})();


