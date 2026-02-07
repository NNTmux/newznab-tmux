/**
 * Alpine.data('themeToggle') - Theme toggle button (cycles light/dark/system)
 * Used on the header theme button and profile edit radio buttons.
 */
import Alpine from '@alpinejs/csp';

Alpine.data('themeToggle', () => ({
    cycle() {
        Alpine.store('theme').cycle();
    }
}));

Alpine.data('themeRadio', () => ({
    _updating: false,

    init() {
        // Watch for external theme changes and sync radio buttons
        this.$watch('$store.theme.current', () => {
            if (this._updating) return;
            this._updating = true;
            this.$nextTick(() => { this._updating = false; });
        });
    },

    select(value) {
        if (this._updating) return;
        this._updating = true;
        Alpine.store('theme').set(value);
        this.$nextTick(() => { this._updating = false; });
    }
}));
