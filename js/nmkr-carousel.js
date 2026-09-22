function scrollCarousel(direction) {
    const container = document.querySelector(".nmkr-carousel-container");
    const scrollAmount = container.offsetWidth / 2;
    if (direction === "left") {
        container.scrollBy({ left: -scrollAmount, behavior: "smooth" });
    } else {
        container.scrollBy({ left: scrollAmount, behavior: "smooth" });
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Auto-scroll logic
    let autoScroll = setInterval(function() {
        scrollCarousel("right");
    }, 5000);

    document.querySelector(".nmkr-carousel-container").addEventListener("mouseover", function() {
        clearInterval(autoScroll);
    });

    document.querySelector(".nmkr-carousel-container").addEventListener("mouseout", function() {
        autoScroll = setInterval(function() {
            scrollCarousel("right");
        }, 5000);
    });
});
