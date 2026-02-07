/**
 * Alpine.data('adminSubmenu') - Admin sidebar submenu toggle
 */
import Alpine from '@alpinejs/csp';

Alpine.data('adminSubmenu', () => ({
    open: false,

    toggle() {
        this.open = !this.open;
    }
}));
