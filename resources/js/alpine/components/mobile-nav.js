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

/**
 * Document-level delegation for mobile menu elements without x-data.
 */
(function() {
    var toggle = document.getElementById('mobile-menu-toggle');
    var panel = document.getElementById('mobile-nav-panel');
    var iconOpen = document.getElementById('mobile-menu-icon-open');
    var iconClose = document.getElementById('mobile-menu-icon-close');
    var searchToggle = document.getElementById('mobile-search-toggle');
    var searchForm = document.getElementById('mobile-search-form');

    if (toggle && panel && !toggle.closest('[x-data]')) {
        toggle.addEventListener('click', function() {
            var isOpen = !panel.classList.contains('hidden');
            if (isOpen) { panel.classList.add('hidden'); toggle.setAttribute('aria-expanded', 'false'); }
            else { panel.classList.remove('hidden'); toggle.setAttribute('aria-expanded', 'true'); if (searchForm) searchForm.classList.add('hidden'); }
            if (iconOpen && iconClose) { iconOpen.classList.toggle('hidden'); iconClose.classList.toggle('hidden'); }
        });
    }

    document.querySelectorAll('.mobile-nav-toggle').forEach(function(t) {
        if (t.closest('[x-data]')) return;
        t.addEventListener('click', function() {
            var section = this.closest('.mobile-nav-section');
            if (!section) return;
            var submenu = section.querySelector('.mobile-nav-submenu');
            var chevron = this.querySelector('.mobile-nav-chevron');
            if (submenu) submenu.classList.toggle('hidden');
            if (chevron) chevron.classList.toggle('rotate-180');
        });
    });

    if (searchToggle && searchForm && !searchToggle.closest('[x-data]')) {
        searchToggle.addEventListener('click', function(ev) {
            ev.preventDefault();
            searchForm.classList.toggle('hidden');
            if (panel && !searchForm.classList.contains('hidden')) {
                panel.classList.add('hidden');
                if (iconOpen && iconClose) { iconOpen.classList.remove('hidden'); iconClose.classList.add('hidden'); }
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    var mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    if (mobileSidebarToggle && !mobileSidebarToggle.closest('[x-data]')) {
        mobileSidebarToggle.addEventListener('click', function() {
            var sidebar = document.getElementById('sidebar');
            if (sidebar) { sidebar.classList.toggle('hidden'); sidebar.classList.toggle('flex'); }
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            if (panel) panel.classList.add('hidden');
            if (searchForm) searchForm.classList.add('hidden');
            if (iconOpen && iconClose) { iconOpen.classList.remove('hidden'); iconClose.classList.add('hidden'); }
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }
    });
})();
