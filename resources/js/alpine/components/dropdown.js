/**
 * Alpine.data('dropdown') - Header navigation dropdown menus
 * Supports click toggle, hover keep-open, and click-outside close.
 */
import Alpine from '@alpinejs/csp';

Alpine.data('dropdown', () => ({
    open: false,
    _closeTimeout: null,

    toggle() {
        this.open = !this.open;
    },

    close() {
        this.open = false;
    },

    delayedClose() {
        this._closeTimeout = setTimeout(() => this.close(), 300);
    },

    cancelClose() {
        clearTimeout(this._closeTimeout);
    }
}));

// Nested submenu (e.g. Foreign languages)
Alpine.data('submenu', () => ({
    open: false,
    _closeTimeout: null,

    show() {
        clearTimeout(this._closeTimeout);
        this.open = true;
    },

    delayedHide() {
        this._closeTimeout = setTimeout(() => { this.open = false; }, 200);
    },

    cancelHide() {
        clearTimeout(this._closeTimeout);
    }
}));
