(function () {
    'use strict';

    function scrollCarousel(wrapper, direction) {
        const container = wrapper.querySelector('.nmkr-carousel-container');
        if (!container) return;

        const amount = Math.max(container.clientWidth * 0.8, 220);
        const reducedMotion = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        container.scrollBy({
            left: direction === 'prev' ? -amount : amount,
            behavior: reducedMotion ? 'auto' : 'smooth'
        });
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.nmkr-carousel-control');
        if (!button) return;

        const wrapper = button.closest('.nmkr-carousel-wrapper');
        if (!wrapper) return;

        scrollCarousel(wrapper, button.getAttribute('data-nmkr-carousel-direction'));
    });
})();
