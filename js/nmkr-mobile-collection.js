(function () {
  'use strict';

  function init() {
    if (!window.matchMedia) return;
    var mq = window.matchMedia('(max-width: 640px)');
    var panels = document.querySelectorAll(
      '.nmkr-shortcode-grid [data-nmkr-collapse-mobile], .nmkr-shortcode-carousel [data-nmkr-collapse-mobile]'
    );
    if (!panels.length) return;

    function sync() {
      panels.forEach(function (panel) {
        // An active filter must stay visible so visitors can understand and reset it.
        panel.open = !mq.matches || panel.hasAttribute('data-nmkr-keep-open');
      });
    }

    sync();
    if (mq.addEventListener) mq.addEventListener('change', sync);
    else if (mq.addListener) mq.addListener(sync);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
}());
