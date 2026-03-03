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

// Document-level delegation for #theme-toggle button without x-data
(function() {
    var themeToggle = document.getElementById('theme-toggle');
    if (themeToggle && !themeToggle.closest('[x-data]')) {
        themeToggle.addEventListener('click', function() {
            Alpine.store('theme').cycle();
        });
    }

    // Theme radio buttons on profile edit
    document.querySelectorAll('input[name="theme_preference"]').forEach(function(radio) {
        if (radio.closest('[x-data]')) return;
        if (radio.dataset.themeListenerAttached === 'true') return;
        radio.dataset.themeListenerAttached = 'true';
        radio.addEventListener('change', function() {
            if (window._updatingThemeUI) return;
            Alpine.store('theme').set(this.value);
        });
    });

    // Dropdown and mobile theme switcher buttons
    document.querySelectorAll('.dropdown-theme-btn, .mobile-theme-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var theme = this.dataset.theme;
            if (theme) {
                Alpine.store('theme').set(theme);
            }
        });
    });

    // Color scheme swatch buttons (dropdown + mobile)
    document.body.addEventListener('click', function(e) {
        var btn = e.target.closest('.dropdown-scheme-btn, .mobile-scheme-btn');
        if (!btn || !btn.dataset.scheme) return;
        e.preventDefault();
        e.stopPropagation();
        Alpine.store('theme').setScheme(btn.dataset.scheme);
    });

    // Profile edit page: live preview when color scheme radio changes
    document.body.addEventListener('change', function(e) {
        var radio = e.target.closest('input.profile-scheme-radio');
        if (!radio || !radio.dataset.scheme) return;
        Alpine.store('theme').setScheme(radio.dataset.scheme);
    });
})();
