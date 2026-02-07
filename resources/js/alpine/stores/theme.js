/**
 * Alpine.store('theme') - Global theme state
 * Manages light/dark/system theme with OS preference detection and persistence.
 */
import Alpine from '@alpinejs/csp';

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

Alpine.store('theme', {
    current: 'light',

    init() {
        const meta = document.querySelector('meta[name="theme-preference"]');
        const isAuth = document.querySelector('meta[name="user-authenticated"]');
        this.current = (isAuth && isAuth.content === 'true')
            ? (meta ? meta.content : 'light')
            : (localStorage.getItem('theme') || 'light');

        this.apply();
        this._listenOS();
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
        this._save(theme);
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

    /** Persist to server (authenticated) or localStorage (guest) */
    _save(theme) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const url = document.querySelector('meta[name="update-theme-url"]')?.content;
        const isAuth = document.querySelector('meta[name="user-authenticated"]');

        if (isAuth && isAuth.content === 'true' && url && csrf) {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ theme_preference: theme })
            }).catch(err => console.error('Error saving theme:', err));
        } else {
            localStorage.setItem('theme', theme);
        }
    }
});
