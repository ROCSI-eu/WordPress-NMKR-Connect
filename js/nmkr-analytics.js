(function () {
  var w = window;
  var cfg = w.NMKR_ANALYTICS || {};

  function clamp01(n) {
    var x = parseFloat(n);
    if (isNaN(x)) return 0;
    if (x < 0) return 0;
    if (x > 1) return 1;
    return x;
  }

  function uuidv4() {
    // RFC4122 version 4 compliant UUID
    var cryptoObj = (w.crypto || w.msCrypto);
    if (cryptoObj && cryptoObj.getRandomValues) {
      var buf = new Uint8Array(16);
      cryptoObj.getRandomValues(buf);
      buf[6] = (buf[6] & 0x0f) | 0x40; // version 4
      buf[8] = (buf[8] & 0x3f) | 0x80; // variant 1
      var bth = [];
      for (var i = 0; i < 256; i++) bth[i] = (i + 256).toString(16).substr(1);
      return (
        bth[buf[0]] + bth[buf[1]] + bth[buf[2]] + bth[buf[3]] + '-' +
        bth[buf[4]] + bth[buf[5]] + '-' +
        bth[buf[6]] + bth[buf[7]] + '-' +
        bth[buf[8]] + bth[buf[9]] + '-' +
        bth[buf[10]] + bth[buf[11]] + bth[buf[12]] + bth[buf[13]] + bth[buf[14]] + bth[buf[15]]
      );
    }
    // Fallback (not cryptographically strong)
    var s4 = function () {
      return Math.floor((1 + Math.random()) * 0x10000).toString(16).substring(1);
    };
    return (
      s4() + s4() + '-' + s4() + '-' + s4() + '-' + s4() + '-' + s4() + s4() + s4()
    );
  }

  try {
    var debug = !!cfg.debug;
    var log = function () { if (debug) { var a = Array.prototype.slice.call(arguments); a.unshift('[NMKR Analytics][dev]'); console.log.apply(console, a); } };
    var warn = function () { if (debug) { var a = Array.prototype.slice.call(arguments); a.unshift('[NMKR Analytics][dev]'); console.warn.apply(console, a); } };

    var sampleRate = clamp01(cfg.sampleRate == null ? 1 : cfg.sampleRate);
    if (Math.random() > sampleRate) return; // sampling gate (first)

    if (cfg.mode === 'off') return;

    var consentFlag = !!(cfg.hasConsent || w.nmkrAnalyticsConsent);
    if (cfg.requiresConsent && !consentFlag) return;

    var dnt = (navigator && (navigator.doNotTrack === '1'));
    if (dnt) return;

    var sidKey = 'nmkr_analytics_sid';
    var sid = null;
    try {
      sid = w.localStorage.getItem(sidKey);
      if (!sid) {
        sid = uuidv4();
        w.localStorage.setItem(sidKey, sid);
      }
    } catch (e) { /* ignore storage errors */ }

    function seenKey(id) { return 'nmkr_seen:' + id; }

    function buildPayload(eventType, el) {
      var ds = el && el.dataset ? el.dataset : {};
      var tz = '';
      try {
        tz = (Intl && Intl.DateTimeFormat && Intl.DateTimeFormat().resolvedOptions().timeZone) || '';
      } catch (e) { tz = ''; }
      return {
        event_type: eventType,
        shortcode: ds.nmkrShortcode || null,
        project_uid: ds.nmkrProjectUid || null,
        token_uid: ds.nmkrTokenUid || null,
        element_id: ds.nmkrId || (ds.nmkrShortcode ? (ds.nmkrShortcode + ':' + (ds.nmkrTokenUid || ds.nmkrProjectUid || 'unknown')) : null),
        session_id: sid || null,
        ts_client: Date.now(),
        meta: {
          vw: w.innerWidth || null,
          vh: w.innerHeight || null,
          lang: (navigator && navigator.language) ? String(navigator.language) : '',
          tz: tz,
          dnt: dnt,
          consent: consentFlag
        }
      };
    }

    function send(evt) {
      if (!cfg.transportEnabled) {
        log('Transport disabled; stub event', evt);
        return;
      }
      // TODO: Implement send via navigator.sendBeacon to cfg.endpoint_rest
      // TODO: Fallback to fetch() POST to cfg.endpoint_rest
      // TODO: Fallback to AJAX endpoint cfg.endpoint_ajax
      warn('Transport enabled, but not implemented in PR-1.', evt);
    }

    var io = null;
    if ('IntersectionObserver' in w) {
      io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          var el = entry.target;
          if (!el || el.getAttribute('data-nmkr-evt') !== 'view') return;
          var id = el.getAttribute('data-nmkr-id') || '';
          if (entry.intersectionRatio >= 0.5) {
            if (id) {
              try { if (w.sessionStorage.getItem(seenKey(id))) return; } catch (e) {}
            }
            var timer = setTimeout(function () {
              // If still visible after dwell
              var stillVisible = entry.isIntersecting && entry.intersectionRatio >= 0.5;
              if (stillVisible) {
                if (id) { try { w.sessionStorage.setItem(seenKey(id), '1'); } catch (e) {} }
                var payload = buildPayload('view', el);
                send(payload);
              }
            }, 1000);
            el.__nmkrViewTimer = timer;
          } else {
            if (el.__nmkrViewTimer) {
              clearTimeout(el.__nmkrViewTimer);
              el.__nmkrViewTimer = null;
            }
          }
        });
      }, { threshold: [0, 0.5, 1] });
    }

    function observeViews() {
      if (!io) return;
      var els = document.querySelectorAll('[data-nmkr-evt="view"]');
      for (var i = 0; i < els.length; i++) {
        io.observe(els[i]);
      }
    }

    function setupClicks() {
      document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t) return;
        var anchor = t.closest ? t.closest('[data-nmkr-cta="buy"]') : null;
        if (!anchor) return;
        var payload = buildPayload('click', anchor);
        send(payload);
      }, { passive: true });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { observeViews(); setupClicks(); });
    } else {
      observeViews();
      setupClicks();
    }

    log('Initialized', { mode: cfg.mode, sampleRate: sampleRate, requiresConsent: !!cfg.requiresConsent, hasConsent: !!cfg.hasConsent, dnt: dnt, transportEnabled: !!cfg.transportEnabled });
  } catch (err) {
    if (w && w.console && cfg && cfg.debug) {
      console.warn('[NMKR Analytics][dev] init error:', err);
    }
  }
})();


