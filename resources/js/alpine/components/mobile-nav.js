/**
 * Alpine.data('mobileNav') - Mobile navigation panel + search toggle
 */
import Alpine from '@alpinejs/csp';

/**
 * Document-level delegation for mobile menu elements without x-data.
 */
(function() {
    document.querySelectorAll('#sidebar a[href]').forEach(function(link) {
        try {
            var linkUrl = new URL(link.href, window.location.origin);
            if (linkUrl.origin === window.location.origin && linkUrl.pathname === window.location.pathname) {
                link.setAttribute('aria-current', 'page');
            }
        } catch (_error) {
            // Ignore malformed or non-navigational URLs.
        }
    });

    var toggle = document.getElementById('mobile-menu-toggle');
    var panel = document.getElementById('mobile-nav-panel');
    var iconOpen = document.getElementById('mobile-menu-icon-open');
    var iconClose = document.getElementById('mobile-menu-icon-close');
    var searchToggle = document.getElementById('mobile-search-toggle');
    var searchForm = document.getElementById('mobile-search-form');

    function setCategoryPanel(open) {
        panel?.classList.toggle('hidden', !open);
        toggle?.setAttribute('aria-expanded', String(open));
        iconOpen?.classList.toggle('hidden', open);
        iconClose?.classList.toggle('hidden', !open);
    }

    function setSearchPanel(open) {
        searchForm?.classList.toggle('hidden', !open);
        searchToggle?.setAttribute('aria-expanded', String(open));
    }

    if (toggle && panel && !toggle.closest('[x-data]')) {
        toggle.addEventListener('click', function() {
            var open = panel.classList.contains('hidden');
            setCategoryPanel(open);
            if (open) setSearchPanel(false);
        });
    }

    document.querySelectorAll('.mobile-nav-toggle').forEach(function(t, index) {
        if (t.closest('[x-data]')) return;
        var submenu = t.closest('.mobile-nav-section')?.querySelector('.mobile-nav-submenu');
        if (!submenu) return;
        submenu.id ||= 'mobile-category-' + index;
        t.setAttribute('aria-controls', submenu.id);
        t.setAttribute('aria-expanded', String(!submenu.classList.contains('hidden')));
        t.addEventListener('click', function() {
            var section = this.closest('.mobile-nav-section');
            if (!section) return;
            var submenu = section.querySelector('.mobile-nav-submenu');
            var chevron = this.querySelector('.mobile-nav-chevron');
            if (submenu) {
                submenu.classList.toggle('hidden');
                this.setAttribute('aria-expanded', String(!submenu.classList.contains('hidden')));
            }
            if (chevron) chevron.classList.toggle('rotate-180');
        });
    });

    if (searchToggle && searchForm && !searchToggle.closest('[x-data]')) {
        searchToggle.addEventListener('click', function(ev) {
            ev.preventDefault();
            var open = searchForm.classList.contains('hidden');
            setSearchPanel(open);
            if (open) {
                setCategoryPanel(false);
                searchForm.querySelector('input[type="search"], input[type="text"]')?.focus();
            }
        });
    }

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') return;
        if (toggle?.getAttribute('aria-expanded') === 'true') {
            setCategoryPanel(false);
            toggle.focus();
        } else if (searchToggle?.getAttribute('aria-expanded') === 'true') {
            setSearchPanel(false);
            searchToggle.focus();
        }
    });

    var mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    var mobileSidebarClose = document.getElementById('mobile-sidebar-close');
    var mobileSidebarBackdrop = document.getElementById('mobile-sidebar-backdrop');
    var inertSidebarBackground = [];

    function setMobileSidebar(open) {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar || !mobileSidebarToggle) return;
        open = open && window.innerWidth < 768;

        sidebar.classList.toggle('hidden', !open);
        sidebar.classList.toggle('flex', open);
        mobileSidebarBackdrop?.classList.toggle('hidden', !open);
        mobileSidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        mobileSidebarToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');

        if (open) {
            setCategoryPanel(false);
            setSearchPanel(false);
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            mobileSidebarClose?.focus();

            for (var ancestor = sidebar; ancestor.parentElement; ancestor = ancestor.parentElement) {
                Array.from(ancestor.parentElement.children).forEach(function(sibling) {
                    if (sibling !== ancestor && sibling !== mobileSidebarBackdrop && !sibling.inert) {
                        sibling.inert = true;
                        inertSidebarBackground.push(sibling);
                    }
                });
                if (ancestor.parentElement === document.body) break;
            }
        } else {
            inertSidebarBackground.forEach(function(element) { element.inert = false; });
            inertSidebarBackground = [];
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }

        if (!open && window.innerWidth < 768) {
            mobileSidebarToggle.focus();
        } else if (!open && document.activeElement === mobileSidebarClose) {
            sidebar.querySelector('a[href]')?.focus();
        }
    }

    if (mobileSidebarToggle && !mobileSidebarToggle.closest('[x-data]')) {
        mobileSidebarToggle.addEventListener('click', function() {
            var sidebar = document.getElementById('sidebar');
            if (sidebar) setMobileSidebar(sidebar.classList.contains('hidden'));
        });

        mobileSidebarClose?.addEventListener('click', function() {
            setMobileSidebar(false);
        });

        mobileSidebarBackdrop?.addEventListener('click', function() {
            setMobileSidebar(false);
        });

        document.addEventListener('keydown', function(event) {
            if (mobileSidebarToggle.getAttribute('aria-expanded') !== 'true') return;

            if (event.key === 'Escape') {
                event.preventDefault();
                setMobileSidebar(false);
            } else if (event.key === 'Tab') {
                var sidebar = document.getElementById('sidebar');
                var focusable = Array.from(sidebar.querySelectorAll('a[href], button, input, select, textarea, [tabindex]'))
                    .filter(function(element) {
                        return element.tabIndex >= 0 && !element.matches(':disabled')
                            && !element.closest('[inert]') && element.getClientRects().length > 0
                            && window.getComputedStyle(element).visibility === 'visible';
                    });
                var first = focusable[0];
                var last = focusable[focusable.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last?.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first?.focus();
                }
            }
        });

        document.getElementById('sidebar')?.addEventListener('click', function(event) {
            if (event.target.closest('a[href]') && mobileSidebarToggle.getAttribute('aria-expanded') === 'true') {
                setMobileSidebar(false);
            }
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1536) {
            var focusInPanel = panel?.contains(document.activeElement);
            setCategoryPanel(false);
            if (focusInPanel) document.querySelector('#desktop-nav button, #desktop-nav a[href]')?.focus();
        }

        if (window.innerWidth >= 1536) {
            var focusInSearch = searchForm?.contains(document.activeElement);
            setSearchPanel(false);
            if (focusInSearch) document.querySelector('#header-search-form input')?.focus();
        }

        if (window.innerWidth >= 768 && mobileSidebarToggle) {
            setMobileSidebar(false);
        }
    });
})();
