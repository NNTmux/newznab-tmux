/**
 * Alpine.data('sidebarToggle') - Sidebar section collapse/expand
 */
import Alpine from '@alpinejs/csp';

Alpine.data('sidebarToggle', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    }
}));
