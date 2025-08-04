document.addEventListener("DOMContentLoaded", function() {
    // Collect all images with the class 'lazy'
    let lazyImages = [].slice.call(document.querySelectorAll("img.lazy"));

    // Check if IntersectionObserver is supported in the browser
    if ("IntersectionObserver" in window) {
        // Create a new IntersectionObserver
        let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                // Check if the image is in the viewport (isIntersecting)
                if (entry.isIntersecting) {
                    let lazyImage = entry.target; // Get the image being observed
                    // Swap 'data-src' with 'src' to load the real image
                    lazyImage.src = lazyImage.dataset.src;
                    lazyImage.classList.remove("lazy"); // Remove the lazy class
                    // Stop observing the current image
                    lazyImageObserver.unobserve(lazyImage);
                }
            });
        });

        // Observe each lazy image
        lazyImages.forEach(function(lazyImage) {
            lazyImageObserver.observe(lazyImage);
        });
    } else {
        // Fallback for browsers without IntersectionObserver support
        lazyImages.forEach(function(lazyImage) {
            lazyImage.src = lazyImage.dataset.src;
            lazyImage.classList.remove("lazy");
        });
    }
});