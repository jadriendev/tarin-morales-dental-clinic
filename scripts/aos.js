const isMobile = window.matchMedia("(max-width: 767px)").matches;

if (!isMobile) {
    AOS.init({
        duration: 900,
        easing: "ease-out-cubic",
        once: true,
        offset: 100
    });
}