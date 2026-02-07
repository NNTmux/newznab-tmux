/**
 * Alpine.store('toast') - Global toast notification state
 * Provides a showToast() method accessible from any Alpine component.
 */
import Alpine from '@alpinejs/csp';

Alpine.store('toast', {
    items: [],
    _nextId: 0,

    init() {
        // Process server-side flash messages on load
        this._processFlashMessages();
    },

    /** Show a toast notification */
    show(message, type) {
        type = type || 'success';
        const id = ++this._nextId;
        this.items.push({ id, message, type, removing: false });

        // Auto-remove after 5 seconds
        setTimeout(() => this.dismiss(id), 5000);
    },

    /** Dismiss a toast by id */
    dismiss(id) {
        const item = this.items.find(t => t.id === id);
        if (item) {
            item.removing = true;
            setTimeout(() => {
                this.items = this.items.filter(t => t.id !== id);
            }, 300);
        }
    },

    /** Get icon class for a toast type */
    iconFor(type) {
        if (type === 'success') return 'fa-check-circle';
        if (type === 'error') return 'fa-exclamation-circle';
        if (type === 'warning') return 'fa-exclamation-triangle';
        return 'fa-info-circle';
    },

    /** Process flash messages from the server */
    _processFlashMessages() {
        const el = document.getElementById('flash-messages-data');
        if (!el) return;

        const messages = JSON.parse(el.dataset.messages || '{}');

        if (messages.success) this.show(messages.success, 'success');
        if (messages.error) {
            if (Array.isArray(messages.error)) {
                messages.error.forEach(e => this.show(e, 'error'));
            } else {
                this.show(messages.error, 'error');
            }
        }
        if (messages.warning) this.show(messages.warning, 'warning');
        if (messages.info) this.show(messages.info, 'info');
    }
});

// Keep backward-compatible global showToast for non-Alpine code
window.showToast = function(message, type) {
    Alpine.store('toast').show(message, type);
};
