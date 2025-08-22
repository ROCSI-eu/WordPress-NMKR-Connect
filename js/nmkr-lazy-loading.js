document.addEventListener('DOMContentLoaded', function () {
  var lazyImages = Array.prototype.slice.call(document.querySelectorAll('img.lazy[data-src]'));
  if (!lazyImages.length) return;

  function load(el) {
    var src = el.getAttribute('data-src');
    if (src) {
      el.src = src;
      el.classList.remove('lazy');
    }
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          load(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '200px 0px' });

    lazyImages.forEach(function (img) { io.observe(img); });
  } else {
    // Fallback: load all immediately
    lazyImages.forEach(load);
  }
});