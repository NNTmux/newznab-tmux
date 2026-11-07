/**
 * Alpine.data('toastContainer') - Toast notification container
 * Renders toast items from the global $store.toast
 */
import Alpine from '@alpinejs/csp';

Alpine.data('toastContainer', () => ({
    items() {
        return Alpine.store('toast').items;
    },

    dismiss(id) {
        Alpine.store('toast').dismiss(id);
    },

    pause(id) {
        Alpine.store('toast').pause(id);
    },

    resume(id) {
        Alpine.store('toast').resume(id);
    },

    roleFor(type) {
        return type === 'error' ? 'alert' : 'status';
    },

    toneFor(type) {
        if (type === 'success') return 'toast-item--success';
        if (type === 'error') return 'toast-item--error';
        if (type === 'warning') return 'toast-item--warning';
        return 'toast-item--info';
    },

    iconFor(type) {
        return Alpine.store('toast').iconFor(type);
    }
}));
