function openLightbox(src){var el=document.getElementById('nmkr-lightbox'); if(!el) return; el.querySelector('img').src=src; el.classList.add('is-open');}
function closeLightbox(){var el=document.getElementById('nmkr-lightbox'); if(!el) return; el.classList.remove('is-open'); el.querySelector('img').src='';}
