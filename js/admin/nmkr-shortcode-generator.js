(function () {
  'use strict';

  function init() {
    var form = document.getElementById('nmkr-generator-form');
    if (!form) return;

    // Progressive enhancement: changes refresh read-only GET data; no AJAX/API,
    // no settings persistence and no WordPress operation is triggered.
    ['nmkr-gen-type', 'nmkr-gen-project'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', function () { form.requestSubmit(); });
    });

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
      status.textContent = worked
        ? 'Shortcode copied.'
        : 'The shortcode is selected. Copy it with your keyboard.';
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
