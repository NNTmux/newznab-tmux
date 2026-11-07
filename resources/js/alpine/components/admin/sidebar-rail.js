/**
 * Alpine.data('adminSidebarRail') - Collapse the desktop admin sidebar to an icon rail.
 * The state lives on <html data-admin-sidebar="collapsed"> (set before first paint by
 * partials/theme-init) and is remembered in localStorage.
 */
import Alpine from '@alpinejs/csp';

export const ADMIN_SIDEBAR_KEY = 'admin_sidebar';

export function setAdminSidebarCollapsed(collapsed) {
    const html = document.documentElement;
    if (collapsed) {
        html.setAttribute('data-admin-sidebar', 'collapsed');
    } else {
        html.removeAttribute('data-admin-sidebar');
    }
    try {
        localStorage.setItem(ADMIN_SIDEBAR_KEY, collapsed ? 'collapsed' : 'expanded');
    } catch (_error) {
        // Storage can be disabled; the choice then lasts for this page only.
    }
    window.dispatchEvent(new CustomEvent('admin-sidebar-change', { detail: { collapsed } }));
}

Alpine.data('adminSidebarRail', () => ({
    collapsed: false,

    init() {
        this.collapsed = document.documentElement.getAttribute('data-admin-sidebar') === 'collapsed';
        window.addEventListener('admin-sidebar-change', (event) => {
            this.collapsed = event.detail.collapsed;
        });
    },

    toggle() {
        setAdminSidebarCollapsed(!this.collapsed);
    },
}));
