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

    /** Errors interrupt as alerts; everything else is announced through the polite live region. */
    roleFor(type) {
        return type === 'error' ? 'alert' : null;
    },

    announcement() {
        return Alpine.store('toast').announcement;
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
