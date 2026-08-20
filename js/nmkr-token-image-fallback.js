(function () {
    'use strict';

    function transitionFailedImage(image) {
        if (!image || !image.matches || !image.matches('img[data-nmkr-token-image]')) {
            return;
        }

        var state = image.getAttribute('data-nmkr-fallback-state') || 'primary';
        var failed = image.currentSrc || image.src;

        if (state === 'primary') {
            var fallback = image.getAttribute('data-nmkr-fallback-src') || '';
            image.setAttribute('data-nmkr-fallback-state', 'fallback');
            image.removeAttribute('data-nmkr-fallback-src');
            if (fallback && fallback !== failed) {
                image.src = fallback;
                return;
            }
        }

        if (state !== 'terminal') {
            var placeholder = image.getAttribute('data-nmkr-placeholder-src') || '';
            image.setAttribute('data-nmkr-fallback-state', 'terminal');
            image.removeAttribute('data-nmkr-fallback-src');
            image.removeAttribute('data-nmkr-placeholder-src');
            if (placeholder && placeholder !== failed) {
                image.src = placeholder;
            }
        }
    }

    document.addEventListener('error', function (event) {
        transitionFailedImage(event.target);
    }, true);

    Array.prototype.forEach.call(document.querySelectorAll('img[data-nmkr-token-image]'), function (image) {
        if (image.complete === true && image.naturalWidth === 0) {
            transitionFailedImage(image);
        }
    });
}());
