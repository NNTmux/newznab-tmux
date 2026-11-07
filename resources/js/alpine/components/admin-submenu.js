/**
 * Alpine.data('adminSubmenu') - Admin sidebar submenu toggle (CSP-safe)
 */
import Alpine from '@alpinejs/csp';
import { setAdminSidebarCollapsed } from './admin/sidebar-rail.js';

Alpine.data('adminSubmenu', () => ({
    open: false,

    init() {
        // The server marks the group holding the current page; the URL check covers cached markup.
        this.open = this.$el.dataset.open === 'true' || Array.from(this.$el.querySelectorAll('a[href]')).some((link) => {
            try {
                var linkUrl = new URL(link.href, window.location.origin);
                return linkUrl.origin === window.location.origin && linkUrl.pathname === window.location.pathname;
            } catch (_error) {
                return false;
            }
        });
    },

    toggle() {
        // In the collapsed icon rail a group click expands the sidebar and opens that group.
        if (document.documentElement.getAttribute('data-admin-sidebar') === 'collapsed') {
            setAdminSidebarCollapsed(false);
            this.open = true;
            return;
        }
        this.open = !this.open;
    }
}));

