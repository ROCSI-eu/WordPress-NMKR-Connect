(function () {
    'use strict';

    let previousFocus = null;

    function getLightbox() {
        return document.getElementById('nmkr-lightbox');
    }

    function openLightbox(src, alt) {
        const lightbox = getLightbox();
        if (!lightbox || !src) return;

        const image = lightbox.querySelector('img');
        const close = lightbox.querySelector('.nmkr-close');
        previousFocus = document.activeElement;

        image.src = src;
        image.alt = alt || '';
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('nmkr-lightbox-open');

        if (close) close.focus();
    }

    function closeLightbox() {
        const lightbox = getLightbox();
        if (!lightbox) return;

        const image = lightbox.querySelector('img');
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('nmkr-lightbox-open');
        if (image) {
            image.src = '';
            image.alt = '';
        }

        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }
        previousFocus = null;
    }

    function trapLightboxFocus(event, lightbox) {
        const focusable = Array.from(lightbox.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (element) {
            return !element.hidden && element.getAttribute('aria-hidden') !== 'true';
        });

        if (!focusable.length) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && (active === first || !lightbox.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !lightbox.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-nmkr-lightbox-trigger="1"]');
        if (trigger) {
            openLightbox(trigger.currentSrc || trigger.src, trigger.alt || '');
            return;
        }

        const lightbox = getLightbox();
        if (!lightbox || !lightbox.classList.contains('is-open')) return;

        if (event.target === lightbox || event.target.closest('.nmkr-close')) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', function (event) {
        const trigger = event.target.closest
            ? event.target.closest('[data-nmkr-lightbox-trigger="1"]')
            : null;

        if (trigger && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openLightbox(trigger.currentSrc || trigger.src, trigger.alt || '');
            return;
        }

        const lightbox = getLightbox();
        const isOpen = lightbox && lightbox.classList.contains('is-open');

        if (isOpen && event.key === 'Tab') {
            trapLightboxFocus(event, lightbox);
            return;
        }

        if (isOpen && event.key === 'Escape') {
            event.preventDefault();
            closeLightbox();
        }
    });

    window.openLightbox = openLightbox;
    window.closeLightbox = closeLightbox;
})();
