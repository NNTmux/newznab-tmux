/**
 * Alpine.store('theme') - Global theme state
 * Manages light/dark/system theme with OS preference detection and persistence.
 */
import Alpine from '@alpinejs/csp';

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
const colorSchemes = ['blue', 'indigo', 'cyan', 'teal', 'emerald', 'violet', 'pink', 'rose', 'red', 'orange', 'amber'];

Alpine.store('theme', {
    current: 'light',
    colorScheme: 'blue',

    init() {
        let storedTheme = 'light';
        let storedScheme = 'blue';
        try {
            storedTheme = localStorage.getItem('theme') || storedTheme;
            storedScheme = localStorage.getItem('color_scheme') || storedScheme;
        } catch (_error) {
            // Storage can be disabled by browser privacy settings.
        }
        const meta = document.querySelector('meta[name="theme-preference"]');
        const isAuth = document.querySelector('meta[name="user-authenticated"]');
        this.current = (isAuth && isAuth.content === 'true')
            ? (meta ? meta.content : 'light')
            : storedTheme;

        const schemeMeta = document.querySelector('meta[name="color-scheme-preference"]');
        const schemeData = document.getElementById('current-theme-data');
        const preferredScheme = (isAuth && isAuth.content === 'true')
            ? (schemeMeta ? schemeMeta.content : (schemeData?.dataset?.colorScheme || 'blue'))
            : storedScheme;
        this.colorScheme = colorSchemes.includes(preferredScheme) ? preferredScheme : 'blue';
        if (isAuth && isAuth.content === 'true') this._mirrorToStorage();

        this.apply();
        this.applyScheme();
        this._listenOS();
    },

    /** Set and persist a supported color scheme. */
    setScheme(scheme) {
        if (!colorSchemes.includes(scheme)) return;
        this.colorScheme = scheme;
        this.applyScheme();
        this._save();
    },

    /** Apply color scheme to <html> and meta */
    applyScheme() {
        const html = document.documentElement;
        html.setAttribute('data-color-scheme', this.colorScheme);
        const meta = document.querySelector('meta[name="color-scheme-preference"]');
        if (meta) meta.content = this.colorScheme;
        const dataEl = document.getElementById('current-theme-data');
        if (dataEl) dataEl.dataset.colorScheme = this.colorScheme;
        this._updateUI();
    },

    /** Cycle light -> dark -> system -> light */
    cycle() {
        const next = this.current === 'light' ? 'dark'
            : this.current === 'dark' ? 'system' : 'light';
        this.set(next);
    },

    /** Set and persist a specific theme */
    set(theme) {
        this.current = theme;
        this.apply();
        this._save();
    },

    /** Apply the current theme to <html> */
    apply() {
        const html = document.documentElement;
        if (this.current === 'system') {
            html.classList.toggle('dark', mediaQuery.matches);
        } else {
            html.classList.toggle('dark', this.current === 'dark');
        }
        // Update meta tag
        const meta = document.querySelector('meta[name="theme-preference"]');
        if (meta) meta.content = this.current;

        // Update UI elements (icon, label, title)
        this._updateUI();
    },

    /** Update theme toggle button UI */
    _updateUI() {
        const icon = document.getElementById('theme-icon');
        const label = document.getElementById('theme-label');
        const toggle = document.getElementById('theme-toggle');

        if (icon) {
            icon.classList.remove('fa-sun', 'fa-moon', 'fa-desktop');
            icon.classList.add(this.icon());
        }
        if (label) {
            label.textContent = this.label();
        }
        if (toggle) {
            toggle.title = this.title();
            toggle.setAttribute('aria-label', `Change theme. Current theme: ${this.label()}`);
        }

        // Update dropdown & mobile theme switcher buttons
        var self = this;
        document.querySelectorAll('.dropdown-theme-btn, .mobile-theme-btn').forEach(function(btn) {
            var isActive = btn.dataset.theme === self.current;
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            btn.classList.remove('bg-accent', 'text-white', 'text-gray-300', 'hover:bg-gray-800', 'hover:bg-gray-700', 'hover:text-white');
            if (isActive) {
                btn.classList.add('bg-accent', 'text-white');
            } else {
                btn.classList.add('text-gray-300', 'hover:text-white');
                if (btn.classList.contains('mobile-theme-btn')) {
                    btn.classList.add('hover:bg-gray-700');
                } else {
                    btn.classList.add('hover:bg-gray-800');
                }
            }
        });

        // Update color scheme swatch buttons
        document.querySelectorAll('.dropdown-scheme-btn, .mobile-scheme-btn').forEach(function(btn) {
            var isActive = btn.dataset.scheme === self.colorScheme;
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            btn.classList.remove('ring-2', 'ring-offset-2', 'ring-offset-gray-900', 'ring-primary-500', 'ring-white');
            if (isActive) {
                btn.classList.add('ring-2', 'ring-offset-2', 'ring-offset-gray-900', 'ring-primary-500', 'dark:ring-offset-gray-950');
            }
        });
    },

    icon() {
        return this.current === 'dark' ? 'fa-moon'
            : this.current === 'system' ? 'fa-desktop' : 'fa-sun';
    },

    label() {
        return this.current === 'dark' ? 'Dark'
            : this.current === 'system' ? 'System' : 'Light';
    },

    title() {
        return this.current === 'dark' ? 'Theme: Dark'
            : this.current === 'system' ? 'Theme: System (Auto)' : 'Theme: Light';
    },

    /** Listen for OS-level theme changes */
    _listenOS() {
        mediaQuery.addEventListener('change', () => {
            if (this.current === 'system') this.apply();
        });
    },

    /**
     * Persist theme and color scheme. Always mirrored to localStorage so pages rendered
     * without a session (error pages) can match; signed-in users are also saved server-side.
     */
    _save() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const url = document.querySelector('meta[name="update-theme-url"]')?.content;
        const isAuth = document.querySelector('meta[name="user-authenticated"]');
        const payload = { theme_preference: this.current, color_scheme: this.colorScheme };

        this._mirrorToStorage();

        if (isAuth && isAuth.content === 'true' && url && csrf) {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload)
            }).catch(err => console.error('Error saving theme:', err));
        }
    },

    /** Copy the active theme to localStorage; silently skipped when storage is blocked. */
    _mirrorToStorage() {
        try {
            localStorage.setItem('theme', this.current);
            localStorage.setItem('color_scheme', this.colorScheme);
        } catch (_error) {
            // Keep the selected theme for this page when persistence is unavailable.
        }
    }
});
