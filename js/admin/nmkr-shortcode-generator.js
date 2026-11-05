(function () {
  'use strict';

  function init() {
    var form = document.getElementById('nmkr-generator-form');
    if (!form) return;

    // Progressive enhancement: changes refresh read-only GET data; no AJAX/API,
    // no settings persistence and no WordPress operation is triggered.
    var tokenSelect = document.getElementById('nmkr-gen-token');
    var manualToken = document.getElementById('nmkr-gen-token-custom');
    ['nmkr-gen-type', 'nmkr-gen-project'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () {
        // A selected token belongs to its old project; clear both inputs before
        // submitting a new project so stale IDs cannot override fresh choices.
        if (id === 'nmkr-gen-project') {
          if (tokenSelect) tokenSelect.value = '';
          if (manualToken) manualToken.value = '';
        }
        form.requestSubmit();
      });
    });

    // Two alternative token inputs must not silently disagree: changing the
    // dropdown clears a previously pasted UID, and typing a UID clears the
    // dropdown. Server validation also refuses conflicting GET values without JS.
    if (tokenSelect && manualToken) {
      tokenSelect.addEventListener('change', function () {
        manualToken.value = '';
      });
      manualToken.addEventListener('input', function () {
        if (manualToken.value.trim()) tokenSelect.value = '';
      });
    }

    var copy = document.getElementById('nmkr-generator-copy');
    var code = document.getElementById('nmkr-generated-shortcode');
    var status = document.getElementById('nmkr-generator-copy-feedback');
    if (!copy || !code || !status) return;

    copy.hidden = false;
    copy.addEventListener('click', async function () {
      var value = code.value;
      var worked = false;
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(value);
          worked = true;
        }
      } catch (error) { /* Use the manual selection fallback. */ }
      if (!worked) {
        code.focus();
        code.select();
        try {
          worked = document.execCommand('copy');
        } catch (error) { /* The selected text remains ready for manual copying. */ }
      }
      var messages = window.nmkrQuickPublishMessages || {};
      status.textContent = worked
        ? (messages.copied || 'Shortcode copied.')
        : (messages.selected || 'The shortcode is selected. Copy it with your keyboard.');
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
