/**
 * Alpine.store('toast') - Global toast notification state
 * Provides a showToast() method accessible from any Alpine component.
 */
import Alpine from '@alpinejs/csp';

Alpine.store('toast', {
    items: [],
    /** Text of the latest non-error toast, mirrored into a persistent live region for screen readers. */
    announcement: '',
    _nextId: 0,
    _timers: {},

    init() {
        // Process server-side flash messages on load
        this._processFlashMessages();
    },

    /** How long a toast stays visible; errors and warnings wait for the user to dismiss them. */
    durationFor(type) {
        return type === 'error' || type === 'warning' ? null : 5000;
    },

    /** Show a toast notification */
    show(message, type) {
        type = type || 'success';
        const id = ++this._nextId;
        const duration = this.durationFor(type);
        this.items.push({ id, message, type, removing: false, remaining: duration });
        this._schedule(id);
        if (type !== 'error') {
            // Errors carry role="alert" themselves. A live region only speaks when its text changes, so
            // alternate a trailing no-break space to make a repeated message count as new text.
            this.announcement = String(message) + (id % 2 === 0 ? '\u00A0' : '');
        }
    },

    /** Pause auto-dismiss while the pointer or keyboard focus is on a toast. */
    pause(id) {
        const item = this.items.find(t => t.id === id);
        if (!item || item.remaining === null || !this._timers[id]) return;
        clearTimeout(this._timers[id].handle);
        item.remaining = Math.max(0, item.remaining - (Date.now() - this._timers[id].startedAt));
        delete this._timers[id];
    },

    /** Resume auto-dismiss with the time that was left. */
    resume(id) {
        this._schedule(id);
    },

    /** Dismiss a toast by id */
    dismiss(id) {
        const item = this.items.find(t => t.id === id);
        if (this._timers[id]) {
            clearTimeout(this._timers[id].handle);
            delete this._timers[id];
        }
        if (item) {
            item.removing = true;
            setTimeout(() => {
                this.items = this.items.filter(t => t.id !== id);
            }, 300);
        }
    },

    _schedule(id) {
        const item = this.items.find(t => t.id === id);
        if (!item || item.remaining === null || this._timers[id]) return;
        this._timers[id] = { startedAt: Date.now(), handle: setTimeout(() => this.dismiss(id), item.remaining) };
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
