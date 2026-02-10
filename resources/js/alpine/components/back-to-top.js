/**
 * Alpine.data('backToTop') - Back to top button, appears on scroll
 * Works with both document scroll and overflow-y-auto containers (main, #app).
 */
import Alpine from '@alpinejs/csp';

Alpine.data('backToTop', (scrollContainerSelector = '[data-scroll-container]') => ({
    visible: false,
    scrollThreshold: 300,
    scrollContainer: null,

    init() {
        this.scrollContainer = document.querySelector(scrollContainerSelector) || window;
        const scrollTarget = this.scrollContainer === window ? window : this.scrollContainer;

        const handleScroll = () => {
            const scrollTop = this.scrollContainer === window
                ? window.scrollY || document.documentElement.scrollTop
                : this.scrollContainer.scrollTop;
            this.visible = scrollTop > this.scrollThreshold;
        };

        scrollTarget.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll(); // Initial check
    },

    scrollToTop() {
        if (this.scrollContainer === window) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            this.scrollContainer.scrollTo({ top: 0, behavior: 'smooth' });
        }
    },
}));
