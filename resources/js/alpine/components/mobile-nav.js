/**
 * Alpine.data('mobileNav') - Mobile navigation panel + search toggle
 */
import Alpine from '@alpinejs/csp';

Alpine.data('mobileNav', () => ({
    navOpen: false,
    searchOpen: false,

    toggleNav() {
        this.navOpen = !this.navOpen;
        if (this.navOpen) this.searchOpen = false;
    },

    toggleSearch() {
        this.searchOpen = !this.searchOpen;
        if (this.searchOpen) this.navOpen = false;
    },

    closeAll() {
        this.navOpen = false;
        this.searchOpen = false;
    },

    init() {
        // Close on resize past lg breakpoint
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) this.closeAll();
        });
    }
}));

Alpine.data('mobileNavSection', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    }
}));

Alpine.data('mobileSidebar', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    }
}));
